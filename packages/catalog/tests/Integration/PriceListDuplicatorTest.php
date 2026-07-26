<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;

final class PriceListDuplicatorTest extends DatabaseTestCase
{
    public function test_duplicate_copies_price_tiers_into_an_inactive_list(): void
    {
        $priceLists = new PriceListRepository(
            $this->pdo,
            new OrganizationRepository($this->pdo),
        );
        $prices = new PriceRepository($this->pdo);
        $source = PriceList::create(
            $this->organizationUid,
            'Retail',
            'EUR',
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validTo: new \DateTimeImmutable('2026-12-31'),
        );
        $priceLists->save($source);
        $prices->save(new PriceListEntry(
            $source->uid->toString(),
            '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            Money::ofMinor(1299, 'EUR'),
            1,
            new \DateTimeImmutable('2026-02-01'),
            null,
        ));
        $prices->save(new PriceListEntry(
            $source->uid->toString(),
            '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            Money::ofMinor(999, 'EUR'),
            10,
            null,
            new \DateTimeImmutable('2026-10-31'),
        ));

        $duplicate = (new PriceListDuplicator($this->pdo, $priceLists, $prices))
            ->duplicate($source);
        $copiedEntries = $prices->forPriceList($duplicate->uid->toString());

        $this->assertSame('Retail (copy)', $duplicate->name);
        $this->assertSame('inactive', $duplicate->status);
        $this->assertSame('EUR', $duplicate->currencyCode);
        $this->assertCount(2, $copiedEntries);
        $this->assertSame([1.0, 10.0], array_column($copiedEntries, 'minQuantity'));
        $this->assertSame([1299, 999], array_map(
            static fn (PriceListEntry $entry): int => $entry->price->amountMinor(),
            $copiedEntries,
        ));
        $this->assertCount(2, $prices->forPriceList($source->uid->toString()));
        $this->assertSame('active', $priceLists->require($source->uid->toString())->status);
    }
}
