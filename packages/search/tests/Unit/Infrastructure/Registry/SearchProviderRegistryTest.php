<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Unit\Infrastructure\Registry;

use Kontor\Search\Infrastructure\Registry\SearchProviderRegistry;
use Kontor\Search\Tests\Support\FakeSearchProvider;
use PHPUnit\Framework\TestCase;

final class SearchProviderRegistryTest extends TestCase
{
    public function test_all_returns_every_registered_provider(): void
    {
        $registry = new SearchProviderRegistry();
        $contacts = new FakeSearchProvider('contacts', 'contact', []);
        $deals = new FakeSearchProvider('deals', 'deal', []);

        $registry->register($contacts);
        $registry->register($deals);

        $this->assertSame([$contacts, $deals], $registry->all());
    }

    public function test_for_entity_types_returns_only_matching_providers(): void
    {
        $registry = new SearchProviderRegistry();
        $contacts = new FakeSearchProvider('contacts', 'contact', []);
        $deals = new FakeSearchProvider('deals', 'deal', []);
        $registry->register($contacts);
        $registry->register($deals);

        $this->assertSame([$contacts], $registry->forEntityTypes(['contact']));
    }

    public function test_for_entity_types_returns_everything_when_empty(): void
    {
        $registry = new SearchProviderRegistry();
        $contacts = new FakeSearchProvider('contacts', 'contact', []);
        $deals = new FakeSearchProvider('deals', 'deal', []);
        $registry->register($contacts);
        $registry->register($deals);

        $this->assertSame([$contacts, $deals], $registry->forEntityTypes([]));
    }

    public function test_for_entity_types_matches_any_of_several_requested_types(): void
    {
        $registry = new SearchProviderRegistry();
        $contacts = new FakeSearchProvider('contacts', 'contact', []);
        $deals = new FakeSearchProvider('deals', 'deal', []);
        $registry->register($contacts);
        $registry->register($deals);

        $result = $registry->forEntityTypes(['deal', 'invoice']);

        $this->assertSame([$deals], $result);
    }

    public function test_unknown_entity_type_matches_nothing(): void
    {
        $registry = new SearchProviderRegistry();
        $registry->register(new FakeSearchProvider('contacts', 'contact', []));

        $this->assertSame([], $registry->forEntityTypes(['invoice']));
    }
}
