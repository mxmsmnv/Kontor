<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Application;

use Kontor\Inventory\Application\InventoryMovementService;
use Kontor\Purchasing\Domain\GoodsReceipt;
use Kontor\Purchasing\Domain\GoodsReceiptLine;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptLineRepository;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptRepository;
use Kontor\Purchasing\Infrastructure\Persistence\PurchaseOrderRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * The "goods receipt" and "inventory integration" milestones together:
 * receive() validates the requested quantities against what each line
 * still has outstanding, records the receipt, and — the actual
 * integration — calls kontor/inventory's InventoryMovementService::receive()
 * per line so the warehouse's stock balance actually moves. Everything
 * runs inside one transaction shared with InventoryMovementService (which
 * detects the ambient transaction and defers commit/rollback to it), so a
 * receipt and the stock movements it causes are atomic together.
 */
final class GoodsReceiptService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PurchaseOrderRepository $orders,
        private readonly DocumentLineRepository $lines,
        private readonly GoodsReceiptRepository $receipts,
        private readonly GoodsReceiptLineRepository $receiptLines,
        private readonly InventoryMovementService $inventory,
    ) {
    }

    /**
     * @param array<int, array{poLineUid: string, quantity: float}> $lines
     */
    public function receive(string $organizationUid, string $purchaseOrderUid, string $warehouseUid, array $lines, ?int $createdBy = null): GoodsReceipt
    {
        $order = $this->orders->require($purchaseOrderUid);

        if (!$order->isReceivable()) {
            throw new RuntimeException("Purchase order \"{$purchaseOrderUid}\" cannot be received from status \"{$order->status}\".");
        }

        if ($lines === []) {
            throw new InvalidArgumentException('At least one line is required to record a goods receipt.');
        }

        $orderLines = [];
        foreach ($this->lines->forDocument('purchase_order', $purchaseOrderUid) as $orderLine) {
            $orderLines[$orderLine->uid->toString()] = $orderLine;
        }

        foreach ($lines as $requested) {
            $poLineUid = $requested['poLineUid'];

            if (!isset($orderLines[$poLineUid])) {
                throw new InvalidArgumentException("\"{$poLineUid}\" is not a line on purchase order \"{$purchaseOrderUid}\".");
            }

            if ($requested['quantity'] <= 0) {
                throw new InvalidArgumentException('Received quantity must be greater than zero.');
            }

            $orderedQuantity = $orderLines[$poLineUid]->quantity;
            $alreadyReceived = $this->receiptLines->totalReceivedFor($poLineUid);
            $remaining = $orderedQuantity - $alreadyReceived;

            if ($requested['quantity'] > $remaining) {
                throw new RuntimeException(
                    "Cannot receive {$requested['quantity']} of line \"{$poLineUid}\" — only {$remaining} remains outstanding."
                );
            }
        }

        $receipt = GoodsReceipt::create($organizationUid, $purchaseOrderUid, $warehouseUid, $createdBy);

        $this->transact(function () use ($organizationUid, $warehouseUid, $lines, $orderLines, $receipt, $order, $createdBy): void {
            $this->receipts->insert($receipt);

            foreach ($lines as $requested) {
                $orderLine = $orderLines[$requested['poLineUid']];

                $receiptLine = GoodsReceiptLine::create(
                    $organizationUid, $receipt->uid->toString(), $requested['poLineUid'], $orderLine->itemUid,
                    $requested['quantity'], $orderLine->unitCode,
                );
                $this->receiptLines->insert($receiptLine);

                $this->inventory->receive(
                    $organizationUid, $warehouseUid, $orderLine->itemUid, $requested['quantity'], $orderLine->unitCode,
                    referenceType: 'purchase_order', referenceUid: $order->uid->toString(),
                    idempotencyKey: $receiptLine->uid->toString(), createdBy: $createdBy,
                );
            }

            $this->recomputeStatus($order, $orderLines);
        });

        return $receipt;
    }

    /**
     * @param array<string, DocumentLine> $orderLines
     */
    private function recomputeStatus(PurchaseOrder $order, array $orderLines): void
    {
        $fullyReceived = true;
        $anyReceived = false;

        foreach ($orderLines as $poLineUid => $orderLine) {
            $received = $this->receiptLines->totalReceivedFor($poLineUid);

            if ($received > 0) {
                $anyReceived = true;
            }

            if ($received < $orderLine->quantity) {
                $fullyReceived = false;
            }
        }

        if ($fullyReceived) {
            $order->status = 'received';
            $order->receivedAt = new \DateTimeImmutable();
        } elseif ($anyReceived) {
            $order->status = 'partially_received';
        }

        $this->orders->save($order);
    }

    private function transact(callable $fn): void
    {
        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $fn();

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
