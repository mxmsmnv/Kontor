<?php

declare(strict_types=1);

namespace Kontor\Inventory\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Application\InventoryMovementService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Inventory\Infrastructure\Persistence\MovementRepository;
use Kontor\Inventory\Infrastructure\Persistence\WarehouseRepository;

final class InventoryMovementServiceTest extends DatabaseTestCase
{
    private InventoryMovementService $movements;
    private BalanceRepository $balances;
    private WarehouseRepository $warehouses;
    private string $warehouseA;
    private string $warehouseB;
    private const ITEM = 'itm_00000000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->warehouses = new WarehouseRepository($this->pdo, $organizations);
        $this->balances = new BalanceRepository($this->pdo, $organizations);
        $movementRepository = new MovementRepository($this->pdo, $organizations);

        $this->movements = new InventoryMovementService($this->pdo, $organizations, $this->warehouses, $this->balances, $movementRepository);

        $a = Warehouse::create($this->organizationUid, 'WH-A', 'Warehouse A');
        $this->warehouses->save($a);
        $this->warehouseA = $a->uid->toString();

        $b = Warehouse::create($this->organizationUid, 'WH-B', 'Warehouse B');
        $this->warehouses->save($b);
        $this->warehouseB = $b->uid->toString();
    }

    public function test_receive_increases_on_hand_and_available(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);

        $balance = $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM);
        $this->assertSame(10.0, $balance->quantityOnHand);
        $this->assertSame(10.0, $balance->quantityAvailable);
        $this->assertSame(0.0, $balance->quantityReserved);
    }

    public function test_receive_into_an_inactive_warehouse_is_rejected(): void
    {
        $this->warehouses->archive($this->warehouseA);

        $this->expectException(\RuntimeException::class);
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);

        $this->movements->transfer($this->organizationUid, $this->warehouseA, $this->warehouseB, self::ITEM, 4.0);

        $this->assertSame(6.0, $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM)->quantityOnHand);
        $this->assertSame(4.0, $this->balances->find($this->organizationUid, $this->warehouseB, self::ITEM)->quantityOnHand);
    }

    public function test_transfer_rejects_insufficient_available_stock(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 5.0);

        $this->expectException(\RuntimeException::class);
        $this->movements->transfer($this->organizationUid, $this->warehouseA, $this->warehouseB, self::ITEM, 10.0);
    }

    public function test_transfer_to_the_same_warehouse_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->movements->transfer($this->organizationUid, $this->warehouseA, $this->warehouseA, self::ITEM, 1.0);
    }

    public function test_adjust_decrease_rejects_going_negative_by_default(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 5.0);

        $this->expectException(\RuntimeException::class);
        $this->movements->adjustDecrease($this->organizationUid, $this->warehouseA, self::ITEM, 10.0, 'stock count correction');
    }

    public function test_adjust_decrease_allows_going_negative_when_overridden(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 5.0);

        $this->movements->adjustDecrease($this->organizationUid, $this->warehouseA, self::ITEM, 10.0, 'write-off', allowNegative: true);

        $this->assertSame(-5.0, $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM)->quantityOnHand);
    }

    public function test_reserve_moves_stock_from_available_to_reserved_without_changing_on_hand(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);

        $this->movements->reserve($this->organizationUid, $this->warehouseA, self::ITEM, 4.0, referenceType: 'sales_order', referenceUid: 'so_01');

        $balance = $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM);
        $this->assertSame(10.0, $balance->quantityOnHand);
        $this->assertSame(4.0, $balance->quantityReserved);
        $this->assertSame(6.0, $balance->quantityAvailable);
    }

    public function test_reserve_rejects_more_than_available(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 5.0);

        $this->expectException(\RuntimeException::class);
        $this->movements->reserve($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);
    }

    public function test_release_returns_reserved_stock_to_available(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);
        $this->movements->reserve($this->organizationUid, $this->warehouseA, self::ITEM, 4.0);

        $this->movements->release($this->organizationUid, $this->warehouseA, self::ITEM, 4.0);

        $balance = $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM);
        $this->assertSame(0.0, $balance->quantityReserved);
        $this->assertSame(10.0, $balance->quantityAvailable);
    }

    public function test_release_rejects_releasing_more_than_reserved(): void
    {
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0);
        $this->movements->reserve($this->organizationUid, $this->warehouseA, self::ITEM, 2.0);

        $this->expectException(\RuntimeException::class);
        $this->movements->release($this->organizationUid, $this->warehouseA, self::ITEM, 5.0);
    }

    public function test_idempotency_key_returns_the_original_movement_without_double_applying(): void
    {
        $first = $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0, idempotencyKey: 'receipt-123');
        $second = $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 10.0, idempotencyKey: 'receipt-123');

        $this->assertSame($first->uid->toString(), $second->uid->toString());
        $this->assertSame(10.0, $this->balances->find($this->organizationUid, $this->warehouseA, self::ITEM)->quantityOnHand);
    }

    public function test_movement_quantity_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->movements->receive($this->organizationUid, $this->warehouseA, self::ITEM, 0.0);
    }
}
