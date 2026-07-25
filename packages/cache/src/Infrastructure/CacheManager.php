<?php

declare(strict_types=1);

namespace Kontor\Cache\Infrastructure;

use Kontor\SDK\Contracts\CacheInterface;
use Kontor\SDK\Contracts\CacheStoreInterface;

/**
 * Produces a namespaced CacheInterface over a shared store (Substage 2.3
 * "namespaces"). Every component gets its own namespace — typically its
 * component name — so the same short key ("list", "count", ...) used by
 * two different components never collides.
 */
final class CacheManager
{
    public function __construct(private readonly CacheStoreInterface $store)
    {
    }

    public function forNamespace(string $namespace): CacheInterface
    {
        return new TaggedCache($this->store, $namespace);
    }
}
