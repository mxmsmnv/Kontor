# Changelog

All notable changes to `kontor/search` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

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
