# Changelog

All notable changes to `kontor/core` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Service container (`Kontor\Core\Support\Container`).
- Capability registry, event dispatcher, route registry and translation
  registry (`Kontor\Core\Infrastructure\Registry`, `Infrastructure\Events`).
- Component registry and minimal `ComponentManager` boot/enable/disable
  lifecycle.
- Core database schema: organizations, components, migration ledger, audit
  events, sequences, extension metadata, relations.
- `Kontor.module.php` bootstrap module and `ProcessKontor.module.php` admin
  shell.
- Component Manager (Substage 1.3): `ComponentManifest`/`ManifestReader` for
  parsing and validating `kontor.json`; `DependencyChecker` (php/processwire/
  kontor-package requires and conflicts); `LocalDiscovery` for scanning a
  components root; `ZipInstaller` with zip-slip and zip-bomb protection;
  `ComponentRegistryInterface` registry adapter with data-retaining
  `uninstall()`; `ComponentManager` orchestrating install/update/enable/
  disable/uninstall/discover, with a pre-update backup gate
  (`PreUpdateBackupRequiredException`) and `component.*` lifecycle events.
- Fixed `AuditLogger::record()`'s `organizationId` to the internal `BIGINT`
  id the schema actually expects (it was typed as the public uid string).
- Backup and recovery (Substage 1.4): `BackupProviderRegistry`;
  `LocalFilesystemBackupWriter`/`Reader` (local protected storage, JSON
  Lines per table); `CoreBackupProvider` — a real `BackupProviderInterface`
  implementation covering every table Core owns, with checksum-based
  verification and transactional restore; `BackupManager` application
  service (`create()` always exports then verifies in one call, per
  kontor.md#24); `RecoveryModeManager`, a filesystem-marker recovery/
  maintenance flag that works even when the database is mid-restore;
  `bin/kontor` CLI for `backup:create`, `backup:list`, `restore:run` and
  `recovery:enable`/`disable`/`status`.
