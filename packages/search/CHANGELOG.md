# Changelog

All notable changes to `kontor/search` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- Cache federated results as scalar snapshots and rebuild SDK DTOs on read,
  matching WireCache's supported value types.

### Added

- Global search now consumes Kontor Cache: query results are cached for 30
  seconds with organization, filters and pagination in the key, and
  completed indexing jobs invalidate the shared results tag.
- Optional developer-defined SQL conditions for lifecycle-aware full-text
  providers.
- Initial alpha (Substage 2.4): `SearchProviderRegistry`; `GlobalSearchService`
  (federated aggregation across providers, itself a `SearchProviderInterface`);
  `SqlFullTextSearchProvider` (reusable MySQL FULLTEXT provider, resolves
  organization uid to internal id before querying); `ComponentsSearchProvider`
  (real, searches installed components); `SearchIndexerInterface` +
  `SearchIndexerRegistry` (push-based indexing extension point for external
  engines); `SearchIndexJob` + `SearchIndexDispatcher` (indexing queue via
  KontorQueue); `SearchHealthCheck`; `KontorSearch.module.php` registering
  the `search` capability into Kontor Core and the `search.index` job type
  into KontorQueue.
