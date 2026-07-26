<?php

declare(strict_types=1);

namespace Kontor\Search\Application;

use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use Kontor\SDK\Contracts\CacheInterface;
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
    public function __construct(
        private readonly SearchProviderRegistry $registry,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtlSeconds = 30,
    ) {
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
        if ($this->cache === null) {
            return $this->searchUncached($query);
        }

        $entityTypes = array_values(array_unique($query->entityTypes));
        sort($entityTypes);
        $key = 'query:' . hash('sha256', json_encode([
            'organizationId' => $query->organizationId,
            'term' => $query->term,
            'entityTypes' => $entityTypes,
            'limit' => $query->limit,
            'offset' => $query->offset,
        ], JSON_THROW_ON_ERROR));
        $snapshot = $this->cache->remember(
            $key,
            fn (): array => $this->snapshot($this->searchUncached($query)),
            $this->cacheTtlSeconds,
            ['results'],
        );

        $result = $this->fromSnapshot($snapshot);
        if ($result === null) {
            $this->cache->delete($key, ['results']);

            return $this->searchUncached($query);
        }

        return $result;
    }

    /**
     * WireCache accepts scalar/array values, not arbitrary DTO objects.
     *
     * @return array{hits: array<int, array<string, mixed>>, total: int}
     */
    private function snapshot(SearchResult $result): array
    {
        return [
            'hits' => array_map(static fn (SearchHit $hit): array => [
                'entityType' => $hit->entityType,
                'entityUid' => $hit->entityUid,
                'title' => $hit->title,
                'subtitle' => $hit->subtitle,
                'url' => $hit->url,
                'score' => $hit->score,
            ], $result->hits),
            'total' => $result->total,
        ];
    }

    private function fromSnapshot(mixed $snapshot): ?SearchResult
    {
        if (!is_array($snapshot)
            || !isset($snapshot['hits'], $snapshot['total'])
            || !is_array($snapshot['hits'])
            || !is_int($snapshot['total'])) {
            return null;
        }

        $hits = [];
        foreach ($snapshot['hits'] as $hit) {
            if (!is_array($hit)
                || !is_string($hit['entityType'] ?? null)
                || !is_string($hit['entityUid'] ?? null)
                || !is_string($hit['title'] ?? null)
                || !is_numeric($hit['score'] ?? null)) {
                return null;
            }
            $hits[] = new SearchHit(
                entityType: $hit['entityType'],
                entityUid: $hit['entityUid'],
                title: $hit['title'],
                subtitle: is_string($hit['subtitle'] ?? null) ? $hit['subtitle'] : null,
                url: is_string($hit['url'] ?? null) ? $hit['url'] : null,
                score: (float) $hit['score'],
            );
        }

        return new SearchResult($hits, $snapshot['total']);
    }

    private function searchUncached(SearchQuery $query): SearchResult
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
