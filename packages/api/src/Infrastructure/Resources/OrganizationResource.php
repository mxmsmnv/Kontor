<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Resources;

use Kontor\API\Application\UnsupportedResourceOperationException;
use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

/**
 * The one trivial built-in resource proving the CRUD-resource pipeline
 * end-to-end (the same "built once, adopted by whoever wants it next"
 * precedent every other registry in this monorepo follows — e.g.
 * `WelcomeWidgetProvider`, `LogActionHandler`). Organizations rather than
 * a business entity, since `kontor/api` depends only on `kontor/core` —
 * business components register their own resources (contacts, invoices,
 * etc. from kontor.md#20.10) when they choose to, without this package
 * ever depending on them.
 *
 * `OrganizationRepository` has no `archive()`/create-another-tenant
 * concept in this substage (kontor.md#4.2's multi-company readiness
 * doesn't extend to organization lifecycle management via API yet), so
 * `create()`/`delete()` are deliberately unsupported here rather than
 * faked.
 */
final class OrganizationResource implements ApiResourceInterface
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function key(): string
    {
        return 'organizations';
    }

    public function schema(): ApiResourceSchema
    {
        return new ApiResourceSchema(
            fields: [
                'uid' => 'string',
                'name' => 'string',
                'legalName' => 'string',
                'countryCode' => 'string',
                'defaultLanguage' => 'string',
                'defaultCurrency' => 'string',
                'timezone' => 'string',
                'status' => 'string',
            ],
            filterableFields: ['status'],
            sortableFields: ['name'],
            supportsCreate: false,
            supportsDelete: false,
        );
    }

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
    {
        // Single-tenant scope: a caller only ever sees its own organization.
        $organization = $this->organizations->find($organizationId);
        $rows = $organization !== null ? [$this->present($organization)] : [];

        return new ApiCollectionResult($rows, count($rows));
    }

    public function find(string $organizationId, string $uid): ?array
    {
        if ($uid !== $organizationId) {
            return null;
        }

        $organization = $this->organizations->find($uid);

        return $organization !== null ? $this->present($organization) : null;
    }

    public function create(string $organizationId, array $attributes): array
    {
        throw UnsupportedResourceOperationException::forResource($this->key(), 'create');
    }

    public function update(string $organizationId, string $uid, array $attributes): array
    {
        if ($uid !== $organizationId) {
            throw new \RuntimeException("Organization \"{$uid}\" was not found.");
        }

        $organization = $this->organizations->require($uid);

        if (array_key_exists('name', $attributes)) {
            $organization->name = (string) $attributes['name'];
        }

        if (array_key_exists('legalName', $attributes)) {
            $organization->legalName = $attributes['legalName'] !== null ? (string) $attributes['legalName'] : null;
        }

        if (array_key_exists('timezone', $attributes)) {
            $organization->timezone = (string) $attributes['timezone'];
        }

        $this->organizations->save($organization);

        return $this->present($organization);
    }

    public function delete(string $organizationId, string $uid): void
    {
        throw UnsupportedResourceOperationException::forResource($this->key(), 'delete');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Organization $organization): array
    {
        return [
            'uid' => $organization->uid->toString(),
            'name' => $organization->name,
            'legalName' => $organization->legalName,
            'countryCode' => $organization->countryCode,
            'defaultLanguage' => $organization->defaultLanguage,
            'defaultCurrency' => $organization->defaultCurrency,
            'timezone' => $organization->timezone,
            'status' => $organization->status,
        ];
    }
}
