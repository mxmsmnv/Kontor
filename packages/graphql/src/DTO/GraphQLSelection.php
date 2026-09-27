<?php

declare(strict_types=1);

namespace Kontor\GraphQL\DTO;

/**
 * One root-level `resourceKey(arguments) { fields }` selection parsed
 * from a query document. `uid` (string) selects a single record via
 * `ApiResourceInterface::find()`; `page`/`pageSize` (int) select a list
 * via `list()`. Any other argument name is a parse error in this
 * substage — see `GraphQLQueryParser`'s own doc comment for why
 * arbitrary filter arguments aren't supported yet.
 */
final class GraphQLSelection
{
    /**
     * @param array<string, string|int|bool> $arguments
     * @param string[] $fields
     */
    public function __construct(
        public readonly string $resourceKey,
        public readonly array $arguments,
        public readonly array $fields,
    ) {
    }
}
