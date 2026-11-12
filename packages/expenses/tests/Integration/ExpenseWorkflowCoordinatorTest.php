<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Application\ExpenseWorkflowCoordinator;
use Kontor\Expenses\Application\ExpenseWorkflowService;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
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
    private ChartOfAccountsService $chart;
    private LedgerEntryRepository $ledgerEntries;
    private LedgerLineRepository $ledgerLines;
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
        $accounts = new AccountRepository($this->pdo, $organizations);
        $this->chart = new ChartOfAccountsService($accounts);
        $this->ledgerEntries = new LedgerEntryRepository($this->pdo, $organizations);
        $this->ledgerLines = new LedgerLineRepository($this->pdo, $organizations);
        $posting = new LedgerExpensePostingService(
            $accounts,
            $this->ledgerEntries,
            new LedgerEntryService($accounts, $this->ledgerEntries, $this->ledgerLines),
        );
        $this->coordinator = new ExpenseWorkflowCoordinator(
            $this->expenses,
            new ExpenseWorkflowService($this->expenses, $posting),
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
        $this->chart->createAccount($this->organizationUid, '1200', 'Bank', 'asset', 'EUR');
        $this->chart->createAccount($this->organizationUid, '4000', 'Expenses', 'expense', 'EUR');
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

    public function test_reimbursement_can_be_retried_after_configuring_missing_ledger_accounts(): void
    {
        $expense = $this->draftExpense();
        $uid = $expense->uid->toString();
        $this->coordinator->perform($uid, 'submit', 5);
        $approved = $this->coordinator->perform($uid, 'approve', 9);

        try {
            $this->coordinator->perform($uid, 'reimburse', 9);
            self::fail('Reimbursement requires expense and bank accounts.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('requires ledger accounts', $exception->getMessage());
        }
        self::assertSame('approved', $this->expenses->require($uid)->status);
        self::assertSame('approved', $this->coordinator->currentState($approved));
        self::assertSame(['submit', 'approve'], array_column($this->coordinator->history($approved), 'actionKey'));
        self::assertSame([], $this->ledgerEntries->forOrganization($this->organizationUid));

        $bank = $this->chart->createAccount($this->organizationUid, '1200', 'Bank', 'asset', 'EUR');
        $expenseAccount = $this->chart->createAccount($this->organizationUid, '4000', 'Expenses', 'expense', 'EUR');
        $reimbursed = $this->coordinator->perform($uid, 'reimburse', 9);

        self::assertSame('reimbursed', $this->expenses->require($uid)->status);
        self::assertSame('reimbursed', $this->coordinator->currentState($reimbursed));
        self::assertSame(['submit', 'approve', 'reimburse'], array_column($this->coordinator->history($reimbursed), 'actionKey'));
        $entries = $this->ledgerEntries->forOrganization($this->organizationUid);
        self::assertCount(1, $entries);
        self::assertSame($uid, $entries[0]->referenceUid);
        $lines = $this->ledgerLines->forEntry($entries[0]->uid->toString());
        self::assertSame($expenseAccount->uid->toString(), $lines[0]->accountUid);
        self::assertSame(4500, $lines[0]->debit->amountMinor());
        self::assertSame($bank->uid->toString(), $lines[1]->accountUid);
        self::assertSame(4500, $lines[1]->credit->amountMinor());
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
