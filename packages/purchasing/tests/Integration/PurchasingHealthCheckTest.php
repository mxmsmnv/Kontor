<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Purchasing\Application\PurchaseOrderWorkflowService;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Purchasing\Health\PurchasingHealthCheck;
use Kontor\Purchasing\Infrastructure\Persistence\PurchaseOrderRepository;
use Kontor\Purchasing\Infrastructure\Persistence\SupplierRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

final class PurchasingHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_open_order_count(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $suppliers = new SupplierRepository($this->pdo, $organizations);
        $orders = new PurchaseOrderRepository($this->pdo, $organizations);
        $lines = new DocumentLineRepository($this->pdo, $organizations);
        $workflow = new PurchaseOrderWorkflowService($orders, $lines, new SequenceService($this->pdo, $organizations));

        $supplier = Supplier::create($this->organizationUid, 'SUP-1', 'Acme', 'EUR');
        $suppliers->save($supplier);

        $order = PurchaseOrder::create($this->organizationUid, $supplier->uid->toString(), 'EUR');
        $orders->save($order);
        $lines->save(DocumentLine::create($this->organizationUid, 'purchase_order', $order->uid->toString(), 'Widget', 1.0, Money::ofMinor(500, 'EUR')));
        $workflow->issue($order->uid->toString());

        $result = (new PurchasingHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['openOrders']);
        $this->assertSame(0, $result->details['receipts']);
    }
}
