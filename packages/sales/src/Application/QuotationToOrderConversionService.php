<?php

declare(strict_types=1);

namespace Kontor\Sales\Application;

use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;
use RuntimeException;

/**
 * Substage 4.1 "conversion" — kontor.md diagram 17.1: "Accepted? -> Yes:
 * Create sales order". Copies every quotation line into new order lines
 * (new uids, same pricing/tax) rather than referencing the originals, so
 * the order's lines stay intact even if the quotation is later archived.
 */
final class QuotationToOrderConversionService
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly OrderRepository $orders,
        private readonly DocumentLineRepository $lines,
        private readonly SequenceService $sequences,
        private readonly ?\PDO $database = null,
    ) {
    }

    public function convert(string $quotationUid): Order
    {
        $ownsTransaction = $this->database !== null && DatabaseConcurrency::beginWriteTransaction($this->database);
        try {
            $order = $this->convertQuotation($quotationUid);
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $order;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function convertQuotation(string $quotationUid): Order
    {
        $quotation = $this->quotations->require($quotationUid, forUpdate: $this->database !== null);

        if (!$quotation->isAccepted()) {
            throw new RuntimeException(
                "Quotation \"{$quotationUid}\" must be accepted before it can be converted to an order."
            );
        }

        $existingOrder = $this->orders->findByQuotation($quotationUid, forUpdate: $this->database !== null);
        if ($existingOrder !== null) {
            throw new RuntimeException(
                "Quotation \"{$quotationUid}\" was already converted to order \"{$existingOrder->uid}\"."
            );
        }

        $order = Order::create(
            organizationId: $quotation->organizationId,
            customerType: $quotation->customerType,
            customerUid: $quotation->customerUid,
            currencyCode: $quotation->currencyCode,
            contactUid: $quotation->contactUid,
            quotationUid: $quotation->uid->toString(),
        );
        $order->number = $this->sequences->next(
            $quotation->organizationId, 'sales', 'order', prefix: 'SO-', padding: 5, resetPolicy: 'yearly'
        );

        $orderLines = array_map(
            static fn (DocumentLine $line): DocumentLine => DocumentLine::create(
                organizationId: $line->organizationId,
                documentType: 'order',
                documentUid: $order->uid->toString(),
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
            ),
            $this->lines->forDocument('quotation', $quotationUid, $quotation->organizationId),
        );

        $order->applyTotalsFromLines($orderLines);
        $this->orders->save($order);

        foreach ($orderLines as $orderLine) {
            $this->lines->save($orderLine);
        }

        return $order;
    }
}
