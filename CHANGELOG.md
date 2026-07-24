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
