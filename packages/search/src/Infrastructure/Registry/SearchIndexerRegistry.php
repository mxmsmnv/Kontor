<?php

declare(strict_types=1);

namespace Kontor\Search\Infrastructure\Registry;

use Kontor\Search\SearchIndexerInterface;

final class SearchIndexerRegistry
{
    /**
     * @var list<SearchIndexerInterface>
     */
    private array $indexers = [];

    public function register(SearchIndexerInterface $indexer): void
    {
        $this->indexers[] = $indexer;
    }

    /**
     * @return list<SearchIndexerInterface>
     */
    public function forEntityType(string $entityType): array
    {
        return array_values(array_filter(
            $this->indexers,
            static fn (SearchIndexerInterface $indexer): bool => $indexer->supports($entityType)
        ));
    }
}
