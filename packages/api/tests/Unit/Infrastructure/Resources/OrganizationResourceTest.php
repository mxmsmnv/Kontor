<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Infrastructure\Resources;

use Kontor\API\Application\UnsupportedResourceOperationException;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PHPUnit\Framework\TestCase;

final class OrganizationResourceTest extends TestCase
{
    private OrganizationResource $resource;

    protected function setUp(): void
    {
        // create()/delete() throw before ever touching the repository, so a
        // FakePdo (never actually queried) is enough here — list()/find()/
        // update() are covered by tests/Integration/OrganizationResourceTest.
        $this->resource = new OrganizationResource(new OrganizationRepository(new class extends \PDO {
            public function __construct()
            {
            }
        }));
    }

    public function test_key(): void
    {
        $this->assertSame('organizations', $this->resource->key());
    }

    public function test_schema_declares_create_and_delete_as_unsupported(): void
    {
        $schema = $this->resource->schema();

        $this->assertFalse($schema->supportsCreate);
        $this->assertTrue($schema->supportsUpdate);
        $this->assertFalse($schema->supportsDelete);
    }

    public function test_create_is_unsupported(): void
    {
        $this->expectException(UnsupportedResourceOperationException::class);

        $this->resource->create('org_1', []);
    }

    public function test_delete_is_unsupported(): void
    {
        $this->expectException(UnsupportedResourceOperationException::class);

        $this->resource->delete('org_1', 'org_1');
    }
}
