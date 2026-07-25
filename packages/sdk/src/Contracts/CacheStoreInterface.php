<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

/**
 * The raw key-value backend a CacheInterface implementation is built on
 * top of (Substage 2.3 "ProcessWire adapter" / "Redis adapter contract").
 * No namespacing, tagging or invalidation logic belongs here — that's
 * CacheInterface's job; a store only ever sees fully-resolved raw keys.
 */
interface CacheStoreInterface
{
    /**
     * @return mixed null if the key is missing or expired
     */
    public function get(string $rawKey): mixed;

    /**
     * @param int|null $ttlSeconds null means "does not expire"
     */
    public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void;

    public function delete(string $rawKey): void;
}
