<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Infrastructure\Search;

use Kontor\SDK\DTO\SearchQuery;
use Kontor\Search\Infrastructure\Search\ComponentsSearchProvider;
use Kontor\Search\Tests\Support\InMemoryComponentRegistry;
use PHPUnit\Framework\TestCase;

final class ComponentsSearchProviderTest extends TestCase
{
    private function registryWith(string ...$names): InMemoryComponentRegistry
    {
        $registry = new InMemoryComponentRegistry();

        foreach ($names as $name) {
            $registry->markInstalled($name, '1.0.0');
            $registry->enable($name);
        }

        return $registry;
    }

    public function test_supports_only_component_entity_type(): void
    {
        $provider = new ComponentsSearchProvider($this->registryWith());

        $this->assertTrue($provider->supports('component'));
        $this->assertFalse($provider->supports('contact'));
    }

    public function test_empty_term_returns_every_component(): void
    {
        $provider = new ComponentsSearchProvider($this->registryWith('KontorCRM', 'KontorSales'));

        $result = $provider->search(new SearchQuery('org_01', ''));

        $this->assertSame(2, $result->total);
    }

    public function test_matches_by_case_insensitive_substring(): void
    {
        $provider = new ComponentsSearchProvider($this->registryWith('KontorCRM', 'KontorSales'));

        $result = $provider->search(new SearchQuery('org_01', 'crm'));

        $this->assertCount(1, $result->hits);
        $this->assertSame('KontorCRM', $result->hits[0]->entityUid);
    }

    public function test_a_prefix_match_ranks_above_a_substring_match(): void
    {
        $provider = new ComponentsSearchProvider($this->registryWith('KontorCRM', 'CRMLegacyBridge'));

        $result = $provider->search(new SearchQuery('org_01', 'crm'));

        $this->assertSame('CRMLegacyBridge', $result->hits[0]->entityUid);
    }

    public function test_no_match_returns_empty_result(): void
    {
        $provider = new ComponentsSearchProvider($this->registryWith('KontorCRM'));

        $result = $provider->search(new SearchQuery('org_01', 'nonexistent'));

        $this->assertSame([], $result->hits);
        $this->assertSame(0, $result->total);
    }
}
