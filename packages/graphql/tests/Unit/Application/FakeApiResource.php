<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\API\Application\UnsupportedResourceOperationException;
use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;

/**
 * A minimal in-memory ApiResourceInterface, so GraphQL-layer tests (the
 * schema registry, the executor, the health check) don't need a live
 * database the way kontor/api's own OrganizationResource would.
 */
final class FakeApiResource implements ApiResourceInterface
{
    /**
     * @param array<string, array<string, mixed>> $recordsByUid
     */
    public function __construct(
        private readonly string $resourceKey,
        private readonly array $recordsByUid = [],
    ) {
    }

    public function key(): string
    {
        return $this->resourceKey;
    }

    public function schema(): ApiResourceSchema
    {
        return new ApiResourceSchema(fields: ['uid' => 'string', 'name' => 'string']);
    }

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
    {
        $rows = array_values($this->recordsByUid);

        return new ApiCollectionResult($rows, count($rows));
    }

    public function find(string $organizationId, string $uid): ?array
    {
        return $this->recordsByUid[$uid] ?? null;
    }

    public function create(string $organizationId, array $attributes): array
    {
        throw UnsupportedResourceOperationException::forResource($this->resourceKey, 'create');
    }

    public function update(string $organizationId, string $uid, array $attributes): array
    {
        throw UnsupportedResourceOperationException::forResource($this->resourceKey, 'update');
    }

    public function delete(string $organizationId, string $uid): void
    {
        throw UnsupportedResourceOperationException::forResource($this->resourceKey, 'delete');
    }
}
