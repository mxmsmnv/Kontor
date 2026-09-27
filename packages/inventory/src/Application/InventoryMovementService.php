<?php

declare(strict_types=1);

namespace Kontor\Inventory\Application;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Domain\InventoryMovement;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Inventory\Infrastructure\Persistence\MovementRepository;
use Kontor\Inventory\Infrastructure\Persistence\WarehouseRepository;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;
use InvalidArgumentException;
use RuntimeException;

/**
 * Implements kontor.md diagram 17.3 ("Inventory movement") for every
 * movement type: validate warehouse(s) -> lock balance row(s) -> check
 * available stock -> update balances -> commit -> emit
 * inventory.movement.completed. "Reject or request approval" (the
 * diagram's insufficient-stock branch) is just a thrown exception here —
 * there's no approval workflow engine yet (kontor.md#18's KontorWorkflow),
 * so the caller decides what "reject" means.
 *
 * kontor-inventory-negative-stock-override (kontor.md#19.8) isn't checked
 * here — permission checks belong to admin controllers/API endpoints
 * (kontor.md#19.8's own list), not this service. adjustDecrease()'s
 * `$allowNegative` parameter is what that permission would gate.
 */
final class InventoryMovementService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
        private readonly WarehouseRepository $warehouses,
        private readonly BalanceRepository $balances,
        private readonly MovementRepository $movements,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function receive(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        string $unitCode = 'pcs',
        ?string $referenceType = null,
        ?string $referenceUid = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'receive', $itemUid, null, $warehouseUid, $quantity, $unitCode,
            $referenceType, $referenceUid, $reason, $idempotencyKey, $createdBy,
        );

        $this->transact(function () use ($organizationId, $warehouseUid, $itemUid, $quantity, $movement): void {
            $row = $this->balances->lockAndGetOrCreate($organizationId, $warehouseUid, $itemUid);
            $onHand = (float) $row['quantity_on_hand'] + $quantity;
            $reserved = (float) $row['quantity_reserved'];
            $this->balances->updateQuantities((int) $row['id'], $onHand, $reserved, $onHand - $reserved);
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    public function transfer(
        string $organizationUid,
        string $sourceWarehouseUid,
        string $destinationWarehouseUid,
        string $itemUid,
        float $quantity,
        string $unitCode = 'pcs',
        ?string $referenceType = null,
        ?string $referenceUid = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if ($sourceWarehouseUid === $destinationWarehouseUid) {
            throw new InvalidArgumentException('Cannot transfer stock to the same warehouse.');
        }

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($sourceWarehouseUid);
        $this->requireActiveWarehouse($destinationWarehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'transfer', $itemUid, $sourceWarehouseUid, $destinationWarehouseUid, $quantity,
            $unitCode, $referenceType, $referenceUid, $reason, $idempotencyKey, $createdBy,
        );

        // Lock in a consistent (lexicographic) order regardless of transfer
        // direction, so two concurrent opposite-direction transfers of the
        // same pair of warehouses can never deadlock against each other.
        [$first, $second] = $sourceWarehouseUid < $destinationWarehouseUid
            ? [$sourceWarehouseUid, $destinationWarehouseUid]
            : [$destinationWarehouseUid, $sourceWarehouseUid];

        $this->transact(function () use ($organizationId, $sourceWarehouseUid, $destinationWarehouseUid, $itemUid, $quantity, $movement, $first, $second): void {
            $rows = [
                $first => $this->balances->lockAndGetOrCreate($organizationId, $first, $itemUid),
                $second => $this->balances->lockAndGetOrCreate($organizationId, $second, $itemUid),
            ];

            $sourceRow = $rows[$sourceWarehouseUid];
            $sourceAvailable = (float) $sourceRow['quantity_available'];

            if ($sourceAvailable < $quantity) {
                throw new RuntimeException(
                    "Insufficient available stock in warehouse \"{$sourceWarehouseUid}\" for item \"{$itemUid}\" (available {$sourceAvailable}, requested {$quantity})."
                );
            }

            $sourceOnHand = (float) $sourceRow['quantity_on_hand'] - $quantity;
            $sourceReserved = (float) $sourceRow['quantity_reserved'];
            $this->balances->updateQuantities((int) $sourceRow['id'], $sourceOnHand, $sourceReserved, $sourceOnHand - $sourceReserved);

            $destinationRow = $rows[$destinationWarehouseUid];
            $destinationOnHand = (float) $destinationRow['quantity_on_hand'] + $quantity;
            $destinationReserved = (float) $destinationRow['quantity_reserved'];
            $this->balances->updateQuantities((int) $destinationRow['id'], $destinationOnHand, $destinationReserved, $destinationOnHand - $destinationReserved);

            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    public function adjustIncrease(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        string $reason,
        string $unitCode = 'pcs',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'adjust', $itemUid, null, $warehouseUid, $quantity, $unitCode,
            null, null, $reason, $idempotencyKey, $createdBy,
        );

        $this->transact(function () use ($organizationId, $warehouseUid, $itemUid, $quantity, $movement): void {
            $row = $this->balances->lockAndGetOrCreate($organizationId, $warehouseUid, $itemUid);
            $onHand = (float) $row['quantity_on_hand'] + $quantity;
            $reserved = (float) $row['quantity_reserved'];
            $this->balances->updateQuantities((int) $row['id'], $onHand, $reserved, $onHand - $reserved);
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    /**
     * $allowNegative is what kontor-inventory-negative-stock-override
     * (kontor.md#19.8) gates — the caller (an admin controller/API
     * endpoint that already checked the permission) decides whether to
     * pass true.
     */
    public function adjustDecrease(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        string $reason,
        string $unitCode = 'pcs',
        bool $allowNegative = false,
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'adjust', $itemUid, $warehouseUid, null, $quantity, $unitCode,
            null, null, $reason, $idempotencyKey, $createdBy,
        );

        $this->transact(function () use ($organizationId, $warehouseUid, $itemUid, $quantity, $allowNegative, $movement): void {
            $row = $this->balances->lockAndGetOrCreate($organizationId, $warehouseUid, $itemUid);
            $onHand = (float) $row['quantity_on_hand'] - $quantity;

            if (!$allowNegative && $onHand < 0) {
                throw new RuntimeException(
                    "Adjustment would take warehouse \"{$warehouseUid}\"'s on-hand stock for item \"{$itemUid}\" negative; pass \$allowNegative to permit this."
                );
            }

            $reserved = (float) $row['quantity_reserved'];
            $this->balances->updateQuantities((int) $row['id'], $onHand, $reserved, $onHand - $reserved);
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    public function reserve(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        ?string $referenceType = null,
        ?string $referenceUid = null,
        string $unitCode = 'pcs',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'reserve', $itemUid, null, $warehouseUid, $quantity, $unitCode,
            $referenceType, $referenceUid, null, $idempotencyKey, $createdBy,
        );

        $this->transact(function () use ($organizationId, $warehouseUid, $itemUid, $quantity, $movement): void {
            $row = $this->balances->lockAndGetOrCreate($organizationId, $warehouseUid, $itemUid);
            $available = (float) $row['quantity_available'];

            if ($available < $quantity) {
                throw new RuntimeException(
                    "Insufficient available stock in warehouse \"{$warehouseUid}\" for item \"{$itemUid}\" to reserve (available {$available}, requested {$quantity})."
                );
            }

            $onHand = (float) $row['quantity_on_hand'];
            $reserved = (float) $row['quantity_reserved'] + $quantity;
            $this->balances->updateQuantities((int) $row['id'], $onHand, $reserved, $onHand - $reserved);
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    public function release(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        ?string $referenceType = null,
        ?string $referenceUid = null,
        string $unitCode = 'pcs',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $movement = InventoryMovement::create(
            $organizationUid, 'release', $itemUid, null, $warehouseUid, $quantity, $unitCode,
            $referenceType, $referenceUid, null, $idempotencyKey, $createdBy,
        );

        $this->transact(function () use ($organizationId, $warehouseUid, $itemUid, $quantity, $movement): void {
            $row = $this->balances->lockAndGetOrCreate($organizationId, $warehouseUid, $itemUid);
            $reserved = (float) $row['quantity_reserved'];

            if ($reserved < $quantity) {
                throw new RuntimeException(
                    "Cannot release {$quantity} of item \"{$itemUid}\" in warehouse \"{$warehouseUid}\" — only {$reserved} is reserved."
                );
            }

            $onHand = (float) $row['quantity_on_hand'];
            $newReserved = $reserved - $quantity;
            $this->balances->updateQuantities((int) $row['id'], $onHand, $newReserved, $onHand - $newReserved);
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    public function shipReserved(
        string $organizationUid,
        string $warehouseUid,
        string $itemUid,
        float $quantity,
        ?string $referenceType = null,
        ?string $referenceUid = null,
        string $unitCode = 'pcs',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $this->assertPositive($quantity);

        if (($existing = $this->existingForKey($organizationUid, $idempotencyKey)) !== null) {
            return $existing;
        }

        $this->requireActiveWarehouse($warehouseUid);
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $movement = InventoryMovement::create(
            $organizationUid,
            'ship',
            $itemUid,
            $warehouseUid,
            null,
            $quantity,
            $unitCode,
            $referenceType,
            $referenceUid,
            null,
            $idempotencyKey,
            $createdBy,
        );

        $this->transact(function () use (
            $organizationId,
            $warehouseUid,
            $itemUid,
            $quantity,
            $movement,
        ): void {
            $row = $this->balances->lockAndGetOrCreate(
                $organizationId,
                $warehouseUid,
                $itemUid,
            );
            $onHand = (float) $row['quantity_on_hand'];
            $reserved = (float) $row['quantity_reserved'];
            if ($reserved < $quantity || $onHand < $quantity) {
                throw new RuntimeException(
                    "Cannot ship {$quantity} of item \"{$itemUid}\" from warehouse \"{$warehouseUid}\" — on hand {$onHand}, reserved {$reserved}."
                );
            }

            $newOnHand = $onHand - $quantity;
            $newReserved = $reserved - $quantity;
            $this->balances->updateQuantities(
                (int) $row['id'],
                $newOnHand,
                $newReserved,
                $newOnHand - $newReserved,
            );
            $this->movements->insert($movement);
        });

        $this->emit($movement);

        return $movement;
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Movement quantity must be greater than zero.');
        }
    }

    private function requireActiveWarehouse(string $warehouseUid): void
    {
        $warehouse = $this->warehouses->find($warehouseUid);

        if ($warehouse === null || !$warehouse->isActive()) {
            throw new RuntimeException("Warehouse \"{$warehouseUid}\" is not available for movements.");
        }
    }

    private function existingForKey(string $organizationUid, ?string $idempotencyKey): ?InventoryMovement
    {
        return $idempotencyKey !== null ? $this->movements->findByIdempotencyKey($organizationUid, $idempotencyKey) : null;
    }

    private function transact(callable $fn): void
    {
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $fn();

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function emit(InventoryMovement $movement): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: 'inventory.movement.completed',
            organizationId: $movement->organizationId,
            entityType: 'inventory_movement',
            entityId: $movement->uid->toString(),
            actorType: 'system',
            actorId: $movement->createdBy !== null ? (string) $movement->createdBy : null,
            data: [
                'movementType' => $movement->movementType,
                'itemUid' => $movement->itemUid,
                'quantity' => $movement->quantity,
            ],
        ));
    }
}
