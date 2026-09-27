<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Application\ExpenseWorkflowService;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\SDK\ValueObjects\Money;

final class ExpenseWorkflowServiceTest extends DatabaseTestCase
{
    private ExpenseRepository $expenses;
    private ExpenseWorkflowService $workflow;
    private string $categoryUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo, $organizations);
        $this->expenses = new ExpenseRepository($this->pdo, $organizations);
        $this->workflow = new ExpenseWorkflowService($this->expenses);

        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $categories->save($category);
        $this->categoryUid = $category->uid->toString();
    }

    private function draftExpense(): Expense
    {
        $expense = Expense::create($this->organizationUid, $this->categoryUid, 'Taxi', Money::ofMinor(4500, 'EUR'), new \DateTimeImmutable('2026-01-01'));
        $this->expenses->save($expense);

        return $expense;
    }

    public function test_submit_then_approve(): void
    {
        $expense = $this->draftExpense();

        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);
        $approved = $this->workflow->approve($expense->uid->toString(), approvedBy: 9);

        $this->assertSame('approved', $approved->status);
        $this->assertSame(9, $approved->approvedBy);
        $this->assertNotNull($approved->approvedAt);
    }

    public function test_submit_then_reject_requires_a_reason(): void
    {
        $expense = $this->draftExpense();
        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);

        $this->expectException(\InvalidArgumentException::class);
        $this->workflow->reject($expense->uid->toString(), approvedBy: 9, reason: '  ');
    }

    public function test_reject_records_the_reason(): void
    {
        $expense = $this->draftExpense();
        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);

        $rejected = $this->workflow->reject($expense->uid->toString(), approvedBy: 9, reason: 'Missing receipt');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Missing receipt', $rejected->rejectionReason);
    }

    public function test_cannot_approve_a_draft_expense(): void
    {
        $expense = $this->draftExpense();

        $this->expectException(\RuntimeException::class);
        $this->workflow->approve($expense->uid->toString(), approvedBy: 9);
    }

    public function test_reimburse_requires_approval_first(): void
    {
        $expense = $this->draftExpense();
        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);

        $this->expectException(\RuntimeException::class);
        $this->workflow->reimburse($expense->uid->toString());
    }

    public function test_full_lifecycle_to_reimbursed(): void
    {
        $expense = $this->draftExpense();
        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);
        $this->workflow->approve($expense->uid->toString(), approvedBy: 9);

        $reimbursed = $this->workflow->reimburse($expense->uid->toString());

        $this->assertSame('reimbursed', $reimbursed->status);
        $this->assertNotNull($reimbursed->reimbursedAt);
    }

    public function test_cannot_cancel_after_approval(): void
    {
        $expense = $this->draftExpense();
        $this->workflow->submit($expense->uid->toString(), submittedBy: 5);
        $this->workflow->approve($expense->uid->toString(), approvedBy: 9);

        $this->expectException(\RuntimeException::class);
        $this->workflow->cancel($expense->uid->toString());
    }

    public function test_cancel_a_draft(): void
    {
        $expense = $this->draftExpense();

        $cancelled = $this->workflow->cancel($expense->uid->toString());

        $this->assertSame('cancelled', $cancelled->status);
    }
}
