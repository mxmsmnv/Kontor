<?php

declare(strict_types=1);

namespace Kontor\Demo\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class DemoHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'demo.storage';
    }

    public function run(): HealthCheckResult
    {
        try {
            $this->pdo->query('SELECT COUNT(*) FROM kontor_demo_scenarios')->fetchColumn();

            return new HealthCheckResult('ok', 'Demo scenario storage is available.');
        } catch (\Throwable $exception) {
            return new HealthCheckResult(
                'critical',
                'Demo scenario storage is not reachable: ' . $exception->getMessage(),
            );
        }
    }
}
