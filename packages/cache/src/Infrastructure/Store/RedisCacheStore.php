<?php

declare(strict_types=1);

namespace Kontor\Cache\Infrastructure\Store;

use Kontor\SDK\Contracts\CacheStoreInterface;
use Redis;

/**
 * Substage 2.3 "Redis adapter contract" — a real implementation of
 * CacheStoreInterface over ext-redis, not just the contract on paper.
 * Values are PHP-serialized so any value TaggedCache can hold (not just
 * strings) round-trips correctly.
 */
final class RedisCacheStore implements CacheStoreInterface
{
    public function __construct(private readonly Redis $redis)
    {
    }

    public function get(string $rawKey): mixed
    {
        $value = $this->redis->get($rawKey);

        return $value === false ? null : unserialize($value);
    }

    public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void
    {
        $serialized = serialize($value);

        if ($ttlSeconds !== null) {
            $this->redis->setex($rawKey, $ttlSeconds, $serialized);
        } else {
            $this->redis->set($rawKey, $serialized);
        }
    }

    public function delete(string $rawKey): void
    {
        $this->redis->del($rawKey);
    }
}
