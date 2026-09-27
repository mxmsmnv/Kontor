<?php

declare(strict_types=1);

namespace Kontor\Catalog\Application;

use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\SDK\ValueObjects\Money;

/**
 * Substage 3.2 "price lists" — resolves the price a given item/quantity/
 * date actually pays: the highest quantity-break tier the requested
 * quantity qualifies for, restricted to entries valid on that date.
 * PriceRepository::forItem() already returns entries ordered by
 * min_quantity descending, so the first one that appliesOn() wins.
 */
final class PricingService
{
    public function __construct(private readonly PriceRepository $prices)
    {
    }

    public function resolvePrice(
        string $priceListUid,
        string $itemUid,
        float $quantity = 1.0,
        ?\DateTimeImmutable $on = null,
    ): ?Money {
        $on ??= new \DateTimeImmutable();

        foreach ($this->prices->forItem($priceListUid, $itemUid) as $entry) {
            if ($entry->appliesOn($on, $quantity)) {
                return $entry->price;
            }
        }

        return null;
    }
}
