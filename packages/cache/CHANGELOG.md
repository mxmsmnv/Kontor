# Changelog

All notable changes to `kontor/cache` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Search is the first production consumer of the cache capability, using a
  dedicated namespace and generation invalidation after indexing.
- First Cache admin vertical: live adapter health, organization-scoped
  namespace workbench, JSON set/get/delete operations, TTLs, tags, and
  generation-based tag or namespace invalidation with dedicated permissions.
- Initial alpha (Substage 2.3): `TaggedCache` (namespaces + tag-based
  invalidation via version counters, over any `CacheStoreInterface`);
  `CacheManager::forNamespace()`; `InMemoryCacheStore`;
  `ProcessWireCacheStore` (delegates to `$wire->cache`); `RedisCacheStore`
  (real `ext-redis`-backed implementation); `CacheHealthCheck`;
  `KontorCache.module.php` registering the `cache` capability into Kontor
  Core.
