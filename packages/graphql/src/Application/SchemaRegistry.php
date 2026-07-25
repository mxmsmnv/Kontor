<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\GraphQL\DTO\GraphQLObjectType;

/**
 * The "schema registry" milestone. Deliberately not a second, parallel
 * registry business components have to opt into separately — it builds
 * the GraphQL schema straight from whatever `kontor/api`'s own
 * `ApiResourceRegistry` already has registered, so a resource registered
 * once is queryable through both REST and GraphQL. Pure — no I/O of its
 * own.
 */
final class SchemaRegistry
{
    public function __construct(
        private readonly ApiResourceRegistry $resources,
        private readonly GraphQLTypeMapper $typeMapper = new GraphQLTypeMapper(),
    ) {
    }

    /**
     * @return array<string, GraphQLObjectType> resource key => object type
     */
    public function objectTypes(): array
    {
        $types = [];

        foreach ($this->resources->all() as $resource) {
            $types[$resource->key()] = new GraphQLObjectType(
                name: $this->typeName($resource->key()),
                fields: $this->typeMapper->objectFields($resource->schema()),
            );
        }

        return $types;
    }

    public function typeName(string $resourceKey): string
    {
        return ucfirst($this->singularize($resourceKey));
    }

    /**
     * A minimal SDL-like rendering of the whole schema, for
     * introspection/documentation — the query engine itself
     * (`GraphQLExecutor`) resolves directly against `ApiResourceRegistry`
     * and a parsed query, not against this string.
     */
    public function toSdl(): string
    {
        $lines = [];

        foreach ($this->objectTypes() as $type) {
            $lines[] = "type {$type->name} {";

            foreach ($type->fields as $field => $gqlType) {
                $lines[] = "  {$field}: {$gqlType}";
            }

            $lines[] = '}';
            $lines[] = '';
        }

        $lines[] = 'type Query {';

        foreach ($this->objectTypes() as $resourceKey => $type) {
            $lines[] = "  {$resourceKey}(uid: String, page: Int, pageSize: Int): [{$type->name}]";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * A naive English-plural heuristic (strip a trailing "s", or "ies" →
     * "y") — good enough for this substage's demonstrator resource
     * (`organizations` → `Organization`) and any resource key a business
     * component names in the same plural-noun style kontor.md#20.10 uses
     * throughout (`contacts`, `invoices`, `quotations`, …). Not a general
     * solution for irregular plurals.
     */
    private function singularize(string $key): string
    {
        if (str_ends_with($key, 'ies')) {
            return substr($key, 0, -3).'y';
        }

        if (str_ends_with($key, 's') && !str_ends_with($key, 'ss')) {
            return substr($key, 0, -1);
        }

        return $key;
    }
}
