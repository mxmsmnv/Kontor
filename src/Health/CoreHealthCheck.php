<?php

declare(strict_types=1);

namespace Kontor\Core\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class CoreHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $backupStoragePath,
    ) {
    }

    public function key(): string
    {
        return 'core';
    }

    public function run(): HealthCheckResult
    {
        try {
            $organizationCount = (int) $this->pdo
                ->query('SELECT COUNT(*) FROM kontor_organizations')
                ->fetchColumn();
            $enabledComponents = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM kontor_components WHERE status = 'enabled'")
                ->fetchColumn();
        } catch (\Throwable $exception) {
            return new HealthCheckResult(
                'critical',
                'Core database tables are not reachable: ' . $exception->getMessage()
            );
        }

        $storageReady = is_dir($this->backupStoragePath)
            ? is_writable($this->backupStoragePath)
            : is_writable(dirname($this->backupStoragePath));

        if (!$storageReady) {
            return new HealthCheckResult(
                'warning',
                'Core database is healthy, but backup storage is not writable.',
                [
                    'organizations' => $organizationCount,
                    'enabledComponents' => $enabledComponents,
                    'backupStorageWritable' => false,
                ]
            );
        }

        return new HealthCheckResult(
            'ok',
            'Core database and backup storage are ready.',
            [
                'organizations' => $organizationCount,
                'enabledComponents' => $enabledComponents,
                'backupStorageWritable' => true,
                'phpVersion' => PHP_VERSION,
            ]
        );
    }
}
