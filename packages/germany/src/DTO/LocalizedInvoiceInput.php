<?php

declare(strict_types=1);

namespace Kontor\Germany\DTO;

use Kontor\SDK\ValueObjects\Money;

/**
 * The generic "invoice-like" data contract `XRechnungFormatter` (and any
 * future country document formatter) operates on — not
 * `kontor/invoices`'s own `Invoice` domain object. A caller in
 * `kontor/invoices`, `kontor/sales`, or an ad-hoc script builds one of
 * these from whatever it has; this package stays decoupled from any
 * particular business component's internal shape.
 */
final class LocalizedInvoiceInput
{
    /**
     * @param LocalizedLineItemInput[] $lineItems
     */
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly \DateTimeImmutable $issueDate,
        public readonly ?\DateTimeImmutable $dueDate,
        public readonly string $currencyCode,
        public readonly LocalizedPartyInput $seller,
        public readonly LocalizedPartyInput $buyer,
        public readonly array $lineItems,
        public readonly Money $totalNet,
        public readonly Money $totalTax,
        public readonly Money $totalGross,
    ) {
    }
}
