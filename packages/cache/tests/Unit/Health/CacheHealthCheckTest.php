<?php

declare(strict_types=1);

namespace Kontor\Cache\Tests\Unit\Health;

use Kontor\Cache\Health\CacheHealthCheck;
use Kontor\Cache\Infrastructure\Store\InMemoryCacheStore;
use Kontor\SDK\Contracts\CacheStoreInterface;
use PHPUnit\Framework\TestCase;

final class CacheHealthCheckTest extends TestCase
{
    public function test_ok_when_store_round_trips_correctly(): void
    {
        $result = (new CacheHealthCheck(new InMemoryCacheStore()))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_critical_when_store_throws(): void
    {
        $store = new class implements CacheStoreInterface {
            public function get(string $rawKey): mixed
            {
                return null;
            }

            public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void
            {
                throw new \RuntimeException('store unreachable');
            }

            public function delete(string $rawKey): void
            {
            }
        };

        $result = (new CacheHealthCheck($store))->run();

        $this->assertSame('critical', $result->status);
        $this->assertStringContainsString('store unreachable', $result->message);
    }

    public function test_critical_when_read_back_value_does_not_match(): void
    {
        $store = new class implements CacheStoreInterface {
            public function get(string $rawKey): mixed
            {
                return 'wrong-value';
            }

            public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void
            {
            }

            public function delete(string $rawKey): void
            {
            }
        };

        $result = (new CacheHealthCheck($store))->run();

        $this->assertSame('critical', $result->status);
    }
}
