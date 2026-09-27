<?php

declare(strict_types=1);

namespace Kontor\Inventory\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Application\InventoryMovementService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Inventory\Health\InventoryHealthCheck;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Inventory\Infrastructure\Persistence\MovementRepository;
use Kontor\Inventory\Infrastructure\Persistence\WarehouseRepository;

final class InventoryHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_active_warehouse_count(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $warehouses = new WarehouseRepository($this->pdo, $organizations);
        $balances = new BalanceRepository($this->pdo, $organizations);
        $movements = new InventoryMovementService($this->pdo, $organizations, $warehouses, $balances, new MovementRepository($this->pdo, $organizations));

        $warehouse = Warehouse::create($this->organizationUid, 'MAIN', 'Main');
        $warehouses->save($warehouse);
        $movements->receive($this->organizationUid, $warehouse->uid->toString(), 'itm_01', 5.0);

        $result = (new InventoryHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeWarehouses']);
        $this->assertSame(0, $result->details['inconsistentBalances']);
    }

    public function test_critical_when_a_balance_row_is_inconsistent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $warehouses = new WarehouseRepository($this->pdo, $organizations);
        $warehouse = Warehouse::create($this->organizationUid, 'MAIN', 'Main');
        $warehouses->save($warehouse);

        $organizationId = $organizations->internalIdOf($this->organizationUid);
        $this->pdo->prepare(
            'INSERT INTO kontor_inventory_balances (organization_id, warehouse_uid, item_uid, quantity_on_hand, quantity_reserved, quantity_available, updated_at, version)
             VALUES (:org, :wh, :item, 10, 0, 999, :now, 1)'
        )->execute(['org' => $organizationId, 'wh' => $warehouse->uid->toString(), 'item' => 'itm_broken', 'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u')]);

        $result = (new InventoryHealthCheck($this->pdo))->run();

        $this->assertSame('critical', $result->status);
        $this->assertSame(1, $result->details['inconsistentBalances']);
    }
}
