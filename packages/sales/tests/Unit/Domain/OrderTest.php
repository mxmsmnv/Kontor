<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Unit\Domain;

use Kontor\Sales\Domain\Order;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $order = Order::create('org_01', 'contact', 'ct_01', 'EUR');

        $this->assertTrue($order->isPending());
        $this->assertTrue($order->isOpen());
        $this->assertSame('unpaid', $order->paymentStatus);
        $this->assertSame('pending', $order->fulfillmentStatus);
    }

    public function test_is_open_covers_pending_and_confirmed(): void
    {
        $order = Order::create('org_01', 'contact', 'ct_01', 'EUR');
        $order->orderStatus = 'confirmed';

        $this->assertTrue($order->isConfirmed());
        $this->assertTrue($order->isOpen());

        $order->orderStatus = 'completed';
        $this->assertFalse($order->isOpen());
    }
}
