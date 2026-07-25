<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Application;

use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Infrastructure\Persistence\PurchaseOrderRepository;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use RuntimeException;

/**
 * The "purchase orders" milestone's own lifecycle: draft -> issued ->
 * (partially_received/received, driven by GoodsReceiptService) ->
 * cancelled. Lines are shared kontor_document_lines rows
 * (document_type = 'purchase_order'), reusing kontor/sales'
 * DocumentLine/DocumentLineRepository directly — the same reuse
 * kontor/invoices already established for that table.
 */
final class PurchaseOrderWorkflowService
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly DocumentLineRepository $lines,
        private readonly SequenceService $sequences,
    ) {
    }

    public function issue(string $purchaseOrderUid): PurchaseOrder
    {
        $order = $this->orders->require($purchaseOrderUid);

        if (!$order->isDraft()) {
            throw new RuntimeException("Purchase order \"{$purchaseOrderUid}\" is not a draft and cannot be issued.");
        }

        $order->applyTotalsFromLines($this->lines->forDocument('purchase_order', $purchaseOrderUid));
        $order->number = $this->sequences->next(
            $order->organizationId, 'purchasing', 'purchase_order', prefix: 'PO-', padding: 5, resetPolicy: 'yearly'
        );
        $order->issueDate ??= new \DateTimeImmutable();
        $order->issuedAt = new \DateTimeImmutable();
        $order->status = 'issued';

        $this->orders->save($order);

        return $order;
    }

    public function cancel(string $purchaseOrderUid): PurchaseOrder
    {
        $order = $this->orders->require($purchaseOrderUid);

        if (!$order->isCancellable()) {
            throw new RuntimeException("Purchase order \"{$purchaseOrderUid}\" cannot be cancelled from status \"{$order->status}\".");
        }

        $order->status = 'cancelled';
        $order->cancelledAt = new \DateTimeImmutable();
        $this->orders->save($order);

        return $order;
    }
}
