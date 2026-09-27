<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\API\DTO\ApiResourceSchema;
use Kontor\GraphQL\Application\GraphQLTypeMapper;
use PHPUnit\Framework\TestCase;

final class GraphQLTypeMapperTest extends TestCase
{
    public function test_known_scalar_types_are_mapped(): void
    {
        $mapper = new GraphQLTypeMapper();

        $this->assertSame('String', $mapper->scalarType('string'));
        $this->assertSame('Int', $mapper->scalarType('int'));
        $this->assertSame('Float', $mapper->scalarType('decimal'));
        $this->assertSame('Boolean', $mapper->scalarType('bool'));
        $this->assertSame('String', $mapper->scalarType('date'));
        $this->assertSame('String', $mapper->scalarType('datetime'));
        $this->assertSame('Int', $mapper->scalarType('money'));
        $this->assertSame('[String]', $mapper->scalarType('array'));
    }

    public function test_an_unknown_type_falls_back_to_string(): void
    {
        $mapper = new GraphQLTypeMapper();

        $this->assertSame('String', $mapper->scalarType('something-unknown'));
    }

    public function test_object_fields_maps_every_schema_field(): void
    {
        $mapper = new GraphQLTypeMapper();
        $schema = new ApiResourceSchema(fields: ['uid' => 'string', 'total_minor' => 'money']);

        $this->assertSame(['uid' => 'String', 'total_minor' => 'Int'], $mapper->objectFields($schema));
    }
}
