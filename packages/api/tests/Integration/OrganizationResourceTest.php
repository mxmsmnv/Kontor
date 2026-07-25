<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Integration;

use Kontor\API\DTO\ApiQuery;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;

final class OrganizationResourceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [new Migration0001CreateOrganizationsTable()];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_organizations', 'kontor_migrations'];
    }

    public function test_list_returns_only_the_callers_own_organization(): void
    {
        $resource = new OrganizationResource(new OrganizationRepository($this->pdo));

        $result = $resource->list($this->organizationUid, new ApiQuery());

        $this->assertSame(1, $result->total);
        $this->assertSame($this->organizationUid, $result->rows[0]['uid']);
    }

    public function test_find_by_own_uid(): void
    {
        $resource = new OrganizationResource(new OrganizationRepository($this->pdo));

        $row = $resource->find($this->organizationUid, $this->organizationUid);

        $this->assertNotNull($row);
        $this->assertSame('Default organization', $row['name']);
    }

    public function test_find_a_different_uid_returns_null(): void
    {
        $resource = new OrganizationResource(new OrganizationRepository($this->pdo));

        $this->assertNull($resource->find($this->organizationUid, 'not_this_org'));
    }

    public function test_update_renames_the_organization(): void
    {
        $resource = new OrganizationResource(new OrganizationRepository($this->pdo));

        $updated = $resource->update($this->organizationUid, $this->organizationUid, ['name' => 'Acme Corp']);

        $this->assertSame('Acme Corp', $updated['name']);
        $this->assertSame('Acme Corp', $resource->find($this->organizationUid, $this->organizationUid)['name']);
    }
}
