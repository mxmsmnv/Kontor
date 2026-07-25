<?php

declare(strict_types=1);

namespace Kontor\Cache\Tests\Integration;

use Kontor\Cache\Infrastructure\Store\RedisCacheStore;
use PHPUnit\Framework\TestCase;

/**
 * Needs the ext-redis extension and a reachable Redis server. Configure
 * KONTOR_TEST_REDIS_HOST (and optionally KONTOR_TEST_REDIS_PORT, default
 * 6379) to run this for real; otherwise it skips cleanly, same convention
 * as the other packages' KONTOR_TEST_DB_DSN.
 */
final class RedisCacheStoreTest extends TestCase
{
    private \Redis $redis;
    private RedisCacheStore $store;

    protected function setUp(): void
    {
        if (!class_exists(\Redis::class)) {
            $this->markTestSkipped('The ext-redis extension is not loaded.');
        }

        $host = getenv('KONTOR_TEST_REDIS_HOST');

        if ($host === false) {
            $this->markTestSkipped('Set KONTOR_TEST_REDIS_HOST (and optionally _PORT) to a reachable Redis server to run this test.');
        }

        $this->redis = new \Redis();

        try {
            $this->redis->connect($host, (int) (getenv('KONTOR_TEST_REDIS_PORT') ?: 6379), 1.0);
        } catch (\Throwable $e) {
            $this->markTestSkipped("Could not connect to Redis: {$e->getMessage()}");
        }

        $this->store = new RedisCacheStore($this->redis);
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            $this->redis->close();
        }
    }

    public function test_set_then_get_round_trips_arbitrary_values(): void
    {
        $this->store->set('kontor:test:key', ['a' => 1, 'b' => [2, 3]], 60);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $this->store->get('kontor:test:key'));

        $this->redis->del('kontor:test:key');
    }

    public function test_get_returns_null_for_a_missing_key(): void
    {
        $this->assertNull($this->store->get('kontor:test:definitely-missing'));
    }

    public function test_delete_removes_the_key(): void
    {
        $this->store->set('kontor:test:key', 'value', null);
        $this->store->delete('kontor:test:key');

        $this->assertNull($this->store->get('kontor:test:key'));
    }

    public function test_ttl_actually_expires_the_key_in_redis(): void
    {
        $this->store->set('kontor:test:key', 'value', 1);

        $this->assertSame('value', $this->store->get('kontor:test:key'));
        $this->assertGreaterThan(0, $this->redis->ttl('kontor:test:key'));
    }
}
