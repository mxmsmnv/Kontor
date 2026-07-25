<?php

declare(strict_types=1);

namespace Kontor\Search\Application;

use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

/**
 * Substage 2.4 "global search UI" / "command palette" backend: fans a
 * SearchQuery out to every registered provider that matches
 * $query->entityTypes (or all providers, when empty — Ctrl+K-style search
 * across entities, settings and components at once, per kontor.md
 * section 28), merges results by score, and re-paginates the merged set.
 *
 * Implements SearchProviderInterface itself so it can be registered as the
 * "search" capability the same way every other capability maps to its own
 * SDK contract (queue -> QueueInterface, storage -> StorageInterface, ...).
 */
final class GlobalSearchService implements SearchProviderInterface
{
    public function __construct(private readonly SearchProviderRegistry $registry)
    {
    }

    public function name(): string
    {
        return 'global';
    }

    public function supports(string $entityType): bool
    {
        foreach ($this->registry->all() as $provider) {
            if ($provider->supports($entityType)) {
                return true;
            }
        }

        return false;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $providers = $this->registry->forEntityTypes($query->entityTypes);

        if ($providers === []) {
            return new SearchResult([], 0);
        }

        // Each provider needs enough candidates from its own ranking to
        // survive the merged re-sort; request offset+limit from each and
        // let the merge step take the true top $limit starting at $offset.
        $perProviderQuery = new SearchQuery(
            organizationId: $query->organizationId,
            term: $query->term,
            entityTypes: $query->entityTypes,
            limit: $query->limit + $query->offset,
            offset: 0,
        );

        $hits = [];
        $total = 0;

        foreach ($providers as $provider) {
            $result = $provider->search($perProviderQuery);
            array_push($hits, ...$result->hits);
            $total += $result->total;
        }

        usort($hits, static fn (SearchHit $a, SearchHit $b): int => $b->score <=> $a->score);

        return new SearchResult(array_slice($hits, $query->offset, $query->limit), $total);
    }
}
