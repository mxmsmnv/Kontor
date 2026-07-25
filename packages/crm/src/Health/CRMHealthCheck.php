<?php

declare(strict_types=1);

namespace Kontor\CRM\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Named CRMDatabaseHealthCheck in kontor.md#22.1's canonical example
 * (Kontor\CRM\Health\CRMDatabaseHealthCheck); this component has only one
 * health check, so the class itself is simply CRMHealthCheck.
 */
final class CRMHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'crm';
    }

    public function run(): HealthCheckResult
    {
        try {
            $leads = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_crm_leads WHERE archived_at IS NULL')->fetchColumn();
            $openDeals = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_crm_deals WHERE status = 'open' AND archived_at IS NULL")->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$leads} lead(s), {$openDeals} open deal(s).",
                ['leads' => $leads, 'openDeals' => $openDeals],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "CRM tables are not reachable: {$e->getMessage()}");
        }
    }
}
