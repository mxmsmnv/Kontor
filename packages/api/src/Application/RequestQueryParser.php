<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\DTO\ApiQuery;

/**
 * The "filtering" milestone (kontor.md#20.4-20.8): pagination, filtering,
 * sorting, sparse fields and relation-expansion query parameters. Pure —
 * takes the already-decoded query-string array PHP's own `parse_str()`
 * produces for bracketed parameters (`filter[status]=paid` becomes
 * `['filter' => ['status' => 'paid']]`), so it has no HTTP dependency and
 * runs as a real (non-skipped) unit test.
 */
final class RequestQueryParser
{
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 200;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function parse(array $queryParams, string $resourceKey): ApiQuery
    {
        $page = max(1, (int) ($queryParams['page']['number'] ?? 1));
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) ($queryParams['page']['size'] ?? self::DEFAULT_PAGE_SIZE)));

        $filters = [];
        foreach ((array) ($queryParams['filter'] ?? []) as $field => $value) {
            $filters[(string) $field] = (string) $value;
        }

        $fieldsRaw = $queryParams['fields'][$resourceKey] ?? null;

        return new ApiQuery(
            page: $page,
            pageSize: $pageSize,
            filters: $filters,
            sort: $this->parseSort((string) ($queryParams['sort'] ?? '')),
            fields: $fieldsRaw !== null ? $this->splitCommaList((string) $fieldsRaw) : null,
            include: $this->splitCommaList((string) ($queryParams['include'] ?? '')),
        );
    }

    /**
     * @return list<array{field: string, direction: string}>
     */
    private function parseSort(string $raw): array
    {
        $sort = [];

        foreach ($this->splitCommaList($raw) as $field) {
            if (str_starts_with($field, '-')) {
                $sort[] = ['field' => substr($field, 1), 'direction' => 'desc'];
            } else {
                $sort[] = ['field' => $field, 'direction' => 'asc'];
            }
        }

        return $sort;
    }

    /**
     * @return list<string>
     */
    private function splitCommaList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v) => $v !== ''));
    }
}
