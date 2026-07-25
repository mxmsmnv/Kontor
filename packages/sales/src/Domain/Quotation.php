<?php

declare(strict_types=1);

namespace Kontor\Sales\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.1. Status transitions are enforced by
 * QuotationWorkflowService, not here — issuing needs SequenceService (an
 * external dependency) to assign a number, so it can't be a pure domain
 * method.
 */
final class Quotation
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $number,
        public string $customerType,
        public string $customerUid,
        public ?string $contactUid,
        public ?string $dealUid,
        public ?\DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $validUntil,
        public string $documentLanguage,
        public string $currencyCode,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public string $status,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $acceptedAt,
        public ?\DateTimeImmutable $rejectedAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $customerType,
        string $customerUid,
        string $currencyCode,
        ?string $contactUid = null,
        ?string $dealUid = null,
        ?\DateTimeImmutable $validUntil = null,
        string $documentLanguage = 'en',
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            number: null,
            customerType: $customerType,
            customerUid: $customerUid,
            contactUid: $contactUid,
            dealUid: $dealUid,
            issueDate: null,
            validUntil: $validUntil,
            documentLanguage: $documentLanguage,
            currencyCode: strtoupper($currencyCode),
            subtotal: Money::zero($currencyCode),
            discount: Money::zero($currencyCode),
            tax: Money::zero($currencyCode),
            total: Money::zero($currencyCode),
            status: 'draft',
            issuedAt: null,
            acceptedAt: null,
            rejectedAt: null,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['issued', 'sent'], true);
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['accepted', 'rejected', 'expired', 'cancelled'], true);
    }

    /**
     * Recomputes subtotal/tax/total from a set of lines (kontor.md#15.4).
     * Discount here is the document-level discount already folded into
     * each line's own subtotal(); this only aggregates.
     *
     * @param DocumentLine[] $lines
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
    }
}
