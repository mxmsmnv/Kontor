<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Health;

use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\GraphQL\Application\SchemaRegistry;
use Kontor\GraphQL\Health\GraphQLHealthCheck;
use Kontor\GraphQL\Tests\Unit\Application\FakeApiResource;
use PHPUnit\Framework\TestCase;

final class GraphQLHealthCheckTest extends TestCase
{
    public function test_ok_when_every_type_name_is_unique(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations'));
        $resources->register(new FakeApiResource('widgets'));

        $result = (new GraphQLHealthCheck(new SchemaRegistry($resources)))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(2, $result->details['types']);
    }

    public function test_warns_on_a_type_name_collision(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('company'));
        $resources->register(new FakeApiResource('companies'));

        $result = (new GraphQLHealthCheck(new SchemaRegistry($resources)))->run();

        $this->assertSame('warning', $result->status);
        $this->assertNotEmpty($result->details['collisions']);
    }
}
