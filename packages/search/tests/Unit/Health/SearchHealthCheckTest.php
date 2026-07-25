<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Health;

use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\Search\Health\SearchHealthCheck;
use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use PHPUnit\Framework\TestCase;

final class SearchHealthCheckTest extends TestCase
{
    public function test_ok_when_search_responds(): void
    {
        $service = new GlobalSearchService(new SearchProviderRegistry());

        $result = (new SearchHealthCheck($service, 'org_01'))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_critical_when_a_provider_throws(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new class implements SearchProviderInterface {
            public function name(): string
            {
                return 'broken';
            }

            public function supports(string $entityType): bool
            {
                return true;
            }

            public function search(SearchQuery $query): SearchResult
            {
                throw new \RuntimeException('index unreachable');
            }
        });

        $result = (new SearchHealthCheck(new GlobalSearchService($registry), 'org_01'))->run();

        $this->assertSame('critical', $result->status);
        $this->assertStringContainsString('index unreachable', $result->message);
    }
}
