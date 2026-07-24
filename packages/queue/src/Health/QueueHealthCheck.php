<?php

declare(strict_types=1);

namespace Kontor\Queue\Health;

use Kontor\Queue\Infrastructure\Persistence\JobRepositoryInterface;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * kontor.md#3.5 / #9.1 healthChecks(). Surfaces stuck (crashed-worker) jobs
 * as critical, and any dead-letter backlog as a warning.
 */
final class QueueHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly int $stuckThresholdSeconds = 3600,
    ) {
    }

    public function key(): string
    {
        return 'queue';
    }

    public function run(): HealthCheckResult
    {
        $stuck = $this->jobs->stuckReservedCount($this->stuckThresholdSeconds);
        $dead = $this->jobs->deadLetterCount();

        if ($stuck > 0) {
            return new HealthCheckResult(
                'critical',
                "{$stuck} job(s) have been reserved for over {$this->stuckThresholdSeconds}s without finishing.",
                ['stuckCount' => $stuck, 'deadLetterCount' => $dead],
            );
        }

        if ($dead > 0) {
            return new HealthCheckResult(
                'warning',
                "{$dead} job(s) are in the dead-letter queue.",
                ['stuckCount' => 0, 'deadLetterCount' => $dead],
            );
        }

        return new HealthCheckResult('ok', 'Queue is healthy.', ['stuckCount' => 0, 'deadLetterCount' => 0]);
    }
}
