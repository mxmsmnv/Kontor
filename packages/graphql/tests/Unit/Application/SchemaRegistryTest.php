<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\GraphQL\Application\SchemaRegistry;
use PHPUnit\Framework\TestCase;

final class SchemaRegistryTest extends TestCase
{
    public function test_singularizes_a_plain_plural_resource_key(): void
    {
        $schema = new SchemaRegistry(new ApiResourceRegistry());

        $this->assertSame('Organization', $schema->typeName('organizations'));
    }

    public function test_singularizes_an_ies_plural(): void
    {
        $schema = new SchemaRegistry(new ApiResourceRegistry());

        $this->assertSame('Category', $schema->typeName('categories'));
    }

    public function test_does_not_mangle_a_word_ending_in_double_s(): void
    {
        $schema = new SchemaRegistry(new ApiResourceRegistry());

        $this->assertSame('Address', $schema->typeName('address'));
    }

    public function test_object_types_reflects_every_registered_resource(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations'));
        $resources->register(new FakeApiResource('widgets'));

        $types = (new SchemaRegistry($resources))->objectTypes();

        $this->assertArrayHasKey('organizations', $types);
        $this->assertArrayHasKey('widgets', $types);
        $this->assertSame('Organization', $types['organizations']->name);
        $this->assertSame('Widget', $types['widgets']->name);
        $this->assertSame(['uid' => 'String', 'name' => 'String'], $types['organizations']->fields);
    }

    public function test_sdl_includes_a_type_and_query_root_per_resource(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations'));

        $sdl = (new SchemaRegistry($resources))->toSdl();

        $this->assertStringContainsString('type Organization {', $sdl);
        $this->assertStringContainsString('type Query {', $sdl);
        $this->assertStringContainsString('organizations(uid: String, page: Int, pageSize: Int): [Organization]', $sdl);
    }
}
