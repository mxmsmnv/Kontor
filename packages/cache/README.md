# Kontor Cache

`kontor/cache` — namespaced caching with tag-based invalidation over a
swappable store. Implements `Kontor\SDK\Contracts\CacheInterface` (a
contract this SDK invents itself — codex rule #15 references "Kontor Cache
contracts" but the specification never defines the interface's shape, same
situation as `JobInterface`) and registers itself as the `cache` capability
in Kontor Core's `CapabilityRegistry`.

Separate component from Kontor Core (spec section 5.3), same independent-
package structure as `kontor/queue` and `kontor/files`. Unlike those two,
it has **no database table** — cache entries aren't a record of truth
(`kontor.md#11` lists no `kontor_cache` table), so there's nothing to
migrate.

## How invalidation works

`TaggedCache` uses the "version counter" technique rather than tracking
which keys belong to which tag: a tag or namespace is a small counter
embedded into every real key computed from it. Flushing a tag or namespace
just increments its counter — every key computed against the old value
becomes unreachable, with no enumeration or pattern-delete needed. That's
what makes it portable across any plain get/set/delete store, including
Redis and ProcessWire's own cache.

One consequence: **the same `$tags` must be passed on both `set()` and
`get()`/`has()`/`delete()`** — tags are the routing key that determines
which "generation" of a value is current, not metadata attached after the
fact. This matches how e.g. Laravel's tagged cache works.

## Contents

- `src/Infrastructure/TaggedCache.php` — the namespace/tag/invalidation
  logic, over any `CacheStoreInterface`.
- `src/Infrastructure/CacheManager.php` — `forNamespace(string): CacheInterface`
  (Substage 2.3 "namespaces"). Every component should use its own
  namespace (typically its component name) so short keys never collide.
- `src/Infrastructure/Store/` — `InMemoryCacheStore` (single-process,
  the reference implementation TaggedCache is tested against),
  `ProcessWireCacheStore` (Substage 2.3 "ProcessWire adapter" — delegates
  to `$wire->cache`/`WireCache`), `RedisCacheStore` (Substage 2.3 "Redis
  adapter contract" — a real `ext-redis`-backed implementation, not just
  the interface on paper).
- `src/Health/CacheHealthCheck.php` — a real write/read/delete round-trip
  against the configured store.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`RedisCacheStoreTest` needs `ext-redis` and a reachable server — set
`KONTOR_TEST_REDIS_HOST` (and optionally `KONTOR_TEST_REDIS_PORT`, default
6379) to run it for real; it skips cleanly otherwise. `ProcessWireCacheStore`
isn't unit-tested directly (same as `Kontor.module.php`/`ProcessKontor.module.php`
elsewhere in this repo) — it needs a real ProcessWire instance.
