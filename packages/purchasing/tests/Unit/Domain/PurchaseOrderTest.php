<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Tests\Unit\Domain;

use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderTest extends TestCase
{
    public function test_create_starts_as_a_draft(): void
    {
        $order = PurchaseOrder::create('org_01', 'sup_01', 'EUR');

        $this->assertTrue($order->isDraft());
        $this->assertFalse($order->isReceivable());
        $this->assertTrue($order->isCancellable());
    }

    public function test_apply_totals_from_lines(): void
    {
        $order = PurchaseOrder::create('org_01', 'sup_01', 'EUR');
        $line = DocumentLine::create('org_01', 'purchase_order', $order->uid->toString(), 'Widget', 10.0, Money::ofMinor(500, 'EUR'), taxRate: 20.0);

        $order->applyTotalsFromLines([$line]);

        $this->assertSame(5000, $order->subtotal->amountMinor());
        $this->assertSame(1000, $order->tax->amountMinor());
        $this->assertSame(6000, $order->total->amountMinor());
    }

    public function test_status_helpers(): void
    {
        $order = PurchaseOrder::create('org_01', 'sup_01', 'EUR');

        $order->status = 'issued';
        $this->assertTrue($order->isReceivable());
        $this->assertTrue($order->isCancellable());

        $order->status = 'partially_received';
        $this->assertTrue($order->isReceivable());

        $order->status = 'received';
        $this->assertFalse($order->isReceivable());
        $this->assertFalse($order->isCancellable());

        $order->status = 'cancelled';
        $this->assertFalse($order->isCancellable());
    }
}
