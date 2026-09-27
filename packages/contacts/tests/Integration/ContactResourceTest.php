<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\API\DTO\ApiQuery;
use Kontor\Contacts\Infrastructure\API\ContactResource;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class ContactResourceTest extends DatabaseTestCase
{
    private ContactResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = new ContactResource(
            new ContactRepository($this->pdo, new OrganizationRepository($this->pdo)),
        );
    }

    public function test_contact_crud_is_organization_scoped_and_soft_deleted(): void
    {
        $created = $this->resource->create($this->organizationUid, [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ADA@EXAMPLE.TEST',
            'source' => 'api',
        ]);

        $this->assertSame('contacts', $this->resource->key());
        $this->assertSame('Ada Lovelace', $created['displayName']);
        $this->assertSame('ada@example.test', $created['email']);
        $this->assertSame($created, $this->resource->find($this->organizationUid, $created['uid']));

        $updated = $this->resource->update($this->organizationUid, $created['uid'], [
            'firstName' => 'Augusta Ada',
            'status' => 'inactive',
            'metadata' => ['externalId' => 'crm-42'],
        ]);
        $this->assertSame('Augusta Ada Lovelace', $updated['displayName']);
        $this->assertSame('inactive', $updated['status']);
        $this->assertSame(['externalId' => 'crm-42'], $updated['metadata']);

        $this->resource->delete($this->organizationUid, $created['uid']);
        $this->assertNull($this->resource->find($this->organizationUid, $created['uid']));
    }

    public function test_list_applies_api_pagination_query_and_status_filters(): void
    {
        $this->resource->create($this->organizationUid, [
            'displayName' => 'Alpha Active',
            'status' => 'active',
        ]);
        $this->resource->create($this->organizationUid, [
            'displayName' => 'Beta Inactive',
            'status' => 'inactive',
        ]);
        $this->resource->create($this->organizationUid, [
            'displayName' => 'Gamma Inactive',
            'status' => 'inactive',
        ]);

        $page = $this->resource->list($this->organizationUid, new ApiQuery(
            page: 2,
            pageSize: 1,
            filters: ['status' => 'inactive'],
        ));

        $this->assertSame(2, $page->total);
        $this->assertCount(1, $page->rows);
        $this->assertSame('inactive', $page->rows[0]['status']);

        $query = $this->resource->list($this->organizationUid, new ApiQuery(
            filters: ['query' => 'Alpha'],
        ));
        $this->assertSame(1, $query->total);
        $this->assertSame('Alpha Active', $query->rows[0]['displayName']);
    }
}
