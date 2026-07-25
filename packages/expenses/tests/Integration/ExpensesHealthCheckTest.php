<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Application\ExpenseWorkflowService;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Health\ExpensesHealthCheck;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\SDK\ValueObjects\Money;

final class ExpensesHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_pending_approval_count(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo, $organizations);
        $expenses = new ExpenseRepository($this->pdo, $organizations);
        $workflow = new ExpenseWorkflowService($expenses);

        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $categories->save($category);

        $expense = Expense::create($this->organizationUid, $category->uid->toString(), 'Taxi', Money::ofMinor(4500, 'EUR'), new \DateTimeImmutable('2026-01-01'));
        $expenses->save($expense);
        $workflow->submit($expense->uid->toString(), submittedBy: 5);

        $result = (new ExpensesHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['pendingApproval']);
        $this->assertSame(0, $result->details['inconsistentDecisions']);
    }

    public function test_critical_when_an_expense_is_both_approved_and_rejected(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo, $organizations);
        $expenses = new ExpenseRepository($this->pdo, $organizations);

        $category = ExpenseCategory::create($this->organizationUid, 'TRAVEL', 'Travel');
        $categories->save($category);

        $expense = Expense::create($this->organizationUid, $category->uid->toString(), 'Taxi', Money::ofMinor(4500, 'EUR'), new \DateTimeImmutable('2026-01-01'));
        $expense->approvedAt = new \DateTimeImmutable();
        $expense->rejectedAt = new \DateTimeImmutable();
        $expenses->save($expense);

        $result = (new ExpensesHealthCheck($this->pdo))->run();

        $this->assertSame('critical', $result->status);
        $this->assertSame(1, $result->details['inconsistentDecisions']);
    }
}
