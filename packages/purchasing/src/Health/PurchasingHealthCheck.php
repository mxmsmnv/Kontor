<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class PurchasingHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'purchasing';
    }

    public function run(): HealthCheckResult
    {
        try {
            $openOrders = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM kontor_purchasing_orders WHERE status IN ('issued', 'partially_received') AND archived_at IS NULL"
            )->fetchColumn();
            $receipts = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_purchasing_receipts')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$openOrders} open purchase order(s), {$receipts} goods receipt(s) recorded.",
                ['openOrders' => $openOrders, 'receipts' => $receipts],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Purchasing tables are not reachable: {$e->getMessage()}");
        }
    }
}
