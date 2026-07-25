<?php

declare(strict_types=1);

namespace Kontor\Payments\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class PaymentsHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'payments';
    }

    public function run(): HealthCheckResult
    {
        try {
            $confirmed = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_payments WHERE status = 'confirmed' AND archived_at IS NULL")->fetchColumn();
            $activeAllocations = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_payment_allocations WHERE reversed_at IS NULL')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$confirmed} confirmed payment(s), {$activeAllocations} active allocation(s).",
                ['confirmedPayments' => $confirmed, 'activeAllocations' => $activeAllocations],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Payments tables are not reachable: {$e->getMessage()}");
        }
    }
}
