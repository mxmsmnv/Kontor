<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;

final class PriceRepositoryTest extends DatabaseTestCase
{
    public function test_for_price_list_delete_and_replace(): void
    {
        $repository = new PriceRepository($this->pdo);
        $repository->save(new PriceListEntry(
            '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            Money::ofMinor(1000, 'EUR'),
            1,
            null,
            null,
        ));
        $repository->save(new PriceListEntry(
            '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            '01ARZ3NDEKTSV4RRFFQ69G5FAC',
            Money::ofMinor(900, 'EUR'),
            5,
            null,
            null,
        ));

        $this->assertCount(2, $repository->forPriceList('01ARZ3NDEKTSV4RRFFQ69G5FAA'));

        $repository->replace(
            '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            1,
            new PriceListEntry(
                '01ARZ3NDEKTSV4RRFFQ69G5FAA',
                '01ARZ3NDEKTSV4RRFFQ69G5FAB',
                Money::ofMinor(800, 'EUR'),
                10,
                null,
                null,
            ),
        );

        $this->assertSame(10.0, $repository->forItem(
            '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            '01ARZ3NDEKTSV4RRFFQ69G5FAB',
        )[0]->minQuantity);
        $this->assertTrue($repository->delete(
            '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            '01ARZ3NDEKTSV4RRFFQ69G5FAC',
            5,
        ));
        $this->assertCount(1, $repository->forPriceList('01ARZ3NDEKTSV4RRFFQ69G5FAA'));
    }

    public function test_for_catalog_item_returns_tiers_across_the_organizations_price_lists(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $priceLists = new PriceListRepository($this->pdo, $organizations);
        $prices = new PriceRepository($this->pdo);
        $retail = PriceList::create($this->organizationUid, 'Retail', 'EUR');
        $wholesale = PriceList::create($this->organizationUid, 'Wholesale', 'EUR');
        $priceLists->save($wholesale);
        $priceLists->save($retail);
        $itemUid = '01ARZ3NDEKTSV4RRFFQ69G5FAB';

        $prices->save(new PriceListEntry(
            $retail->uid->toString(),
            $itemUid,
            Money::ofMinor(1500, 'EUR'),
            1,
            null,
            null,
        ));
        $prices->save(new PriceListEntry(
            $wholesale->uid->toString(),
            $itemUid,
            Money::ofMinor(1200, 'EUR'),
            10,
            null,
            null,
        ));
        $prices->save(new PriceListEntry(
            $retail->uid->toString(),
            '01ARZ3NDEKTSV4RRFFQ69G5FAC',
            Money::ofMinor(999, 'EUR'),
            1,
            null,
            null,
        ));

        $entries = $prices->forCatalogItem($this->organizationUid, $itemUid);

        $this->assertCount(2, $entries);
        $this->assertSame(
            [$retail->uid->toString(), $wholesale->uid->toString()],
            array_column($entries, 'priceListUid'),
        );
        $this->assertSame([1500, 1200], array_map(
            static fn (PriceListEntry $entry): int => $entry->price->amountMinor(),
            $entries,
        ));
    }
}
