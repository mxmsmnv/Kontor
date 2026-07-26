<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
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
}
