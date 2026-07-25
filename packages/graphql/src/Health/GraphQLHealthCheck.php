<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Health;

use Kontor\GraphQL\Application\SchemaRegistry;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * This package has no database table of its own — nothing to persist —
 * so, like `kontor/cache`, there's no row count to check. Instead this
 * checks a real invariant: `SchemaRegistry`'s naive singularization
 * (see its own doc comment) can make two distinct resource keys collide
 * on the same GraphQL type name (e.g. `company` and `companies` would
 * both become `Company`) — worth surfacing before a client sees a
 * confusing schema, the same "check for a real anomaly, not just a
 * count" approach `kontor/entities`'/`kontor/api`'s own health checks
 * use.
 */
final class GraphQLHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly SchemaRegistry $schema,
    ) {
    }

    public function key(): string
    {
        return 'graphql';
    }

    public function run(): HealthCheckResult
    {
        $typeNameOwners = [];
        $collisions = [];

        foreach ($this->schema->objectTypes() as $resourceKey => $type) {
            if (isset($typeNameOwners[$type->name])) {
                $collisions[] = "{$type->name} ({$typeNameOwners[$type->name]} vs {$resourceKey})";

                continue;
            }

            $typeNameOwners[$type->name] = $resourceKey;
        }

        if ($collisions === []) {
            return new HealthCheckResult(
                'ok',
                count($typeNameOwners).' GraphQL type(s) registered, no name collisions.',
                ['types' => count($typeNameOwners)],
            );
        }

        return new HealthCheckResult(
            'warning',
            'GraphQL type name collision(s): '.implode(', ', $collisions).'.',
            ['collisions' => $collisions],
        );
    }
}
