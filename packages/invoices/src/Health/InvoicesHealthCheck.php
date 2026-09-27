<?php

declare(strict_types=1);

namespace Kontor\Invoices\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class InvoicesHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'invoices';
    }

    public function run(): HealthCheckResult
    {
        try {
            $invoices = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_invoices WHERE kind = 'invoice' AND archived_at IS NULL")->fetchColumn();
            $overdue = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_invoices WHERE status = 'overdue' AND archived_at IS NULL")->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$invoices} invoice(s), {$overdue} overdue.",
                ['invoices' => $invoices, 'overdue' => $overdue],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Invoices tables are not reachable: {$e->getMessage()}");
        }
    }
}
