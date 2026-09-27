<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Inventory\Application\InventoryMovementService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Inventory\Infrastructure\Persistence\MovementRepository;
use Kontor\Inventory\Infrastructure\Persistence\WarehouseRepository;
use Kontor\Purchasing\Application\GoodsReceiptService;
use Kontor\Purchasing\Application\PurchaseOrderWorkflowService;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptLineRepository;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptRepository;
use Kontor\Purchasing\Infrastructure\Persistence\PurchaseOrderRepository;
use Kontor\Purchasing\Infrastructure\Persistence\SupplierRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

final class GoodsReceiptServiceTest extends DatabaseTestCase
{
    private const ITEM = 'itm_widget_00000001';

    private PurchaseOrderRepository $orders;
    private DocumentLineRepository $lines;
    private PurchaseOrderWorkflowService $workflow;
    private GoodsReceiptService $receiptService;
    private BalanceRepository $balances;
    private string $warehouseUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $sequences = new SequenceService($this->pdo, $organizations);

        $this->orders = new PurchaseOrderRepository($this->pdo, $organizations);
        $this->lines = new DocumentLineRepository($this->pdo, $organizations);
        $this->workflow = new PurchaseOrderWorkflowService($this->orders, $this->lines, $sequences);

        $warehouses = new WarehouseRepository($this->pdo, $organizations);
        $this->balances = new BalanceRepository($this->pdo, $organizations);
        $inventoryMovements = new InventoryMovementService(
            $this->pdo, $organizations, $warehouses, $this->balances, new MovementRepository($this->pdo, $organizations),
        );

        $warehouse = Warehouse::create($this->organizationUid, 'MAIN', 'Main Warehouse');
        $warehouses->save($warehouse);
        $this->warehouseUid = $warehouse->uid->toString();

        $this->receiptService = new GoodsReceiptService(
            $this->pdo, $this->orders, $this->lines,
            new GoodsReceiptRepository($this->pdo, $organizations),
            new GoodsReceiptLineRepository($this->pdo, $organizations),
            $inventoryMovements,
        );
    }

    private function issuedOrderWithOneLine(float $quantity = 10.0): PurchaseOrder
    {
        $supplier = Supplier::create($this->organizationUid, 'SUP-1', 'Acme Supplies', 'EUR');
        (new SupplierRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($supplier);

        $order = PurchaseOrder::create($this->organizationUid, $supplier->uid->toString(), 'EUR', $this->warehouseUid);
        $this->orders->save($order);

        $this->lines->save(DocumentLine::create(
            $this->organizationUid, 'purchase_order', $order->uid->toString(), 'Widget', $quantity, Money::ofMinor(500, 'EUR'),
            itemUid: self::ITEM,
        ));

        return $this->workflow->issue($order->uid->toString());
    }

    public function test_receiving_the_full_ordered_quantity_marks_the_order_received_and_moves_stock(): void
    {
        $order = $this->issuedOrderWithOneLine(10.0);
        $line = $this->lines->forDocument('purchase_order', $order->uid->toString())[0];

        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $line->uid->toString(), 'quantity' => 10.0],
        ]);

        $reloaded = $this->orders->require($order->uid->toString());
        $this->assertSame('received', $reloaded->status);
        $this->assertNotNull($reloaded->receivedAt);

        $balance = $this->balances->find($this->organizationUid, $this->warehouseUid, self::ITEM);
        $this->assertSame(10.0, $balance->quantityOnHand);
        $this->assertSame(10.0, $balance->quantityAvailable);
    }

    public function test_partial_receipt_marks_the_order_partially_received_and_moves_only_that_quantity(): void
    {
        $order = $this->issuedOrderWithOneLine(10.0);
        $line = $this->lines->forDocument('purchase_order', $order->uid->toString())[0];

        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $line->uid->toString(), 'quantity' => 4.0],
        ]);

        $reloaded = $this->orders->require($order->uid->toString());
        $this->assertSame('partially_received', $reloaded->status);
        $this->assertNull($reloaded->receivedAt);

        $balance = $this->balances->find($this->organizationUid, $this->warehouseUid, $line->itemUid);
        $this->assertSame(4.0, $balance->quantityOnHand);

        // Receiving the remainder completes it.
        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $line->uid->toString(), 'quantity' => 6.0],
        ]);

        $this->assertSame('received', $this->orders->require($order->uid->toString())->status);
        $this->assertSame(10.0, $this->balances->find($this->organizationUid, $this->warehouseUid, $line->itemUid)->quantityOnHand);
    }

    public function test_cannot_receive_more_than_what_remains_outstanding(): void
    {
        $order = $this->issuedOrderWithOneLine(10.0);
        $line = $this->lines->forDocument('purchase_order', $order->uid->toString())[0];

        $this->expectException(\RuntimeException::class);
        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $line->uid->toString(), 'quantity' => 11.0],
        ]);
    }

    public function test_cannot_receive_against_a_draft_order(): void
    {
        $supplier = Supplier::create($this->organizationUid, 'SUP-1', 'Acme', 'EUR');
        (new SupplierRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($supplier);
        $order = PurchaseOrder::create($this->organizationUid, $supplier->uid->toString(), 'EUR');
        $this->orders->save($order);
        $this->lines->save(DocumentLine::create($this->organizationUid, 'purchase_order', $order->uid->toString(), 'Widget', 1.0, Money::ofMinor(500, 'EUR')));

        $this->expectException(\RuntimeException::class);
        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $this->lines->forDocument('purchase_order', $order->uid->toString())[0]->uid->toString(), 'quantity' => 1.0],
        ]);
    }

    public function test_rejects_a_line_that_does_not_belong_to_the_order(): void
    {
        $order = $this->issuedOrderWithOneLine(10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'quantity' => 1.0],
        ]);
    }

    public function test_cancel_is_blocked_once_fully_received(): void
    {
        $order = $this->issuedOrderWithOneLine(10.0);
        $line = $this->lines->forDocument('purchase_order', $order->uid->toString())[0];
        $this->receiptService->receive($this->organizationUid, $order->uid->toString(), $this->warehouseUid, [
            ['poLineUid' => $line->uid->toString(), 'quantity' => 10.0],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->workflow->cancel($order->uid->toString());
    }
}
