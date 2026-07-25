<?php

declare(strict_types=1);

namespace Kontor\Expenses\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * Status transitions live in ExpenseWorkflowService, not here — the
 * "approvals" milestone is a single-approver status workflow: draft ->
 * submitted -> approved|rejected -> (if approved) reimbursed, or
 * cancelled before any approval decision.
 */
final class Expense
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $categoryUid,
        public ?string $supplierUid,
        public string $description,
        public Money $amount,
        public \DateTimeImmutable $expenseDate,
        public ?string $receiptFileUid,
        public string $status,
        public ?int $submittedBy,
        public ?int $approvedBy,
        public ?\DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $approvedAt,
        public ?\DateTimeImmutable $rejectedAt,
        public ?\DateTimeImmutable $reimbursedAt,
        public ?string $rejectionReason,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $categoryUid,
        string $description,
        Money $amount,
        \DateTimeImmutable $expenseDate,
        ?string $supplierUid = null,
        ?string $receiptFileUid = null,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            categoryUid: $categoryUid,
            supplierUid: $supplierUid,
            description: $description,
            amount: $amount,
            expenseDate: $expenseDate,
            receiptFileUid: $receiptFileUid,
            status: 'draft',
            submittedBy: null,
            approvedBy: null,
            submittedAt: null,
            approvedAt: null,
            rejectedAt: null,
            reimbursedAt: null,
            rejectionReason: null,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }

    public function hasReceipt(): bool
    {
        return $this->receiptFileUid !== null;
    }
}
