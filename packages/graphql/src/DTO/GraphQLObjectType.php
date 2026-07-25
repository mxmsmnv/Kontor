<?php

declare(strict_types=1);

namespace Kontor\GraphQL\DTO;

/**
 * The "component types" milestone's output for a single resource — a
 * business component's `ApiResourceInterface` (kontor.md's `kontor/api`)
 * translated into a GraphQL object type by `SchemaRegistry`/
 * `GraphQLTypeMapper`.
 */
final class GraphQLObjectType
{
    /**
     * @param array<string, string> $fields field name => GraphQL scalar type
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
    ) {
    }
}
