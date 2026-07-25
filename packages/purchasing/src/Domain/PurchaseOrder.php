<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * Status transitions live in PurchaseOrderWorkflowService/
 * GoodsReceiptService, not here — issuing needs SequenceService and
 * receiving needs kontor/inventory, same split as every other numbered
 * document in this monorepo.
 */
final class PurchaseOrder
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $number,
        public string $supplierUid,
        public ?string $warehouseUid,
        public ?\DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $expectedDate,
        public string $currencyCode,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public string $status,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $receivedAt,
        public ?\DateTimeImmutable $cancelledAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $supplierUid,
        string $currencyCode,
        ?string $warehouseUid = null,
        ?\DateTimeImmutable $expectedDate = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            number: null,
            supplierUid: $supplierUid,
            warehouseUid: $warehouseUid,
            issueDate: null,
            expectedDate: $expectedDate,
            currencyCode: strtoupper($currencyCode),
            subtotal: Money::zero($currencyCode),
            discount: Money::zero($currencyCode),
            tax: Money::zero($currencyCode),
            total: Money::zero($currencyCode),
            status: 'draft',
            issuedAt: null,
            receivedAt: null,
            cancelledAt: null,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['issued', 'partially_received'], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'partially_received'], true);
    }

    /**
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
    }
}
