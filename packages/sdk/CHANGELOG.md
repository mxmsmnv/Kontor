# Changelog

All notable changes to `kontor/sdk` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [0.2.1] - Unreleased

### Added

- `CacheInterface` and `CacheStoreInterface` (codex rule #15 references
  "Kontor Cache contracts" but the specification never defines them —
  same situation as `JobInterface`). `kontor/cache` is the first consumer.
- `Scaffolding/ComponentScaffolder`, `EntityScaffolder`,
  `MigrationScaffolder`, `ReportScaffolder` and the `bin/kontor-make` CLI
  (Substage 7.4's `make:component`/`make:entity`/`make:migration`/
  `make:report` milestones) — pure filesystem generators reproducing the
  directory structure, standard columns, and `RepositoryInterface`/
  `ReportProviderInterface` shapes every hand-built component already
  follows. Supports `--dry-run` and `--non-interactive`; JSON output,
  audit and confirmation flags (kontor.md section 35) are deferred, since
  scaffolding only ever creates new files and never touches existing
  data. `docs/COMPONENT-GUIDE.md` documents the conventions (Substage
  7.4's "documentation" milestone).

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
