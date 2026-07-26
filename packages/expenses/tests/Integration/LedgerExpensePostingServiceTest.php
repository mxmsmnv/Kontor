<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
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

final class LedgerExpensePostingServiceTest extends DatabaseTestCase
{
    public function test_reimbursement_posts_expense_against_bank_once(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $entries = new LedgerEntryRepository($this->pdo, $organizations);
        $lines = new LedgerLineRepository($this->pdo, $organizations);
        $chart = new ChartOfAccountsService($accounts);
        $bank = $chart->createAccount($this->organizationUid, '1200', 'Bank', 'asset', 'EUR');
        $expenseAccount = $chart->createAccount($this->organizationUid, '4000', 'Expenses', 'expense', 'EUR');
        $posting = new LedgerExpensePostingService(
            $accounts,
            $entries,
            new LedgerEntryService($accounts, $entries, $lines),
        );

        $expenses = new ExpenseRepository($this->pdo, $organizations);
        $workflow = new ExpenseWorkflowService($expenses, $posting);
        $expense = $this->approvedExpense($organizations, $expenses);
        $reimbursed = $workflow->reimburse($expense->uid->toString(), 41);
        $posting->postReimbursement($reimbursed, 41);

        $entry = $entries->findByReference(
            LedgerExpensePostingService::REFERENCE_TYPE,
            $expense->uid->toString(),
        );
        $this->assertNotNull($entry);
        $this->assertSame(41, $entry->createdBy);
        $entryLines = $lines->forEntry($entry->uid->toString());
        $this->assertSame($expenseAccount->uid->toString(), $entryLines[0]->accountUid);
        $this->assertSame(4500, $entryLines[0]->debit->amountMinor());
        $this->assertSame($bank->uid->toString(), $entryLines[1]->accountUid);
        $this->assertSame(4500, $entryLines[1]->credit->amountMinor());
        $this->assertCount(1, $entries->forOrganization($this->organizationUid));
    }

    public function test_missing_accounts_fail_before_reimbursement_state_changes(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $entries = new LedgerEntryRepository($this->pdo, $organizations);
        $lines = new LedgerLineRepository($this->pdo, $organizations);
        $expenses = new ExpenseRepository($this->pdo, $organizations);
        $workflow = new ExpenseWorkflowService(
            $expenses,
            new LedgerExpensePostingService(
                $accounts,
                $entries,
                new LedgerEntryService($accounts, $entries, $lines),
            ),
        );
        $expense = $this->approvedExpense($organizations, $expenses);

        try {
            $workflow->reimburse($expense->uid->toString(), 41);
            $this->fail('Missing ledger accounts should reject reimbursement.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('requires ledger accounts', $exception->getMessage());
        }

        $unchanged = $expenses->require($expense->uid->toString());
        $this->assertSame('approved', $unchanged->status);
        $this->assertNull($unchanged->reimbursedAt);
        $this->assertSame([], $entries->forOrganization($this->organizationUid));
    }

    private function approvedExpense(
        OrganizationRepository $organizations,
        ExpenseRepository $expenses,
    ): Expense {
        $categories = new CategoryRepository($this->pdo, $organizations);
        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $categories->save($category);
        $expense = Expense::create(
            $this->organizationUid,
            $category->uid->toString(),
            'Taxi',
            Money::ofMinor(4500, 'EUR'),
            new \DateTimeImmutable('2026-07-26'),
        );
        $expenses->save($expense);
        $workflow = new ExpenseWorkflowService($expenses);
        $workflow->submit($expense->uid->toString(), 5);

        return $workflow->approve($expense->uid->toString(), 9);
    }
}
