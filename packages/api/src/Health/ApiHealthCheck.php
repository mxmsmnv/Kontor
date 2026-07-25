<?php

declare(strict_types=1);

namespace Kontor\API\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Flags webhook subscriptions that got disabled after repeated delivery
 * failures (kontor.md#20.11 "disable after repeated permanent failures")
 * and deliveries stuck in `exhausted` — the same "check for a real
 * anomaly, not just a row count" approach `kontor/entities`'
 * `EntitiesHealthCheck` uses.
 */
final class ApiHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function key(): string
    {
        return 'api';
    }

    public function run(): HealthCheckResult
    {
        try {
            $disabledSubscriptions = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM kontor_webhook_subscriptions WHERE status = 'disabled' AND archived_at IS NULL")
                ->fetchColumn();

            $exhaustedDeliveries = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM kontor_webhook_deliveries WHERE status = 'exhausted'")
                ->fetchColumn();

            if ($disabledSubscriptions === 0 && $exhaustedDeliveries === 0) {
                return new HealthCheckResult(
                    'ok',
                    'No disabled webhook subscriptions or exhausted deliveries.',
                    ['disabledSubscriptions' => 0, 'exhaustedDeliveries' => 0],
                );
            }

            return new HealthCheckResult(
                'warning',
                "{$disabledSubscriptions} webhook subscription(s) disabled, {$exhaustedDeliveries} delivery(ies) exhausted their retries.",
                ['disabledSubscriptions' => $disabledSubscriptions, 'exhaustedDeliveries' => $exhaustedDeliveries],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "API tables are not reachable: {$e->getMessage()}");
        }
    }
}
