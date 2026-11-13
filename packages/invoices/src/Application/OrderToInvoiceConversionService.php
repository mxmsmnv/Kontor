<?php

declare(strict_types=1);

namespace Kontor\Invoices\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;
use RuntimeException;

/**
 * Creates one invoice draft from a confirmed or completed sales order,
 * copying its commercial lines so later order edits cannot mutate the
 * invoice snapshot.
 */
final class OrderToInvoiceConversionService
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly OrderRepository $orders,
        private readonly DocumentLineRepository $lines,
        private readonly ?\PDO $database = null,
    ) {
    }

    public function convert(string $orderUid, ?\DateTimeImmutable $dueDate = null): Invoice
    {
        $ownsTransaction = $this->database !== null && DatabaseConcurrency::beginWriteTransaction($this->database);
        try {
            $invoice = $this->convertOrder($orderUid, $dueDate);
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $invoice;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function convertOrder(string $orderUid, ?\DateTimeImmutable $dueDate): Invoice
    {
        $order = $this->orders->require($orderUid, forUpdate: $this->database !== null);

        if (!in_array($order->orderStatus, ['confirmed', 'completed'], true)) {
            throw new RuntimeException(
                "Order \"{$orderUid}\" must be confirmed or completed before it can be invoiced."
            );
        }

        $existingInvoice = $this->invoices->findByOrder($orderUid, forUpdate: $this->database !== null);
        if ($existingInvoice !== null) {
            throw new RuntimeException(
                "Order \"{$orderUid}\" was already converted to invoice \"{$existingInvoice->uid}\"."
            );
        }

        $orderLines = $this->lines->forDocument('order', $orderUid);
        if ($orderLines === []) {
            throw new RuntimeException("Order \"{$orderUid}\" has no document lines to invoice.");
        }
        foreach ($orderLines as $line) {
            if ($line->organizationId !== $order->organizationId) {
                throw new RuntimeException('Order contains a line outside its organization.');
            }
        }

        $invoice = Invoice::create(
            organizationId: $order->organizationId,
            customerType: $order->customerType,
            customerUid: $order->customerUid,
            currencyCode: $order->currencyCode,
            contactUid: $order->contactUid,
            orderUid: $order->uid->toString(),
            dueDate: $dueDate ?? new \DateTimeImmutable('+14 days'),
        );
        $invoiceLines = array_map(
            static fn (DocumentLine $line): DocumentLine => DocumentLine::create(
                organizationId: $line->organizationId,
                documentType: 'invoice',
                documentUid: $invoice->uid->toString(),
                title: $line->title,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                itemUid: $line->itemUid,
                itemType: $line->itemType,
                sku: $line->sku,
                description: $line->description,
                unitCode: $line->unitCode,
                discountType: $line->discountType,
                discountValue: $line->discountValue,
                taxCode: $line->taxCode,
                taxRate: $line->taxRate,
                sortOrder: $line->sortOrder,
                snapshot: $line->snapshot,
            ),
            $orderLines,
        );
        $invoice->applyTotalsFromLines($invoiceLines);
        $this->invoices->save($invoice);

        foreach ($invoiceLines as $invoiceLine) {
            $this->lines->save($invoiceLine);
        }

        return $invoice;
    }
}
