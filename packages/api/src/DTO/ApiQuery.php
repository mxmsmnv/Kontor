<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

/**
 * The parsed form of kontor.md#20.4-20.8's query parameters
 * (`page[number]`/`page[size]`, `filter[x]`, `sort`, `fields[resource]`,
 * `include`) — see `RequestQueryParser`.
 */
final class ApiQuery
{
    /**
     * @param array<string, string> $filters
     * @param list<array{field: string, direction: string}> $sort
     * @param string[]|null $fields null means "all fields" (no sparse projection requested)
     * @param string[] $include
     */
    public function __construct(
        public readonly int $page = 1,
        public readonly int $pageSize = 50,
        public readonly array $filters = [],
        public readonly array $sort = [],
        public readonly ?array $fields = null,
        public readonly array $include = [],
    ) {
    }
}
