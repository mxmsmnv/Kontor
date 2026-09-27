<?php

declare(strict_types=1);

namespace Kontor\Cache\Infrastructure;

use Kontor\SDK\Contracts\CacheInterface;
use Kontor\SDK\Contracts\CacheStoreInterface;

/**
 * Namespace and tag-based invalidation (Substage 2.3) over any
 * CacheStoreInterface, using the "version counter" technique: a tag or
 * namespace isn't tracked as a set of member keys, it's a small counter
 * embedded into every real key computed from it. Flushing a tag or
 * namespace just increments its counter — every key computed against the
 * old counter value becomes unreachable, without enumerating or deleting
 * anything. This works over a plain get/set/delete store (no multi-key
 * transactions, no pattern-delete, no tag index), which is what makes it
 * portable across the ProcessWire, Redis and in-memory adapters alike.
 */
final class TaggedCache implements CacheInterface
{
    private const NAMESPACE_VERSION_PREFIX = 'kontor:cache:__nsv__:';
    private const TAG_VERSION_PREFIX = 'kontor:cache:__tagv__:';

    public function __construct(
        private readonly CacheStoreInterface $store,
        private readonly string $namespace,
    ) {
    }

    public function get(string $key, array $tags = [], mixed $default = null): mixed
    {
        $value = $this->store->get($this->realKey($key, $tags));

        return $value ?? $default;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null, array $tags = []): void
    {
        $this->store->set($this->realKey($key, $tags), $value, $ttlSeconds);
    }

    public function has(string $key, array $tags = []): bool
    {
        return $this->store->get($this->realKey($key, $tags)) !== null;
    }

    public function delete(string $key, array $tags = []): void
    {
        $this->store->delete($this->realKey($key, $tags));
    }

    public function remember(string $key, callable $factory, ?int $ttlSeconds = null, array $tags = []): mixed
    {
        $realKey = $this->realKey($key, $tags);
        $existing = $this->store->get($realKey);

        if ($existing !== null) {
            return $existing;
        }

        $value = $factory();
        $this->store->set($realKey, $value, $ttlSeconds);

        return $value;
    }

    public function flushTag(string $tag): void
    {
        $this->bumpVersion(self::TAG_VERSION_PREFIX . $this->namespace . ':' . $tag);
    }

    public function flushNamespace(): void
    {
        $this->bumpVersion(self::NAMESPACE_VERSION_PREFIX . $this->namespace);
    }

    /**
     * @param string[] $tags
     */
    private function realKey(string $key, array $tags): string
    {
        $namespaceVersion = $this->versionOf(self::NAMESPACE_VERSION_PREFIX . $this->namespace);

        $tagVersions = [];

        foreach ($tags as $tag) {
            $tagVersions[$tag] = $this->versionOf(self::TAG_VERSION_PREFIX . $this->namespace . ':' . $tag);
        }

        ksort($tagVersions);

        $tagSegment = '';

        foreach ($tagVersions as $tag => $version) {
            $tagSegment .= "{$tag}={$version},";
        }

        return "kontor:cache:{$this->namespace}:v{$namespaceVersion}:{$key}:tags[{$tagSegment}]";
    }

    private function versionOf(string $versionKey): int
    {
        return (int) ($this->store->get($versionKey) ?? 0);
    }

    private function bumpVersion(string $versionKey): void
    {
        $this->store->set($versionKey, $this->versionOf($versionKey) + 1, null);
    }
}
