<?php

declare(strict_types=1);

namespace Kontor\AI\Health;

use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * A real invariant, not just a count: an approval queue that's been
 * silently backing up is worth surfacing — the same "check for a real
 * anomaly" approach `kontor/entities`'/`kontor/api`'s own health checks
 * use.
 */
final class AIHealthCheck implements HealthCheckInterface
{
    private const STALE_AFTER_DAYS = 7;

    public function __construct(
        private readonly PendingAIActionRepository $pending,
    ) {
    }

    public function key(): string
    {
        return 'ai';
    }

    public function run(): HealthCheckResult
    {
        try {
            $stale = $this->pending->pendingOlderThan((new \DateTimeImmutable())->modify('-'.self::STALE_AFTER_DAYS.' days'));

            if ($stale === []) {
                return new HealthCheckResult(
                    'ok',
                    'No AI actions have awaited approval for more than '.self::STALE_AFTER_DAYS.' days.',
                    ['staleCount' => 0],
                );
            }

            return new HealthCheckResult(
                'warning',
                count($stale)." AI action(s) have awaited approval for more than ".self::STALE_AFTER_DAYS.' days.',
                ['staleCount' => count($stale)],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "AI tables are not reachable: {$e->getMessage()}");
        }
    }
}
