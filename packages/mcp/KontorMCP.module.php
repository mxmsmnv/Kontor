<?php

namespace ProcessWire;

use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\MCP\Application\KontorMcpBridge;
use Kontor\MCP\Health\McpIntegrationHealthCheck;

class KontorMCP extends WireData implements Module
{
    private ?KontorMcpBridge $bridge = null;

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor MCP',
            'summary' => 'Full scoped MCP integration for installed Kontor resources, search and settings.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Kontor',
            'icon' => 'exchange',
            'singular' => true,
            'autoload' => true,
            'mcpProvider' => true,
            'requires' => ['Kontor', 'KontorAPI', 'McpServer'],
        ];
    }

    /** @return array{name: string, title: string, version: string} */
    public function mcpProviderInfo(): array
    {
        return [
            'name' => 'kontor',
            'title' => 'Kontor business operations',
            'version' => '1.0.0',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function mcpTools(): array
    {
        return [
            $this->tool(
                'kontor_status',
                'Kontor status',
                'Return installed component, resource and integration readiness without credentials.',
                [$this, 'mcpKontorStatus'],
            ),
            $this->tool(
                'kontor_components',
                'Kontor components',
                'List installed Kontor components and their enabled state.',
                [$this, 'mcpKontorComponents'],
            ),
            $this->tool(
                'kontor_resources',
                'Kontor resource capabilities',
                'List resources contributed by currently installed components, their fields and supported operations.',
                [$this, 'mcpKontorResources'],
            ),
            $this->tool(
                'kontor_search',
                'Search Kontor',
                'Search installed Kontor providers with a bounded query and optional entity types.',
                [$this, 'mcpKontorSearch'],
                'read',
                [
                    'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'entity_types' => $this->jsonDocumentSchema(16_384, 'JSON array of stable entity type names.'),
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10_000],
                ],
                ['query'],
            ),
            $this->tool(
                'kontor_records_list',
                'List Kontor records',
                'Return one bounded page from an available Kontor API resource.',
                [$this, 'mcpKontorRecordsList'],
                'read',
                [
                    'resource' => $this->resourceSchema(),
                    'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100_000],
                    'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    'filters' => $this->jsonDocumentSchema(16_384, 'JSON object using only the resource filterable fields.'),
                    'sort' => $this->jsonDocumentSchema(16_384, 'JSON array of field and direction objects.'),
                    'fields' => $this->jsonDocumentSchema(16_384, 'JSON array of fields to return.'),
                ],
                ['resource'],
            ),
            $this->tool(
                'kontor_record_get',
                'Get a Kontor record',
                'Read one record by stable ULID from an available Kontor API resource.',
                [$this, 'mcpKontorRecordGet'],
                'read',
                [
                    'resource' => $this->resourceSchema(),
                    'uid' => $this->uidSchema(),
                    'fields' => $this->jsonDocumentSchema(16_384, 'JSON array of fields to return.'),
                ],
                ['resource', 'uid'],
            ),
            $this->tool(
                'kontor_record_validate',
                'Validate a Kontor record change',
                'Validate a bounded create or update document without changing business records.',
                [$this, 'mcpKontorRecordValidate'],
                'read',
                [
                    'resource' => $this->resourceSchema(),
                    'operation' => ['type' => 'string', 'enum' => ['create', 'update']],
                    'document' => $this->jsonDocumentSchema(KontorMcpBridge::MAX_DOCUMENT_BYTES, 'JSON object containing only writable resource fields.'),
                    'uid' => ['type' => 'string', 'maxLength' => 26],
                ],
                ['resource', 'operation', 'document'],
            ),
            $this->tool(
                'kontor_record_create',
                'Create a Kontor record',
                'Create one validated business record through its owning resource. Replays return the original result.',
                [$this, 'mcpKontorRecordCreate'],
                'draft',
                [
                    'resource' => $this->resourceSchema(),
                    'document' => $this->jsonDocumentSchema(KontorMcpBridge::MAX_DOCUMENT_BYTES, 'JSON object containing only writable resource fields.'),
                    'idempotency_key' => $this->idempotencySchema(),
                ],
                ['resource', 'document', 'idempotency_key'],
            ),
            $this->tool(
                'kontor_record_update',
                'Update a Kontor record',
                'Update one validated business record by stable ULID. Replays return the original result.',
                [$this, 'mcpKontorRecordUpdate'],
                'draft',
                [
                    'resource' => $this->resourceSchema(),
                    'uid' => $this->uidSchema(),
                    'document' => $this->jsonDocumentSchema(KontorMcpBridge::MAX_DOCUMENT_BYTES, 'JSON object containing only writable resource fields.'),
                    'idempotency_key' => $this->idempotencySchema(),
                ],
                ['resource', 'uid', 'document', 'idempotency_key'],
            ),
            $this->tool(
                'kontor_record_archive',
                'Archive a reviewed Kontor record',
                'Archive one reviewed record through its owning resource after explicit confirmation.',
                [$this, 'mcpKontorRecordArchive'],
                'admin',
                [
                    'resource' => $this->resourceSchema(),
                    'uid' => $this->uidSchema(),
                    'idempotency_key' => $this->idempotencySchema(),
                    'confirmation' => ['type' => 'string', 'const' => 'ARCHIVE_REVIEWED_KONTOR_RECORD'],
                ],
                ['resource', 'uid', 'idempotency_key', 'confirmation'],
                destructive: true,
            ),
            $this->tool(
                'kontor_settings_export',
                'Export Kontor settings',
                'Return a versioned, secret-free profile from every available Kontor settings provider.',
                [$this, 'mcpKontorSettingsExport'],
            ),
            $this->tool(
                'kontor_settings_preview',
                'Preview Kontor settings import',
                'Validate a portable settings profile and return every proposed change without applying it.',
                [$this, 'mcpKontorSettingsPreview'],
                'read',
                ['document' => $this->jsonDocumentSchema(KontorMcpBridge::MAX_DOCUMENT_BYTES, 'A Kontor settings profile JSON object.')],
                ['document'],
            ),
            $this->tool(
                'kontor_settings_apply',
                'Apply reviewed Kontor settings',
                'Apply a valid reviewed settings profile after explicit confirmation. Replays return the original result.',
                [$this, 'mcpKontorSettingsApply'],
                'admin',
                [
                    'document' => $this->jsonDocumentSchema(KontorMcpBridge::MAX_DOCUMENT_BYTES, 'A reviewed Kontor settings profile JSON object.'),
                    'idempotency_key' => $this->idempotencySchema(),
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_REVIEWED_KONTOR_SETTINGS'],
                ],
                ['document', 'idempotency_key', 'confirmation'],
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function mcpKontorStatus(): array
    {
        return $this->safe(fn (): array => $this->bridge()->status());
    }

    /** @return array<string, mixed> */
    public function mcpKontorComponents(): array
    {
        return $this->safe(fn (): array => $this->bridge()->components());
    }

    /** @return array<string, mixed> */
    public function mcpKontorResources(): array
    {
        return $this->safe(fn (): array => $this->bridge()->resourceSchemas());
    }

    /** @return array<string, mixed> */
    public function mcpKontorSearch(string $query, string $entity_types = '[]', int $limit = 20, int $offset = 0): array
    {
        return $this->safe(fn (): array => $this->bridge()->search($query, $entity_types, $limit, $offset));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordsList(
        string $resource,
        int $page = 1,
        int $page_size = 50,
        string $filters = '{}',
        string $sort = '[]',
        string $fields = '[]',
    ): array {
        return $this->safe(fn (): array => $this->bridge()->listRecords(
            $resource,
            $page,
            $page_size,
            $filters,
            $sort,
            $fields,
        ));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordGet(string $resource, string $uid, string $fields = '[]'): array
    {
        return $this->safe(fn (): array => $this->bridge()->getRecord($resource, $uid, $fields));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordValidate(string $resource, string $operation, string $document, string $uid = ''): array
    {
        return $this->safe(fn (): array => $this->bridge()->validateRecord($resource, $operation, $document, $uid));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordCreate(string $resource, string $document, string $idempotency_key): array
    {
        return $this->safe(fn (): array => $this->bridge()->createRecord($resource, $document, $idempotency_key));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordUpdate(string $resource, string $uid, string $document, string $idempotency_key): array
    {
        return $this->safe(fn (): array => $this->bridge()->updateRecord($resource, $uid, $document, $idempotency_key));
    }

    /** @return array<string, mixed> */
    public function mcpKontorRecordArchive(
        string $resource,
        string $uid,
        string $idempotency_key,
        string $confirmation,
    ): array {
        return $this->safe(fn (): array => $this->bridge()->archiveRecord(
            $resource,
            $uid,
            $idempotency_key,
            $confirmation,
        ));
    }

    /** @return array<string, mixed> */
    public function mcpKontorSettingsExport(): array
    {
        return $this->safe(fn (): array => $this->bridge()->exportSettings([
            'host' => (string) $this->wire()->config->httpHost,
            'transport' => 'mcp',
        ]));
    }

    /** @return array<string, mixed> */
    public function mcpKontorSettingsPreview(string $document): array
    {
        return $this->safe(fn (): array => $this->bridge()->previewSettings($document));
    }

    /** @return array<string, mixed> */
    public function mcpKontorSettingsApply(string $document, string $idempotency_key, string $confirmation): array
    {
        return $this->safe(fn (): array => $this->bridge()->applySettings(
            $document,
            $idempotency_key,
            $confirmation,
        ));
    }

    public function bridge(): KontorMcpBridge
    {
        if ($this->bridge !== null) {
            return $this->bridge;
        }

        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        /** @var KontorAPI $api */
        $api = $this->wire()->modules->get('KontorAPI');
        $organizations = $kontor->container()->get(OrganizationRepository::class);
        $organization = $organizations->defaultOrganization('US', 'en', 'USD');
        $search = null;
        $settings = null;

        if ($this->wire()->modules->isInstalled('KontorSearch')) {
            /** @var KontorSearch $searchModule */
            $searchModule = $this->wire()->modules->get('KontorSearch');
            $search = $searchModule->globalSearchService();
        }
        if ($this->wire()->modules->isInstalled('KontorSettings')) {
            /** @var KontorSettings $settingsModule */
            $settingsModule = $this->wire()->modules->get('KontorSettings');
            $settings = $settingsModule->migrationService();
        }

        return $this->bridge = new KontorMcpBridge(
            organizationUid: $organization->uid->toString(),
            resources: $api->resourceRegistry(),
            components: $kontor->container()->get(ComponentRegistry::class),
            idempotency: new IdempotencyService($api->idempotencyKeyRepository()),
            search: $search,
            settings: $settings,
        );
    }

    public function healthCheck(): McpIntegrationHealthCheck
    {
        return new McpIntegrationHealthCheck($this->bridge());
    }

    public function ___install(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $components = $kontor->container()->get(ComponentRegistry::class);
        $components->markInstalled('mcp', self::getModuleInfo()['version'], 'mcp');
        $components->enable('mcp');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $this->___install();
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor MCP removed. MCP Server clients and audit history were preserved.'));
    }

    /**
     * @param array<string, mixed> $properties
     * @param string[] $required
     * @return array<string, mixed>
     */
    private function tool(
        string $name,
        string $title,
        string $description,
        callable $handler,
        string $scope = 'read',
        array $properties = [],
        array $required = [],
        bool $destructive = false,
    ): array {
        return [
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'handler' => $handler,
            'scope' => $scope,
            'read_only' => $scope === 'read',
            'destructive' => $destructive,
            'idempotent' => true,
            'open_world' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => $properties !== [] ? $properties : new \stdClass(),
                'required' => $required,
                'additionalProperties' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function resourceSchema(): array
    {
        return ['type' => 'string', 'minLength' => 2, 'maxLength' => 64, 'pattern' => '^[a-z][a-z0-9_]{1,63}$'];
    }

    /** @return array<string, mixed> */
    private function uidSchema(): array
    {
        return ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'];
    }

    /** @return array<string, mixed> */
    private function idempotencySchema(): array
    {
        return ['type' => 'string', 'minLength' => 8, 'maxLength' => 160, 'pattern' => '^[A-Za-z0-9._:-]+$'];
    }

    /** @return array<string, mixed> */
    private function jsonDocumentSchema(int $maxLength, string $description): array
    {
        return [
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => $maxLength,
            'description' => $description,
        ];
    }

    /** @param callable(): array<string, mixed> $operation @return array<string, mixed> */
    private function safe(callable $operation): array
    {
        try {
            return $operation();
        } catch (\InvalidArgumentException|\Kontor\API\Application\IdempotencyKeyConflictException $exception) {
            throw new WireException($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->wire()->log->save('kontor-mcp', 'Provider operation failed: ' . $exception->getMessage());
            throw new WireException($this->_('The Kontor operation could not be completed.'));
        }
    }
}
