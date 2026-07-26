# Changelog

All notable changes to `kontor/marketplace` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- First ProcessKontor admin vertical: registry source management, deterministic
  JSON synchronization, listing recommendations, publisher trust, and
  advisory visibility.
- Initial alpha (Substage 8.3): `kontor_marketplace_registries`,
  `kontor_marketplace_publishers`, `kontor_marketplace_listings`,
  `kontor_marketplace_advisories` migrations (kontor.md#22, full
  gap-fill, instance-wide like `kontor_components`); `Registry`/
  `Publisher`/`MarketplaceListing`/`Advisory` domain objects;
  `RegistryRepository`/`PublisherRepository`/`ListingRepository`/
  `AdvisoryRepository`; `RegistryClientInterface` +
  `CurlRegistryClient` (the injectable outbound-HTTP boundary, a GET
  fetch, distinct from `kontor/api`'s own POST-only
  `HttpClientInterface`); `RegistrySyncService` (the "official
  registry"/"custom registry"/"component metadata"/"publisher model"
  milestones all at once — one registry payload, parsed with
  `Kontor\Core\Domain\ComponentManifest::fromArray()` directly, ingests
  both components and advisories in one pass; a publisher only ever
  becomes verified via a trusted registry sync; malformed entries are
  skipped, not thrown); `RegistryManagementService` (add/enable/disable
  custom registries; `"official"` is reserved); `AdvisoryService` (the
  "advisories" milestone's query side, reusing
  `Kontor\Core\Support\VersionConstraint::satisfies()` directly);
  `InstallabilityChecker` (combines Core's own `DependencyChecker` with
  `AdvisoryService` to recommend, never install — that stays
  `ComponentManager`'s job); `MarketplaceHealthCheck` (flags a stale
  registry or a listing with an open critical advisory); permissions;
  en/fr/de/es translations. Third and final component of Stage 8 (API
  and external ecosystem), which this substage closes out.
