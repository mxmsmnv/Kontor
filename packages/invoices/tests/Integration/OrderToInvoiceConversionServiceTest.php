<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Application\OrderToInvoiceConversionService;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;

final class OrderToInvoiceConversionServiceTest extends DatabaseTestCase
{
    private InvoiceRepository $invoices;
    private OrderRepository $orders;
    private DocumentLineRepository $lines;
    private OrderToInvoiceConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->invoices = new InvoiceRepository($this->pdo, $organizations);
        $this->orders = new OrderRepository($this->pdo, $organizations);
        $this->lines = new DocumentLineRepository($this->pdo, $organizations);
        $this->service = new OrderToInvoiceConversionService(
            $this->invoices,
            $this->orders,
            $this->lines,
        );
    }

    public function test_converts_a_completed_order_with_copied_lines_and_totals(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $order->orderStatus = 'completed';
        $this->orders->save($order);
        $this->lines->save(DocumentLine::create(
            $this->organizationUid,
            'order',
            $order->uid->toString(),
            'Consulting',
            2,
            Money::ofMinor(10000, 'EUR'),
            taxRate: 20,
        ));

        $invoice = $this->service->convert($order->uid->toString());

        $this->assertSame($order->uid->toString(), $invoice->orderUid);
        $this->assertSame(20000, $invoice->subtotal->amountMinor());
        $this->assertSame(4000, $invoice->tax->amountMinor());
        $this->assertSame(24000, $invoice->total->amountMinor());
        $this->assertSame(24000, $invoice->due->amountMinor());
        $this->assertCount(1, $this->lines->forDocument('invoice', $invoice->uid->toString()));
    }

    public function test_refuses_a_pending_order(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('confirmed or completed');

        $this->service->convert($order->uid->toString());
    }

    public function test_refuses_an_order_without_lines(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $order->orderStatus = 'confirmed';
        $this->orders->save($order);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no document lines');

        $this->service->convert($order->uid->toString());
    }

    public function test_refuses_to_convert_the_same_order_twice(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $order->orderStatus = 'confirmed';
        $this->orders->save($order);
        $this->lines->save(DocumentLine::create(
            $this->organizationUid,
            'order',
            $order->uid->toString(),
            'Consulting',
            1,
            Money::ofMinor(10000, 'EUR'),
        ));
        $this->service->convert($order->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already converted');

        $this->service->convert($order->uid->toString());
    }
}
