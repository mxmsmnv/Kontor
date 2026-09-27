<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class HealthCheckRunner
{
    /**
     * @param iterable<HealthCheckInterface> $checks
     * @return array<int, array{key: string, result: HealthCheckResult}>
     */
    public function run(iterable $checks): array
    {
        $results = [];

        foreach ($checks as $check) {
            try {
                $result = $check->run();
            } catch (\Throwable $exception) {
                $result = new HealthCheckResult(
                    'critical',
                    'Health check failed unexpectedly: ' . $exception->getMessage()
                );
            }

            $results[] = ['key' => $check->key(), 'result' => $result];
        }

        return $results;
    }
}
