<?php

declare(strict_types=1);

namespace Kontor\Cache\Infrastructure\Store;

use Kontor\SDK\Contracts\CacheStoreInterface;
use ProcessWire\WireCache;

/**
 * Substage 2.3 "ProcessWire adapter" — delegates to ProcessWire's own
 * $cache (WireCache) API rather than a separate cache table/files, so
 * Kontor cache entries share PW's existing cache backend and admin
 * "Clear caches" tooling.
 */
final class ProcessWireCacheStore implements CacheStoreInterface
{
    public function __construct(private readonly WireCache $cache)
    {
    }

    public function get(string $rawKey): mixed
    {
        return $this->cache->get($rawKey);
    }

    public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void
    {
        $this->cache->save($rawKey, $value, $ttlSeconds ?? WireCache::expireNever);
    }

    public function delete(string $rawKey): void
    {
        $this->cache->delete($rawKey);
    }
}
