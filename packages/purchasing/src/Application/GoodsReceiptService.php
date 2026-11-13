<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Application;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
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
    private const QUANTITY_SCALE = 6;

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
        return $this->transact(function () use ($organizationUid, $purchaseOrderUid, $warehouseUid, $lines, $createdBy): GoodsReceipt {
            $order = $this->orders->requireForUpdate($purchaseOrderUid, $organizationUid);

            if (!$order->isReceivable()) {
                throw new RuntimeException("Purchase order \"{$purchaseOrderUid}\" cannot be received from status \"{$order->status}\".");
            }

            if ($lines === []) {
                throw new InvalidArgumentException('At least one line is required to record a goods receipt.');
            }

            $orderLines = [];
            foreach ($this->lines->forDocument('purchase_order', $purchaseOrderUid, $organizationUid) as $orderLine) {
                $orderLines[$orderLine->uid->toString()] = $orderLine;
            }

            $requestedTotals = [];
            foreach ($lines as $index => $requested) {
                $poLineUid = $requested['poLineUid'];

                if (!isset($orderLines[$poLineUid])) {
                    throw new InvalidArgumentException("\"{$poLineUid}\" is not a line on purchase order \"{$purchaseOrderUid}\".");
                }

                $quantity = round($requested['quantity'], self::QUANTITY_SCALE);
                if (!is_finite($quantity) || $quantity <= 0) {
                    throw new InvalidArgumentException('Received quantity must be greater than zero.');
                }

                $lines[$index]['quantity'] = $quantity;
                $requestedTotals[$poLineUid] = round(($requestedTotals[$poLineUid] ?? 0.0) + $quantity, self::QUANTITY_SCALE);
            }

            foreach ($requestedTotals as $poLineUid => $quantity) {
                $orderedQuantity = $orderLines[$poLineUid]->quantity;
                $alreadyReceived = $this->receiptLines->totalReceivedFor($poLineUid);
                $remaining = round($orderedQuantity - $alreadyReceived, self::QUANTITY_SCALE);

                if ($quantity > $remaining) {
                    throw new RuntimeException(
                        "Cannot receive {$quantity} of line \"{$poLineUid}\" — only {$remaining} remains outstanding."
                    );
                }
            }

            $receipt = GoodsReceipt::create($organizationUid, $purchaseOrderUid, $warehouseUid, $createdBy);
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

            return $receipt;
        });
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

    private function transact(callable $receive): GoodsReceipt
    {
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $receipt = $receive();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $receipt;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
