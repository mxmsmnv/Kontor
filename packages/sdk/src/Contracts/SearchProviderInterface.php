<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;

interface SearchProviderInterface
{
    public function name(): string;

    public function supports(string $entityType): bool;

    public function search(SearchQuery $query): SearchResult;
}
