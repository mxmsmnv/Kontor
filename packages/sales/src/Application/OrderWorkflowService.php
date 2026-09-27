<?php

declare(strict_types=1);

namespace Kontor\Sales\Application;

use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;
use RuntimeException;

/**
 * Substage 4.1 "status workflows" for orders. Only order_status is
 * actively driven here — payment_status/fulfillment_status belong to
 * Payments (Substage 4.4) and Inventory (Substage 6.1), not built yet.
 */
final class OrderWorkflowService
{
    public function __construct(private readonly OrderRepository $orders)
    {
    }

    public function confirm(string $orderUid): Order
    {
        $order = $this->orders->require($orderUid);

        if (!$order->isPending()) {
            throw new RuntimeException("Order \"{$orderUid}\" is not pending and cannot be confirmed.");
        }

        $order->orderStatus = 'confirmed';
        $order->confirmedAt = new \DateTimeImmutable();
        $this->orders->save($order);

        return $order;
    }

    public function complete(string $orderUid): Order
    {
        $order = $this->orders->require($orderUid);

        if (!$order->isConfirmed()) {
            throw new RuntimeException("Order \"{$orderUid}\" must be confirmed before it can be completed.");
        }

        $order->orderStatus = 'completed';
        $order->completedAt = new \DateTimeImmutable();
        $order->fulfillmentStatus = 'fulfilled';
        $this->orders->save($order);

        return $order;
    }

    public function cancel(string $orderUid): Order
    {
        $order = $this->orders->require($orderUid);

        if (!$order->isOpen()) {
            throw new RuntimeException("Order \"{$orderUid}\" is already completed or cancelled.");
        }

        $order->orderStatus = 'cancelled';
        $this->orders->save($order);

        return $order;
    }
}
