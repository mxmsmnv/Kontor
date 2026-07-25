<?php

declare(strict_types=1);

namespace Kontor\Catalog\Domain;

use Kontor\SDK\ValueObjects\Money;

/**
 * kontor.md#14.3 — no uid of its own in the spec's schema, like
 * kontor_contact_company; followed exactly.
 */
final class PriceListEntry
{
    public function __construct(
        public readonly string $priceListUid,
        public readonly string $itemUid,
        public Money $price,
        public float $minQuantity,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
    ) {
    }

    public function appliesOn(\DateTimeImmutable $date, float $quantity): bool
    {
        if ($quantity < $this->minQuantity) {
            return false;
        }

        if ($this->validFrom !== null && $date < $this->validFrom) {
            return false;
        }

        return !($this->validTo !== null && $date > $this->validTo);
    }
}
