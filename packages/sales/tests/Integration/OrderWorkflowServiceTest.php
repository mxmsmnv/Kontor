<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Sales\Application\OrderWorkflowService;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;

final class OrderWorkflowServiceTest extends DatabaseTestCase
{
    private OrderRepository $orders;
    private OrderWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = new OrderRepository($this->pdo, new OrganizationRepository($this->pdo));
        $this->service = new OrderWorkflowService($this->orders);
    }

    public function test_confirm_then_complete(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);

        $confirmed = $this->service->confirm($order->uid->toString());
        $this->assertSame('confirmed', $confirmed->orderStatus);
        $this->assertNotNull($confirmed->confirmedAt);

        $completed = $this->service->complete($order->uid->toString());
        $this->assertSame('completed', $completed->orderStatus);
        $this->assertSame('fulfilled', $completed->fulfillmentStatus);
        $this->assertNotNull($completed->completedAt);
    }

    public function test_complete_requires_confirmed_first(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);

        $this->expectException(\RuntimeException::class);

        $this->service->complete($order->uid->toString());
    }

    public function test_cancel_a_pending_order(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);

        $cancelled = $this->service->cancel($order->uid->toString());

        $this->assertSame('cancelled', $cancelled->orderStatus);
    }

    public function test_cannot_cancel_a_completed_order(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);
        $this->service->confirm($order->uid->toString());
        $this->service->complete($order->uid->toString());

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($order->uid->toString());
    }
}
