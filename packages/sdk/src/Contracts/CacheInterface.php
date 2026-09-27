<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

/**
 * The public cache capability contract (codex rule #15 forbids one-off
 * caching systems outside Kontor Cache contracts, but the specification
 * never defines this interface's shape — like JobInterface, it's this
 * SDK's own invention to fill that gap).
 *
 * An instance is already scoped to one namespace (kontor.md Substage 2.3
 * "namespaces") — obtained via KontorCache's `CacheManager::forNamespace()`,
 * not part of this interface itself.
 *
 * Tag-based invalidation (Substage 2.3 "tags"/"invalidation") requires the
 * *same* $tags to be passed on both write and read: tags are the routing
 * key that determines which "generation" of a value is current, not
 * metadata attached after the fact. Storing a literal `null` value is
 * indistinguishable from a cache miss, same as PSR-16.
 */
interface CacheInterface
{
    public function get(string $key, array $tags = [], mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttlSeconds = null, array $tags = []): void;

    public function has(string $key, array $tags = []): bool;

    public function delete(string $key, array $tags = []): void;

    /**
     * Returns the cached value for $key/$tags, computing and storing it via
     * $factory on a miss.
     */
    public function remember(string $key, callable $factory, ?int $ttlSeconds = null, array $tags = []): mixed;

    /**
     * Invalidates every entry stored under $tag, in this namespace, without
     * needing to enumerate or delete individual keys.
     */
    public function flushTag(string $tag): void;

    /**
     * Invalidates every entry in this namespace.
     */
    public function flushNamespace(): void;
}
