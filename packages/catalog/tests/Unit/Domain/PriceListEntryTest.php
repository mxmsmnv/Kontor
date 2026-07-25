<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Unit\Domain;

use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class PriceListEntryTest extends TestCase
{
    public function test_applies_on_respects_min_quantity(): void
    {
        $entry = new PriceListEntry('pl_01', 'item_01', Money::ofMinor(1000, 'EUR'), 10.0, null, null);

        $this->assertFalse($entry->appliesOn(new \DateTimeImmutable(), 5.0));
        $this->assertTrue($entry->appliesOn(new \DateTimeImmutable(), 10.0));
        $this->assertTrue($entry->appliesOn(new \DateTimeImmutable(), 20.0));
    }

    public function test_applies_on_respects_validity_window(): void
    {
        $entry = new PriceListEntry(
            'pl_01', 'item_01', Money::ofMinor(1000, 'EUR'), 1.0,
            new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'),
        );

        $this->assertFalse($entry->appliesOn(new \DateTimeImmutable('2025-12-31'), 1.0));
        $this->assertTrue($entry->appliesOn(new \DateTimeImmutable('2026-06-01'), 1.0));
        $this->assertFalse($entry->appliesOn(new \DateTimeImmutable('2027-01-01'), 1.0));
    }
}
