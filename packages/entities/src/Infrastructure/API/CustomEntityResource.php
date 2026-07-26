<?php

declare(strict_types=1);

namespace Kontor\Entities\Infrastructure\API;

use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Domain\EntityDefinition;
use Kontor\Entities\Domain\EntityRecord;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use RuntimeException;

final class CustomEntityResource implements ApiResourceInterface
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        private readonly string $entityKey,
        private readonly array $fields,
        private readonly EntityDefinitionRepository $definitions,
        private readonly EntityRecordRepository $records,
        private readonly EntityRecordService $recordService,
    ) {
    }

    public function key(): string
    {
        return 'entities_' . $this->entityKey;
    }

    public function schema(): ApiResourceSchema
    {
        return new ApiResourceSchema([
            ...$this->fields,
            'uid' => 'string',
            'status' => 'string',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ]);
    }

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
    {
        $definition = $this->definitionFor($organizationId);
        $offset = ($query->page - 1) * $query->pageSize;

        return new ApiCollectionResult(
            array_map(
                $this->present(...),
                $this->records->forDefinitionPage(
                    $definition->uid->toString(),
                    $query->pageSize,
                    $offset,
                ),
            ),
            $this->records->countForDefinition($definition->uid->toString()),
        );
    }

    public function find(string $organizationId, string $uid): ?array
    {
        $definition = $this->definitionFor($organizationId);
        $record = $this->records->findActive($uid);

        return $record !== null
            && hash_equals($record->organizationId, $organizationId)
            && hash_equals($record->definitionUid, $definition->uid->toString())
                ? $this->present($record)
                : null;
    }

    public function create(string $organizationId, array $attributes): array
    {
        $definition = $this->definitionFor($organizationId);
        $record = $this->recordService->create(
            $definition->uid->toString(),
            $this->customAttributes($attributes),
        );

        return $this->present($record);
    }

    public function update(string $organizationId, string $uid, array $attributes): array
    {
        $definition = $this->definitionFor($organizationId);
        $record = $this->requireOwned($organizationId, $definition, $uid);
        $record = $this->recordService->update(
            $uid,
            array_replace($record->data, $this->customAttributes($attributes)),
        );

        return $this->present($record);
    }

    public function delete(string $organizationId, string $uid): void
    {
        $definition = $this->definitionFor($organizationId);
        $this->requireOwned($organizationId, $definition, $uid);
        $this->records->archive($uid);
    }

    private function definitionFor(string $organizationId): EntityDefinition
    {
        $definition = $this->definitions->findActiveByKey($organizationId, $this->entityKey);

        if ($definition === null || !$definition->apiExposed || !$definition->isActive()) {
            throw new RuntimeException("Custom entity \"{$this->entityKey}\" is not API exposed.");
        }

        return $definition;
    }

    private function requireOwned(
        string $organizationId,
        EntityDefinition $definition,
        string $uid,
    ): EntityRecord {
        $record = $this->records->findActive($uid);
        if ($record === null) {
            throw new RuntimeException("Custom entity record \"{$uid}\" was not found.");
        }
        if (
            !hash_equals($record->organizationId, $organizationId)
            || !hash_equals($record->definitionUid, $definition->uid->toString())
        ) {
            throw new RuntimeException("Custom entity record \"{$uid}\" was not found.");
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function customAttributes(array $attributes): array
    {
        return array_diff_key(
            $attributes,
            array_flip(['uid', 'status', 'createdAt', 'updatedAt']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EntityRecord $record): array
    {
        return [
            ...$record->data,
            'uid' => $record->uid->toString(),
            'status' => $record->status,
            'createdAt' => $record->createdAt->format(DATE_ATOM),
            'updatedAt' => $record->updatedAt->format(DATE_ATOM),
        ];
    }
}
