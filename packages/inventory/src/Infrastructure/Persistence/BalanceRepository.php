<?php

declare(strict_types=1);

namespace Kontor\Inventory\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Domain\StockBalance;

/**
 * kontor.md#16.2. lockAndGetOrCreate()/updateQuantities() are the two
 * halves of kontor.md diagram 17.3's "Lock balance rows" / "Update
 * balances" steps — both expect to run inside a transaction the caller
 * (InventoryMovementService) already opened, since a transfer locks two
 * rows (source and destination) in one transaction. This mirrors
 * kontor/core's own SequenceService: SELECT ... FOR UPDATE if the row
 * exists, otherwise INSERT it (which holds the same lock as the inserting
 * transaction until commit).
 */
final class BalanceRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $organizationUid, string $warehouseUid, string $itemUid): StockBalance
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_inventory_balances WHERE organization_id = :organization_id AND warehouse_uid = :warehouse_uid AND item_uid = :item_uid'
        );
        $statement->execute(['organization_id' => $organizationId, 'warehouse_uid' => $warehouseUid, 'item_uid' => $itemUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false
            ? new StockBalance($organizationUid, $warehouseUid, $itemUid, 0.0, 0.0, 0.0)
            : $this->hydrate($row, $organizationUid);
    }

    /**
     * Must be called inside an already-open transaction. Returns the raw
     * row (including its `id`) so the caller can pass it straight to
     * updateQuantities() without a second lookup.
     *
     * @return array<string, mixed>
     */
    public function lockAndGetOrCreate(int $organizationId, string $warehouseUid, string $itemUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_inventory_balances
             WHERE organization_id = :organization_id AND warehouse_uid = :warehouse_uid AND item_uid = :item_uid
             FOR UPDATE'
        );
        $statement->execute(['organization_id' => $organizationId, 'warehouse_uid' => $warehouseUid, 'item_uid' => $itemUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row !== false) {
            return $row;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO kontor_inventory_balances
                (organization_id, warehouse_uid, item_uid, quantity_on_hand, quantity_reserved, quantity_available, updated_at, version)
             VALUES
                (:organization_id, :warehouse_uid, :item_uid, 0, 0, 0, :updated_at, 1)'
        );
        $insert->execute([
            'organization_id' => $organizationId,
            'warehouse_uid' => $warehouseUid,
            'item_uid' => $itemUid,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'organization_id' => $organizationId,
            'warehouse_uid' => $warehouseUid,
            'item_uid' => $itemUid,
            'quantity_on_hand' => '0',
            'quantity_reserved' => '0',
            'quantity_available' => '0',
        ];
    }

    public function updateQuantities(int $rowId, float $onHand, float $reserved, float $available): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE kontor_inventory_balances
             SET quantity_on_hand = :on_hand, quantity_reserved = :reserved, quantity_available = :available,
                 updated_at = :updated_at, version = version + 1
             WHERE id = :id'
        );
        $statement->execute([
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'id' => $rowId,
        ]);
    }

    private function hydrate(array $row, string $organizationUid): StockBalance
    {
        return new StockBalance(
            organizationId: $organizationUid,
            warehouseUid: $row['warehouse_uid'],
            itemUid: $row['item_uid'],
            quantityOnHand: (float) $row['quantity_on_hand'],
            quantityReserved: (float) $row['quantity_reserved'],
            quantityAvailable: (float) $row['quantity_available'],
        );
    }
}
