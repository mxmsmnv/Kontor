<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Application\ExpenseWorkflowCoordinator;
use Kontor\Expenses\Application\ExpenseWorkflowService;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;

final class ExpenseWorkflowCoordinatorTest extends DatabaseTestCase
{
    private ExpenseRepository $expenses;
    private ExpenseWorkflowCoordinator $coordinator;
    private ApprovalRequestRepository $approvals;
    private string $categoryUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo, $organizations);
        $this->expenses = new ExpenseRepository($this->pdo, $organizations);
        $definitions = new DefinitionRepository($this->pdo, $organizations);
        $transitions = new TransitionRepository($this->pdo, $organizations);
        $instances = new InstanceRepository($this->pdo, $organizations);
        $this->approvals = new ApprovalRequestRepository($this->pdo, $organizations);
        $history = new HistoryRepository($this->pdo, $organizations);
        $engine = new WorkflowEngine($definitions, $transitions, $instances, $this->approvals, $history);
        $this->coordinator = new ExpenseWorkflowCoordinator(
            $this->expenses,
            new ExpenseWorkflowService($this->expenses),
            $definitions,
            new WorkflowDefinitionService($definitions, $transitions),
            $instances,
            $engine,
        );

        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $categories->save($category);
        $this->categoryUid = $category->uid->toString();
    }

    public function test_expense_lifecycle_is_mirrored_with_approval_and_history(): void
    {
        $expense = $this->draftExpense();

        $submitted = $this->coordinator->perform($expense->uid->toString(), 'submit', 5);
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('submitted', $this->coordinator->currentState($submitted));

        $approved = $this->coordinator->perform($expense->uid->toString(), 'approve', 9);
        $this->assertSame('approved', $approved->status);
        $this->assertSame('approved', $this->coordinator->currentState($approved));
        $approval = $this->approvals->pendingFor($this->organizationUid);
        $this->assertSame([], $approval);
        $decidedCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM kontor_workflow_approval_requests WHERE status = 'approved'"
        )->fetchColumn();
        $this->assertSame(1, $decidedCount);

        $reimbursed = $this->coordinator->perform($expense->uid->toString(), 'reimburse', 9);
        $this->assertSame('reimbursed', $reimbursed->status);
        $this->assertSame('reimbursed', $this->coordinator->currentState($reimbursed));
        $history = $this->coordinator->history($reimbursed);
        $this->assertSame(['submit', 'approve', 'reimburse'], array_column($history, 'actionKey'));
        $this->assertSame('expenses', $history[0]->metadata['component']);
    }

    public function test_rejection_reason_is_kept_in_expense_and_workflow_history(): void
    {
        $expense = $this->draftExpense();
        $this->coordinator->perform($expense->uid->toString(), 'submit', 5);

        $rejected = $this->coordinator->perform(
            $expense->uid->toString(),
            'reject',
            9,
            'Receipt is unreadable',
        );

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('rejected', $this->coordinator->currentState($rejected));
        $history = $this->coordinator->history($rejected);
        $this->assertSame('Receipt is unreadable', $history[1]->metadata['reason']);
    }

    private function draftExpense(): Expense
    {
        $expense = Expense::create(
            $this->organizationUid,
            $this->categoryUid,
            'Taxi',
            Money::ofMinor(4500, 'EUR'),
            new \DateTimeImmutable('2026-01-01'),
        );
        $this->expenses->save($expense);

        return $expense;
    }
}
