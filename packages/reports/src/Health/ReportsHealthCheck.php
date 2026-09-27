<?php

declare(strict_types=1);

namespace Kontor\Reports\Health;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class ReportsHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ReportProviderRegistry $providers,
    ) {
    }

    public function key(): string
    {
        return 'reports';
    }

    public function run(): HealthCheckResult
    {
        try {
            $scheduled = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_scheduled_reports WHERE archived_at IS NULL')->fetchColumn();
            $registeredProviders = count($this->providers->all());

            if ($registeredProviders === 0) {
                return new HealthCheckResult('warning', 'No report providers are registered.', ['scheduledReports' => $scheduled, 'registeredProviders' => 0]);
            }

            return new HealthCheckResult(
                'ok',
                "{$scheduled} scheduled report(s), {$registeredProviders} registered provider(s).",
                ['scheduledReports' => $scheduled, 'registeredProviders' => $registeredProviders],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Reports tables are not reachable: {$e->getMessage()}");
        }
    }
}
