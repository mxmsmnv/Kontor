<?php

declare(strict_types=1);

namespace Kontor\Cache\Infrastructure\Store;

use Kontor\SDK\Contracts\CacheStoreInterface;

/**
 * Single-process, non-persistent store. Useful standalone for short-lived
 * CLI scripts, and as the reference implementation CacheManager/TaggedCache
 * are tested against.
 */
final class InMemoryCacheStore implements CacheStoreInterface
{
    /**
     * @var array<string, array{value: mixed, expiresAt: int|null}>
     */
    private array $items = [];

    public function get(string $rawKey): mixed
    {
        if (!isset($this->items[$rawKey])) {
            return null;
        }

        $entry = $this->items[$rawKey];

        if ($entry['expiresAt'] !== null && $entry['expiresAt'] < time()) {
            unset($this->items[$rawKey]);

            return null;
        }

        return $entry['value'];
    }

    public function set(string $rawKey, mixed $value, ?int $ttlSeconds): void
    {
        $this->items[$rawKey] = [
            'value' => $value,
            'expiresAt' => $ttlSeconds !== null ? time() + $ttlSeconds : null,
        ];
    }

    public function delete(string $rawKey): void
    {
        unset($this->items[$rawKey]);
    }
}
