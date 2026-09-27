<?php

declare(strict_types=1);

namespace Kontor\Cache\Health;

use Kontor\SDK\Contracts\CacheStoreInterface;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Proves the configured store is actually writable/readable with a real
 * round-trip, rather than just checking configuration exists.
 */
final class CacheHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly CacheStoreInterface $store)
    {
    }

    public function key(): string
    {
        return 'cache';
    }

    public function run(): HealthCheckResult
    {
        $probeKey = 'kontor:cache:__health_check__:' . bin2hex(random_bytes(8));
        $probeValue = bin2hex(random_bytes(8));

        try {
            $this->store->set($probeKey, $probeValue, 60);
            $readBack = $this->store->get($probeKey);
            $this->store->delete($probeKey);

            if ($readBack !== $probeValue) {
                return new HealthCheckResult('critical', 'A value was written to the cache store but read back incorrectly.');
            }

            if ($this->store->get($probeKey) !== null) {
                return new HealthCheckResult('warning', 'Cache store delete did not remove the health-check probe key.');
            }

            return new HealthCheckResult('ok', 'Cache store is writable and readable.');
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Cache store is not reachable: {$e->getMessage()}");
        }
    }
}
