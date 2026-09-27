<?php

declare(strict_types=1);

namespace Kontor\Inventory\Domain;

/**
 * kontor.md#16.2. A read model, not an aggregate a caller constructs —
 * BalanceRepository is the only thing that produces one, always for an
 * exact (organization, warehouse, item) triple, defaulting to all-zero
 * when no row exists yet (a balance implicitly exists at zero before its
 * first movement).
 */
final class StockBalance
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $warehouseUid,
        public readonly string $itemUid,
        public readonly float $quantityOnHand,
        public readonly float $quantityReserved,
        public readonly float $quantityAvailable,
    ) {
    }
}
