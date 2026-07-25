<?php

declare(strict_types=1);

namespace Kontor\Expenses\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Beyond a plain count, checks a real invariant: no expense should ever
 * have both approved_at and rejected_at set — ExpenseWorkflowService's
 * approve()/reject() are mutually exclusive terminal decisions from
 * 'submitted', so both being non-null means something wrote outside that
 * path.
 */
final class ExpensesHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'expenses';
    }

    public function run(): HealthCheckResult
    {
        try {
            $pendingApproval = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_expenses WHERE status = 'submitted' AND archived_at IS NULL")->fetchColumn();
            $inconsistent = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_expenses WHERE approved_at IS NOT NULL AND rejected_at IS NOT NULL')->fetchColumn();

            if ($inconsistent > 0) {
                return new HealthCheckResult(
                    'critical',
                    "{$inconsistent} expense(s) are marked both approved and rejected.",
                    ['pendingApproval' => $pendingApproval, 'inconsistentDecisions' => $inconsistent],
                );
            }

            return new HealthCheckResult(
                'ok',
                "{$pendingApproval} expense(s) pending approval.",
                ['pendingApproval' => $pendingApproval, 'inconsistentDecisions' => 0],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Expenses tables are not reachable: {$e->getMessage()}");
        }
    }
}
