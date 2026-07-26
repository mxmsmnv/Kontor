<?php

declare(strict_types=1);

namespace Kontor\Ledger\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Defense in depth beyond `LedgerBalanceValidator`'s write-time check:
 * re-derives whether every entry's lines actually still balance directly
 * from the database, catching any corruption a bug (or a direct SQL
 * write bypassing the service layer) could otherwise introduce silently
 * — the same "check for a real anomaly, not just a count" approach
 * `kontor/entities`'/`kontor/api`'s own health checks use, taken to its
 * most literal form for a ledger.
 */
final class LedgerHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function key(): string
    {
        return 'ledger';
    }

    public function run(): HealthCheckResult
    {
        try {
            $statement = $this->pdo->query(
                'SELECT entry_uid, currency_code, SUM(debit_minor) AS total_debit, SUM(credit_minor) AS total_credit
                 FROM kontor_ledger_lines
                 GROUP BY entry_uid, currency_code
                 HAVING total_debit <> total_credit'
            );
            $unbalanced = $statement->fetchAll(\PDO::FETCH_ASSOC);

            if ($unbalanced === []) {
                return new HealthCheckResult('ok', 'Every ledger entry balances.', ['unbalancedEntries' => 0]);
            }

            return new HealthCheckResult(
                'critical',
                count($unbalanced).' ledger entry(ies) do not balance.',
                ['unbalancedEntries' => count($unbalanced)],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Ledger tables are not reachable: {$e->getMessage()}");
        }
    }
}
