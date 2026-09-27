<?php

declare(strict_types=1);

namespace Kontor\Expenses\Application;

use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * The "approvals" milestone: a single-approver status workflow — draft ->
 * submitted -> approved|rejected -> (if approved) reimbursed, or
 * cancelled any time before a decision is made. Not a multi-step approval
 * chain — nothing in this substage's milestones asks for one.
 */
final class ExpenseWorkflowService
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly ?ExpensePostingInterface $posting = null,
    ) {
    }

    public function submit(string $expenseUid, int $submittedBy): Expense
    {
        $expense = $this->expenses->require($expenseUid);

        if (!$expense->isDraft()) {
            throw new RuntimeException("Expense \"{$expenseUid}\" is not a draft and cannot be submitted.");
        }

        $expense->status = 'submitted';
        $expense->submittedBy = $submittedBy;
        $expense->submittedAt = new \DateTimeImmutable();
        $this->expenses->save($expense);

        return $expense;
    }

    public function approve(string $expenseUid, int $approvedBy): Expense
    {
        $expense = $this->expenses->require($expenseUid);

        if (!$expense->isSubmitted()) {
            throw new RuntimeException("Expense \"{$expenseUid}\" must be submitted before it can be approved.");
        }

        $expense->status = 'approved';
        $expense->approvedBy = $approvedBy;
        $expense->approvedAt = new \DateTimeImmutable();
        $this->expenses->save($expense);

        return $expense;
    }

    public function reject(string $expenseUid, int $approvedBy, string $reason): Expense
    {
        $expense = $this->expenses->require($expenseUid);

        if (!$expense->isSubmitted()) {
            throw new RuntimeException("Expense \"{$expenseUid}\" must be submitted before it can be rejected.");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $expense->status = 'rejected';
        $expense->approvedBy = $approvedBy;
        $expense->rejectedAt = new \DateTimeImmutable();
        $expense->rejectionReason = $reason;
        $this->expenses->save($expense);

        return $expense;
    }

    public function reimburse(string $expenseUid, ?int $createdBy = null): Expense
    {
        $expense = $this->expenses->require($expenseUid);

        if (!$expense->isApproved()) {
            throw new RuntimeException("Expense \"{$expenseUid}\" must be approved before it can be reimbursed.");
        }

        $this->posting?->assertCanPost($expense);
        $expense->status = 'reimbursed';
        $expense->reimbursedAt = new \DateTimeImmutable();
        $this->expenses->save($expense);
        $this->posting?->postReimbursement($expense, $createdBy);

        return $expense;
    }

    public function cancel(string $expenseUid): Expense
    {
        $expense = $this->expenses->require($expenseUid);

        if (!$expense->isCancellable()) {
            throw new RuntimeException("Expense \"{$expenseUid}\" cannot be cancelled from status \"{$expense->status}\".");
        }

        $expense->status = 'cancelled';
        $this->expenses->save($expense);

        return $expense;
    }
}
