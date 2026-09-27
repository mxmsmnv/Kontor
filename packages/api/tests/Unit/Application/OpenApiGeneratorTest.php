<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\OpenApiGenerator;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PHPUnit\Framework\TestCase;

final class OpenApiGeneratorTest extends TestCase
{
    public function test_generates_paths_and_schema_for_every_registered_resource(): void
    {
        $registry = new ApiResourceRegistry();
        $registry->register(new OrganizationResource(new OrganizationRepository(new FakePdoForOpenApiTest())));

        $document = (new OpenApiGenerator($registry))->generate();

        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertArrayHasKey('/organizations', $document['paths']);
        $this->assertArrayHasKey('/organizations/{uid}', $document['paths']);
        $this->assertArrayHasKey('get', $document['paths']['/organizations']);
        $this->assertArrayHasKey('get', $document['paths']['/organizations/{uid}']);
        $this->assertArrayHasKey('patch', $document['paths']['/organizations/{uid}']);
        $this->assertArrayHasKey('Organizations', $document['components']['schemas']);
    }

    public function test_unsupported_operations_are_omitted_from_the_paths(): void
    {
        $registry = new ApiResourceRegistry();
        $registry->register(new OrganizationResource(new OrganizationRepository(new FakePdoForOpenApiTest())));

        $document = (new OpenApiGenerator($registry))->generate();

        // organizations does not support create or delete (see OrganizationResource).
        $this->assertArrayNotHasKey('post', $document['paths']['/organizations']);
        $this->assertArrayNotHasKey('delete', $document['paths']['/organizations/{uid}']);
    }

    public function test_empty_registry_produces_an_empty_but_valid_document(): void
    {
        $document = (new OpenApiGenerator(new ApiResourceRegistry()))->generate();

        $this->assertSame([], $document['paths']);
        $this->assertSame([], $document['components']['schemas']);
    }
}

final class FakePdoForOpenApiTest extends \PDO
{
    public function __construct()
    {
    }
}
