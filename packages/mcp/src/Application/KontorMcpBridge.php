<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use InvalidArgumentException;
use Kontor\API\Application\IdempotencyService;
use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\Settings\Contracts\SettingsMigrationInterface;

final class KontorMcpBridge
{
    public const MAX_DOCUMENT_BYTES = 1_048_576;
    public const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly string $organizationUid,
        private readonly ApiResourceRegistry $resources,
        private readonly ComponentRegistryInterface $components,
        private readonly IdempotencyService $idempotency,
        private readonly ?SearchProviderInterface $search = null,
        private readonly ?SettingsMigrationInterface $settings = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $components = $this->componentRows();
        $enabled = count(array_filter(
            $components,
            static fn (array $component): bool => $component['status'] === 'enabled',
        ));

        return [
            'organization_uid' => $this->organizationUid,
            'components' => [
                'installed' => count($components),
                'enabled' => $enabled,
            ],
            'resources' => count($this->resources->all()),
            'features' => [
                'search' => $this->search !== null,
                'settings_migration' => $this->settings !== null,
                'resource_crud' => true,
            ],
            'access_model' => 'mcp_scopes_and_resource_capabilities',
        ];
    }

    /** @return array<string, mixed> */
    public function components(): array
    {
        $items = $this->componentRows();

        return ['items' => $items, 'total' => count($items)];
    }

    /** @return array<string, mixed> */
    public function resourceSchemas(): array
    {
        $items = [];
        foreach ($this->resources->all() as $key => $resource) {
            $schema = $resource->schema();
            $fields = array_filter(
                $schema->fields,
                fn (string $type, string $field): bool => !$this->isSensitiveKey($field),
                ARRAY_FILTER_USE_BOTH,
            );
            $items[] = [
                'resource' => $key,
                'fields' => $fields,
                'filterable_fields' => $schema->filterableFields,
                'sortable_fields' => $schema->sortableFields,
                'operations' => [
                    'list' => true,
                    'find' => true,
                    'create' => $schema->supportsCreate,
                    'update' => $schema->supportsUpdate,
                    'archive' => $schema->supportsDelete,
                ],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['resource'] <=> $b['resource']);

        return ['items' => $items, 'total' => count($items)];
    }

    /** @return array<string, mixed> */
    public function listRecords(
        string $resourceKey,
        int $page = 1,
        int $pageSize = 50,
        string $filtersJson = '{}',
        string $sortJson = '[]',
        string $fieldsJson = '[]',
    ): array {
        $resource = $this->resource($resourceKey);
        $schema = $resource->schema();
        $filters = $this->decodeObject($filtersJson, 'filters', 16_384);
        $sort = $this->decodeList($sortJson, 'sort', 16_384);
        $fields = $this->decodeStringList($fieldsJson, 'fields', 16_384);

        foreach ($filters as $field => $value) {
            if (!in_array($field, $schema->filterableFields, true)) {
                throw new InvalidArgumentException("Resource \"{$resourceKey}\" does not support filter \"{$field}\".");
            }
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Filter \"{$field}\" must be a scalar value.");
            }
        }

        $normalizedSort = [];
        foreach ($sort as $item) {
            if (!is_array($item) || !is_string($item['field'] ?? null)) {
                throw new InvalidArgumentException('Every sort entry needs a field and optional direction.');
            }
            $field = $item['field'];
            $direction = strtolower((string) ($item['direction'] ?? 'asc'));
            if (!in_array($field, $schema->sortableFields, true) || !in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException("Unsupported sort for resource \"{$resourceKey}\".");
            }
            $normalizedSort[] = ['field' => $field, 'direction' => $direction];
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $schema->fields)) {
                throw new InvalidArgumentException("Resource \"{$resourceKey}\" does not expose field \"{$field}\".");
            }
        }

        $query = new ApiQuery(
            page: max(1, $page),
            pageSize: max(1, min(self::MAX_PAGE_SIZE, $pageSize)),
            filters: array_map(static fn (mixed $value): string => (string) $value, $filters),
            sort: $normalizedSort,
            fields: $fields !== [] ? $fields : null,
        );
        $result = $resource->list($this->organizationUid, $query);
        $rows = array_map(fn (array $row): array => $this->project($this->scrub($row), $query->fields), $result->rows);

        return [
            'resource' => $resourceKey,
            'items' => $rows,
            'page' => $query->page,
            'page_size' => $query->pageSize,
            'total' => $result->total,
        ];
    }

    /** @return array<string, mixed> */
    public function getRecord(string $resourceKey, string $uid, string $fieldsJson = '[]'): array
    {
        $resource = $this->resource($resourceKey);
        $fields = $this->decodeStringList($fieldsJson, 'fields', 16_384);
        foreach ($fields as $field) {
            if (!array_key_exists($field, $resource->schema()->fields)) {
                throw new InvalidArgumentException("Resource \"{$resourceKey}\" does not expose field \"{$field}\".");
            }
        }
        $record = $resource->find($this->organizationUid, $this->uid($uid));
        if ($record === null) {
            throw new InvalidArgumentException("Record was not found in resource \"{$resourceKey}\".");
        }

        return [
            'resource' => $resourceKey,
            'record' => $this->project($this->scrub($record), $fields !== [] ? $fields : null),
        ];
    }

    /** @return array<string, mixed> */
    public function validateRecord(string $resourceKey, string $operation, string $document, string $uid = ''): array
    {
        $resource = $this->resource($resourceKey);
        $attributes = $this->validateAttributes($resource, $operation, $this->decodeObject($document, 'document'));
        if ($operation === 'update') {
            $this->uid($uid);
        }

        return [
            'valid' => true,
            'resource' => $resourceKey,
            'operation' => $operation,
            'uid' => $operation === 'update' ? $uid : null,
            'accepted_fields' => array_keys($attributes),
        ];
    }

    /** @return array<string, mixed> */
    public function createRecord(string $resourceKey, string $document, string $idempotencyKey): array
    {
        $resource = $this->resource($resourceKey);
        $attributes = $this->validateAttributes($resource, 'create', $this->decodeObject($document, 'document'));
        $request = ['resource' => $resourceKey, 'attributes' => $attributes];
        $stored = $this->idempotency->remember(
            $this->organizationUid,
            'mcp:create:' . $this->idempotencyKey($idempotencyKey),
            $request,
            fn (): array => ['status' => 201, 'body' => $this->scrub($resource->create($this->organizationUid, $attributes))],
        );

        return ['resource' => $resourceKey, 'record' => $stored->body, 'replay_safe' => true];
    }

    /** @return array<string, mixed> */
    public function updateRecord(string $resourceKey, string $uid, string $document, string $idempotencyKey): array
    {
        $resource = $this->resource($resourceKey);
        $uid = $this->uid($uid);
        $attributes = $this->validateAttributes($resource, 'update', $this->decodeObject($document, 'document'));
        $request = ['resource' => $resourceKey, 'uid' => $uid, 'attributes' => $attributes];
        $stored = $this->idempotency->remember(
            $this->organizationUid,
            'mcp:update:' . $this->idempotencyKey($idempotencyKey),
            $request,
            fn (): array => ['status' => 200, 'body' => $this->scrub($resource->update($this->organizationUid, $uid, $attributes))],
        );

        return ['resource' => $resourceKey, 'record' => $stored->body, 'replay_safe' => true];
    }

    /** @return array<string, mixed> */
    public function archiveRecord(
        string $resourceKey,
        string $uid,
        string $idempotencyKey,
        string $confirmation,
    ): array {
        if ($confirmation !== 'ARCHIVE_REVIEWED_KONTOR_RECORD') {
            throw new InvalidArgumentException('Explicit archive confirmation is required.');
        }
        $resource = $this->resource($resourceKey);
        if (!$resource->schema()->supportsDelete) {
            throw new InvalidArgumentException("Resource \"{$resourceKey}\" does not support archive.");
        }
        $uid = $this->uid($uid);
        $request = ['resource' => $resourceKey, 'uid' => $uid, 'confirmation' => $confirmation];
        $stored = $this->idempotency->remember(
            $this->organizationUid,
            'mcp:archive:' . $this->idempotencyKey($idempotencyKey),
            $request,
            function () use ($resource, $uid): array {
                $resource->delete($this->organizationUid, $uid);

                return ['status' => 200, 'body' => ['uid' => $uid, 'archived' => true]];
            },
        );

        return ['resource' => $resourceKey, ...$stored->body, 'replay_safe' => true];
    }

    /** @return array<string, mixed> */
    public function search(string $term, string $entityTypesJson = '[]', int $limit = 20, int $offset = 0): array
    {
        if ($this->search === null) {
            throw new InvalidArgumentException('Kontor Search is not installed.');
        }
        $term = trim($term);
        if (mb_strlen($term) < 2 || mb_strlen($term) > 200) {
            throw new InvalidArgumentException('Search query must contain 2 to 200 characters.');
        }
        $entityTypes = $this->decodeStringList($entityTypesJson, 'entity_types', 16_384);
        $result = $this->search->search(new SearchQuery(
            organizationId: $this->organizationUid,
            term: $term,
            entityTypes: $entityTypes,
            limit: max(1, min(50, $limit)),
            offset: max(0, min(10_000, $offset)),
        ));

        return [
            'items' => array_map(static fn (SearchHit $hit): array => [
                'entity_type' => $hit->entityType,
                'uid' => $hit->entityUid,
                'title' => $hit->title,
                'subtitle' => $hit->subtitle,
                'admin_url' => $hit->url,
                'score' => $hit->score,
            ], $result->hits),
            'total' => $result->total,
        ];
    }

    /** @return array<string, mixed> */
    public function exportSettings(array $source = []): array
    {
        return $this->settings()->exportProfile($source);
    }

    /** @return array<string, mixed> */
    public function previewSettings(string $document): array
    {
        $profile = $this->settings()->decode($document);

        return $this->settings()->preview($profile)->toArray();
    }

    /** @return array<string, mixed> */
    public function applySettings(string $document, string $idempotencyKey, string $confirmation): array
    {
        if ($confirmation !== 'APPLY_REVIEWED_KONTOR_SETTINGS') {
            throw new InvalidArgumentException('Explicit settings confirmation is required.');
        }
        $profile = $this->settings()->decode($document);
        $preview = $this->settings()->preview($profile);
        if (!$preview->successful()) {
            throw new InvalidArgumentException('Settings profile contains validation errors. Preview it first.');
        }
        $request = [
            'fingerprint' => hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'confirmation' => $confirmation,
        ];
        $stored = $this->idempotency->remember(
            $this->organizationUid,
            'mcp:settings:' . $this->idempotencyKey($idempotencyKey),
            $request,
            fn (): array => ['status' => 200, 'body' => $this->settings()->apply($profile)->toArray()],
        );

        return [...$stored->body, 'replay_safe' => true];
    }

    /** @return array<int, array{name: string, version: string, status: string}> */
    private function componentRows(): array
    {
        return array_map(static fn (array $component): array => [
            'name' => (string) ($component['name'] ?? ''),
            'version' => (string) ($component['version'] ?? ''),
            'status' => (string) ($component['status'] ?? ''),
        ], $this->components->all());
    }

    private function resource(string $key): ApiResourceInterface
    {
        $key = strtolower(trim($key));
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1 || !$this->resources->has($key)) {
            throw new InvalidArgumentException("Kontor API resource \"{$key}\" is not available on this installation.");
        }

        return $this->resources->get($key);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function validateAttributes(ApiResourceInterface $resource, string $operation, array $attributes): array
    {
        $schema = $resource->schema();
        if (!in_array($operation, ['create', 'update'], true)) {
            throw new InvalidArgumentException('Operation must be create or update.');
        }
        if ($operation === 'create' && !$schema->supportsCreate) {
            throw new InvalidArgumentException("Resource \"{$resource->key()}\" does not support create.");
        }
        if ($operation === 'update' && !$schema->supportsUpdate) {
            throw new InvalidArgumentException("Resource \"{$resource->key()}\" does not support update.");
        }
        if ($attributes === []) {
            throw new InvalidArgumentException('Document must contain at least one field.');
        }

        foreach ($attributes as $field => $value) {
            $type = $schema->fields[$field] ?? null;
            if ($type === null || in_array($field, ['uid', 'createdAt', 'updatedAt'], true)) {
                throw new InvalidArgumentException("Field \"{$field}\" is not writable for resource \"{$resource->key()}\".");
            }
            if (!$this->matchesType($value, $type)) {
                throw new InvalidArgumentException("Field \"{$field}\" must match type \"{$type}\".");
            }
        }
        $this->assertNoSensitiveKeys($attributes, 'document');

        return $attributes;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        if ($value === null) {
            return true;
        }

        return match ($type) {
            'int', 'integer' => is_int($value),
            'decimal', 'money' => is_int($value) || is_float($value) || is_string($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            default => is_string($value),
        };
    }

    /** @return array<string, mixed> */
    private function decodeObject(string $json, string $label, int $maxBytes = self::MAX_DOCUMENT_BYTES): array
    {
        if ($json === '' || strlen($json) > $maxBytes) {
            throw new InvalidArgumentException("{$label} must be non-empty and smaller than {$maxBytes} bytes.");
        }
        try {
            $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException("{$label} is not valid JSON.", previous: $exception);
        }
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException("{$label} must contain a JSON object.");
        }

        return $value;
    }

    /** @return array<int, mixed> */
    private function decodeList(string $json, string $label, int $maxBytes): array
    {
        if (strlen($json) > $maxBytes) {
            throw new InvalidArgumentException("{$label} is too large.");
        }
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException("{$label} is not valid JSON.", previous: $exception);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException("{$label} must contain a JSON array.");
        }

        return $value;
    }

    /** @return string[] */
    private function decodeStringList(string $json, string $label, int $maxBytes): array
    {
        $items = $this->decodeList($json, $label, $maxBytes);
        foreach ($items as $item) {
            if (!is_string($item) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $item) !== 1) {
                throw new InvalidArgumentException("{$label} may contain only stable field or entity names.");
            }
        }

        return array_values(array_unique($items));
    }

    private function uid(string $uid): string
    {
        $uid = trim($uid);
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $uid) !== 1) {
            throw new InvalidArgumentException('Record uid must be a valid ULID.');
        }

        return $uid;
    }

    private function idempotencyKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $key) !== 1) {
            throw new InvalidArgumentException('Idempotency key must contain 8 to 160 safe characters.');
        }

        return $key;
    }

    /** @param array<string, mixed> $row @param string[]|null $fields @return array<string, mixed> */
    private function project(array $row, ?array $fields): array
    {
        return $fields === null ? $row : array_intersect_key($row, array_flip($fields));
    }

    /** @param array<string, mixed> $data */
    private function assertNoSensitiveKeys(array $data, string $path): void
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ($this->isSensitiveKey($key)) {
                throw new InvalidArgumentException("Sensitive field \"{$path}.{$key}\" is not available through MCP.");
            }
            if (is_array($value)) {
                $this->assertNoSensitiveKeys($value, $path . '.' . $key);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function scrub(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if ($this->isSensitiveKey((string) $key)) {
                unset($data[$key]);
                continue;
            }
            if (is_array($data[$key])) {
                $data[$key] = $this->scrub($data[$key]);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:pass(?:word)?|secret|token|api[_-]?key|private[_-]?key|credential|auth[_-]?salt)/i', $key) === 1;
    }

    private function settings(): SettingsMigrationInterface
    {
        return $this->settings ?? throw new InvalidArgumentException('Kontor Settings is not installed.');
    }
}
