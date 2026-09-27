<?php

declare(strict_types=1);

namespace Kontor\Search\Health;

use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;
use Kontor\SDK\DTO\SearchQuery;

/**
 * Proves the search aggregation pipeline actually runs, by executing a
 * real (empty-term) query rather than just checking providers are
 * registered.
 */
final class SearchHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly GlobalSearchService $search,
        private readonly string $organizationId,
    ) {
    }

    public function key(): string
    {
        return 'search';
    }

    public function run(): HealthCheckResult
    {
        try {
            $this->search->search(new SearchQuery($this->organizationId, ''));

            return new HealthCheckResult('ok', 'Search is responding.');
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Search failed: {$e->getMessage()}");
        }
    }
}
