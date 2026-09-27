<?php

declare(strict_types=1);

namespace Kontor\Inventory\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Beyond a plain count, this checks a real invariant: every balance row's
 * quantity_available must equal quantity_on_hand - quantity_reserved.
 * BalanceRepository::updateQuantities() always keeps them in sync, so any
 * mismatch means a write happened outside that path (a bug, or a direct
 * DB edit) — this is exactly the kind of drift kontor/payments'
 * "recompute from scratch" allocation logic was designed to prevent for
 * invoices, surfaced here as a health signal instead.
 */
final class InventoryHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'inventory';
    }

    public function run(): HealthCheckResult
    {
        try {
            $warehouses = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_inventory_warehouses WHERE status = 'active'")->fetchColumn();
            $inconsistent = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM kontor_inventory_balances WHERE quantity_available <> quantity_on_hand - quantity_reserved'
            )->fetchColumn();

            if ($inconsistent > 0) {
                return new HealthCheckResult(
                    'critical',
                    "{$inconsistent} balance row(s) have quantity_available out of sync with on_hand - reserved.",
                    ['activeWarehouses' => $warehouses, 'inconsistentBalances' => $inconsistent],
                );
            }

            return new HealthCheckResult(
                'ok',
                "{$warehouses} active warehouse(s), balances consistent.",
                ['activeWarehouses' => $warehouses, 'inconsistentBalances' => 0],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Inventory tables are not reachable: {$e->getMessage()}");
        }
    }
}
