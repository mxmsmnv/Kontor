<?php

declare(strict_types=1);

namespace Kontor\Sales\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class SalesHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'sales';
    }

    public function run(): HealthCheckResult
    {
        try {
            $quotations = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_sales_quotations WHERE archived_at IS NULL')->fetchColumn();
            $openOrders = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_sales_orders WHERE order_status IN ('pending', 'confirmed') AND archived_at IS NULL")->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$quotations} quotation(s), {$openOrders} open order(s).",
                ['quotations' => $quotations, 'openOrders' => $openOrders],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Sales tables are not reachable: {$e->getMessage()}");
        }
    }
}
