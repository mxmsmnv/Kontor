<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Health;

use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class DashboardHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly WidgetRegistry $widgets,
    ) {
    }

    public function key(): string
    {
        return 'dashboard';
    }

    public function run(): HealthCheckResult
    {
        try {
            $dashboards = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_dashboards WHERE archived_at IS NULL')->fetchColumn();
            $registeredWidgets = count($this->widgets->all());

            if ($registeredWidgets === 0) {
                return new HealthCheckResult('warning', 'No widgets are registered.', ['dashboards' => $dashboards, 'registeredWidgets' => 0]);
            }

            return new HealthCheckResult(
                'ok',
                "{$dashboards} dashboard(s), {$registeredWidgets} registered widget(s).",
                ['dashboards' => $dashboards, 'registeredWidgets' => $registeredWidgets],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Dashboard tables are not reachable: {$e->getMessage()}");
        }
    }
}
