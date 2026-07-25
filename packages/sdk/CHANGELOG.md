# Changelog

All notable changes to `kontor/sdk` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [0.2.1] - Unreleased

### Added

- `CacheInterface` and `CacheStoreInterface` (codex rule #15 references
  "Kontor Cache contracts" but the specification never defines them —
  same situation as `JobInterface`). `kontor/cache` is the first consumer.

## [0.2.0]

### Changed

- `JobInterface::handle()` now takes a second `JobProgressReporterInterface
  $progress` parameter, so a running job can report its own completion
  percentage (kontor.md section 31 "progress"). `JobInterface` had no
  concrete implementation anywhere yet (KontorQueue is the first consumer),
  so this is not a breaking change in practice.

### Added

- `JobProgressReporterInterface`.

## [0.1.0]

### Added

- Initial alpha: component contracts, capability registry contract, event
  dispatcher contract, repository contract, backup/import/export/search/
  report provider contracts, queue contract, storage contract, AI provider
  contract.
- Canonical event envelope (`KontorEvent`).
- Value objects: `Uid`, `Money`, `OrganizationId`.
