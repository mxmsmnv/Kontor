<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\GraphQL\DTO\GraphQLDocument;

/**
 * The "complexity limits" milestone: a query requesting many fields
 * across a large page size is more expensive than one requesting a
 * single record, so complexity is `fieldCount * rowMultiplier` summed
 * across every selection — a single-record selection (`uid` argument)
 * has a multiplier of 1; a list selection's multiplier is its
 * `pageSize` (defaulting to the same 50 `kontor/api`'s own
 * `RequestQueryParser` defaults to). Pure.
 */
final class GraphQLComplexityCalculator
{
    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly int $maxComplexity = 1000,
    ) {
    }

    public function complexityOf(GraphQLDocument $document): int
    {
        $total = 0;

        foreach ($document->selections as $selection) {
            $isSingleRecord = isset($selection->arguments['uid']);
            $multiplier = $isSingleRecord ? 1 : (int) ($selection->arguments['pageSize'] ?? self::DEFAULT_PAGE_SIZE);

            $total += count($selection->fields) * max(1, $multiplier);
        }

        return $total;
    }

    public function exceedsLimit(GraphQLDocument $document): bool
    {
        return $this->complexityOf($document) > $this->maxComplexity;
    }

    public function maxComplexity(): int
    {
        return $this->maxComplexity;
    }
}
