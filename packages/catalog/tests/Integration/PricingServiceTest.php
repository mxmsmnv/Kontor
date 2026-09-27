<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Application\PricingService;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\SDK\ValueObjects\Money;

final class PricingServiceTest extends DatabaseTestCase
{
    public function test_resolves_the_highest_qualifying_quantity_tier(): void
    {
        $prices = new PriceRepository($this->pdo);
        $prices->save(new PriceListEntry('pl_01', 'item_01', Money::ofMinor(1000, 'EUR'), 1.0, null, null));
        $prices->save(new PriceListEntry('pl_01', 'item_01', Money::ofMinor(900, 'EUR'), 10.0, null, null));
        $prices->save(new PriceListEntry('pl_01', 'item_01', Money::ofMinor(800, 'EUR'), 100.0, null, null));

        $pricing = new PricingService($prices);

        $this->assertSame(1000, $pricing->resolvePrice('pl_01', 'item_01', 1.0)->amountMinor());
        $this->assertSame(900, $pricing->resolvePrice('pl_01', 'item_01', 15.0)->amountMinor());
        $this->assertSame(800, $pricing->resolvePrice('pl_01', 'item_01', 500.0)->amountMinor());
    }

    public function test_respects_the_validity_window(): void
    {
        $prices = new PriceRepository($this->pdo);
        $prices->save(new PriceListEntry(
            'pl_01', 'item_01', Money::ofMinor(500, 'EUR'), 1.0,
            new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-08-31'),
        ));

        $pricing = new PricingService($prices);

        $this->assertNull($pricing->resolvePrice('pl_01', 'item_01', 1.0, new \DateTimeImmutable('2026-01-01')));
        $this->assertSame(500, $pricing->resolvePrice('pl_01', 'item_01', 1.0, new \DateTimeImmutable('2026-07-01'))->amountMinor());
    }

    public function test_returns_null_when_no_entry_qualifies(): void
    {
        $prices = new PriceRepository($this->pdo);
        $prices->save(new PriceListEntry('pl_01', 'item_01', Money::ofMinor(1000, 'EUR'), 10.0, null, null));

        $pricing = new PricingService($prices);

        $this->assertNull($pricing->resolvePrice('pl_01', 'item_01', 5.0));
    }
}
