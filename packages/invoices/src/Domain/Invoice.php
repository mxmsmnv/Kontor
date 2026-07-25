<?php

declare(strict_types=1);

namespace Kontor\Invoices\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.3. `kind`/`creditedInvoiceUid` are this package's gap-fill
 * (see the migration's doc comment) — a credit note is just an Invoice with
 * kind='credit_note' pointing back at the invoice it credits, not a
 * separate entity. Status transitions live in InvoiceWorkflowService, not
 * here — issuing needs SequenceService, same split as Sales' Quotation.
 */
final class Invoice
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $number,
        public readonly string $kind,
        public readonly ?string $creditedInvoiceUid,
        public string $customerType,
        public string $customerUid,
        public ?string $contactUid,
        public ?string $orderUid,
        public ?\DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $dueDate,
        public string $documentLanguage,
        public string $currencyCode,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public Money $paid,
        public Money $due,
        public string $status,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $sentAt,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $cancelledAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $customerType,
        string $customerUid,
        string $currencyCode,
        ?string $contactUid = null,
        ?string $orderUid = null,
        ?\DateTimeImmutable $dueDate = null,
        string $documentLanguage = 'en',
        string $kind = 'invoice',
        ?string $creditedInvoiceUid = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            number: null,
            kind: $kind,
            creditedInvoiceUid: $creditedInvoiceUid,
            customerType: $customerType,
            customerUid: $customerUid,
            contactUid: $contactUid,
            orderUid: $orderUid,
            issueDate: null,
            dueDate: $dueDate,
            documentLanguage: $documentLanguage,
            currencyCode: strtoupper($currencyCode),
            subtotal: Money::zero($currencyCode),
            discount: Money::zero($currencyCode),
            tax: Money::zero($currencyCode),
            total: Money::zero($currencyCode),
            paid: Money::zero($currencyCode),
            due: Money::zero($currencyCode),
            status: 'draft',
            issuedAt: null,
            sentAt: null,
            paidAt: null,
            cancelledAt: null,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'sent'], true);
    }

    public function isCreditable(): bool
    {
        return $this->kind === 'invoice' && in_array($this->status, ['issued', 'sent', 'overdue', 'paid'], true);
    }

    /**
     * Recomputes subtotal/tax/total from a set of lines (kontor.md#15.4),
     * then keeps `due` in sync with `total - paid` — mirrors Sales'
     * Quotation::applyTotalsFromLines(). `paid` never moves on its own
     * here; Payments (Substage 4.4) is the only future consumer that will
     * mutate it.
     *
     * @param \Kontor\Sales\Domain\DocumentLine[] $lines
     */
    public function applyTotalsFromLines(array $lines): void
    {
        $subtotal = Money::zero($this->currencyCode);
        $tax = Money::zero($this->currencyCode);
        $total = Money::zero($this->currencyCode);

        foreach ($lines as $line) {
            $subtotal = $subtotal->add($line->subtotal());
            $tax = $tax->add($line->taxAmount());
            $total = $total->add($line->total());
        }

        $this->subtotal = $subtotal;
        $this->tax = $tax;
        $this->total = $total;
        $this->due = $total->subtract($this->paid);
    }
}
