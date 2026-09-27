<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Search;

use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

/**
 * "Components search" (kontor.md section 28) — searches installed
 * components by name via Core's ComponentRegistry. A tiny, in-memory
 * dataset (dozens of rows at most), so a plain substring match is the
 * right tool, not MySQL FULLTEXT.
 */
final class ComponentsSearchProvider implements SearchProviderInterface
{
    public function __construct(private readonly ComponentRegistryInterface $components)
    {
    }

    public function name(): string
    {
        return 'components';
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'component';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $term = mb_strtolower(trim($query->term));
        $hits = [];

        foreach ($this->components->all() as $component) {
            $name = (string) $component['name'];
            $haystack = mb_strtolower($name);

            if ($term !== '' && !str_contains($haystack, $term)) {
                continue;
            }

            $hits[] = new SearchHit(
                entityType: 'component',
                entityUid: $name,
                title: $name,
                subtitle: (string) $component['status'],
                score: $term !== '' && str_starts_with($haystack, $term) ? 2.0 : 1.0,
            );
        }

        usort($hits, static fn (SearchHit $a, SearchHit $b): int => $b->score <=> $a->score);

        $total = count($hits);

        return new SearchResult(array_slice($hits, $query->offset, $query->limit), $total);
    }
}
