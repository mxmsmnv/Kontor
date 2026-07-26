<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Application;

use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use Kontor\Search\Tests\Support\FakeSearchProvider;
use Kontor\Search\Tests\Support\RecordingCache;
use PHPUnit\Framework\TestCase;

final class GlobalSearchServiceTest extends TestCase
{
    private function hit(string $entityType, string $uid, float $score): SearchHit
    {
        return new SearchHit(entityType: $entityType, entityUid: $uid, title: $uid, score: $score);
    }

    public function test_merges_hits_from_multiple_providers_sorted_by_score(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', [
            $this->hit('contact', 'c1', 1.0),
        ]));
        $registry->register(new FakeSearchProvider('deals', 'deal', [
            $this->hit('deal', 'd1', 5.0),
        ]));

        $result = (new GlobalSearchService($registry))->search(new SearchQuery('org_01', 'acme'));

        $this->assertSame(['d1', 'c1'], array_map(static fn (SearchHit $h): string => $h->entityUid, $result->hits));
        $this->assertSame(2, $result->total);
    }

    public function test_entity_types_filter_restricts_which_providers_are_queried(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', [$this->hit('contact', 'c1', 1.0)]));
        $registry->register(new FakeSearchProvider('deals', 'deal', [$this->hit('deal', 'd1', 1.0)]));

        $result = (new GlobalSearchService($registry))->search(new SearchQuery('org_01', 'acme', entityTypes: ['deal']));

        $this->assertCount(1, $result->hits);
        $this->assertSame('deal', $result->hits[0]->entityType);
    }

    public function test_pagination_applies_to_the_merged_result_not_per_provider(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', [
            $this->hit('contact', 'c1', 3.0),
            $this->hit('contact', 'c2', 1.0),
        ]));
        $registry->register(new FakeSearchProvider('deals', 'deal', [
            $this->hit('deal', 'd1', 2.0),
        ]));

        $result = (new GlobalSearchService($registry))->search(new SearchQuery('org_01', 'acme', limit: 2, offset: 1));

        // full ranked order is c1(3), d1(2), c2(1) — offset 1, limit 2 => d1, c2
        $this->assertSame(['d1', 'c2'], array_map(static fn (SearchHit $h): string => $h->entityUid, $result->hits));
        $this->assertSame(3, $result->total);
    }

    public function test_no_matching_providers_returns_an_empty_result(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', [$this->hit('contact', 'c1', 1.0)]));

        $result = (new GlobalSearchService($registry))->search(new SearchQuery('org_01', 'acme', entityTypes: ['invoice']));

        $this->assertSame([], $result->hits);
        $this->assertSame(0, $result->total);
    }

    public function test_supports_reflects_any_registered_provider(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', []));
        $service = new GlobalSearchService($registry);

        $this->assertTrue($service->supports('contact'));
        $this->assertFalse($service->supports('invoice'));
    }

    public function test_repeated_query_is_served_from_cache_without_calling_provider_again(): void
    {
        $registry = new SearchProviderRegistry();
        $provider = new FakeSearchProvider('contacts', 'contact', [
            $this->hit('contact', 'c1', 1.0),
        ]);
        $registry->register($provider);
        $cache = new RecordingCache();
        $service = new GlobalSearchService($registry, $cache);
        $query = new SearchQuery('org_01', 'acme', entityTypes: ['contact']);

        $first = $service->search($query);
        $second = $service->search($query);

        $this->assertEquals($first, $second);
        $this->assertSame(1, $provider->searchCalls);
        $this->assertSame(1, $cache->rememberMisses);
    }

    public function test_cache_key_is_scoped_by_organization_and_pagination(): void
    {
        $registry = new SearchProviderRegistry();
        $provider = new FakeSearchProvider('contacts', 'contact', [
            $this->hit('contact', 'c1', 1.0),
        ]);
        $registry->register($provider);
        $service = new GlobalSearchService($registry, new RecordingCache());

        $service->search(new SearchQuery('org_01', 'acme', limit: 10));
        $service->search(new SearchQuery('org_02', 'acme', limit: 10));
        $service->search(new SearchQuery('org_01', 'acme', limit: 5));

        $this->assertSame(3, $provider->searchCalls);
    }
}
