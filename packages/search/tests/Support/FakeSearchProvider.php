<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Support;

use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

final class FakeSearchProvider implements SearchProviderInterface
{
    public int $searchCalls = 0;

    /**
     * @param SearchHit[] $hits
     */
    public function __construct(
        private readonly string $providerName,
        private readonly string $entityType,
        private readonly array $hits,
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function supports(string $entityType): bool
    {
        return $entityType === $this->entityType;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->searchCalls++;

        return new SearchResult(array_slice($this->hits, $query->offset, $query->limit), count($this->hits));
    }
}
