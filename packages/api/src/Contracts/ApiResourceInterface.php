<?php

declare(strict_types=1);

namespace Kontor\API\Contracts;

use Kontor\API\Application\UnsupportedResourceOperationException;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;

/**
 * The "CRUD resources" milestone (kontor.md#20.10). A business component
 * registers one implementation per resource into `ApiResourceRegistry` —
 * `kontor/api` itself never depends on a business component's classes,
 * the same inverted-dependency shape every other registry in this
 * monorepo already uses (`ReportProviderRegistry`, `WidgetRegistry`,
 * `ActionHandlerRegistry`). Not every resource supports every operation
 * (kontor.md#20.10 itself has resources with no `DELETE`, e.g.
 * `/companies`) — an unsupported operation throws
 * `UnsupportedResourceOperationException` rather than silently no-op'ing.
 */
interface ApiResourceInterface
{
    public function key(): string;

    public function schema(): ApiResourceSchema;

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $organizationId, string $uid): ?array;

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     *
     * @throws UnsupportedResourceOperationException
     */
    public function create(string $organizationId, array $attributes): array;

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     *
     * @throws UnsupportedResourceOperationException
     */
    public function update(string $organizationId, string $uid, array $attributes): array;

    /**
     * @throws UnsupportedResourceOperationException
     */
    public function delete(string $organizationId, string $uid): void;
}
