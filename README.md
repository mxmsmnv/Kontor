# Kontor

Open-source modular ERP, CRM and business operations ecosystem for
ProcessWire. See [`docs/KONTOR-SPECIFICATION.md`](docs/KONTOR-SPECIFICATION.md)
for the canonical architecture specification.

This repository is **Kontor Core** (`kontor/core`) — the service container,
capability/component registries, migrations, audit log, and the single
`ProcessKontor` admin shell that business components register into. It is
also the monorepo staging ground for `kontor/sdk` while the ecosystem is
still pre-split into separate repositories (spec section 5.6 / `KontorDev`).

## Layout

```text
Kontor.module.php          Core bootstrap module (autoload, singular)
ProcessKontor.module.php   The one public admin Process module
kontor.json                Core's own component manifest
src/                        Kontor\Core\... (Domain, Application, Infrastructure, Admin)
migrations/                 Core schema migrations
packages/sdk/               kontor/sdk — contracts, DTOs, event envelope, value objects
tests/                       Core unit/integration/migration tests
```

## Development

```bash
composer install
vendor/bin/phpunit
```

`packages/sdk` is wired in via a Composer path repository, so `kontor/sdk`
resolves to the local copy without needing a Packagist release.

## Status

Stage 0 (SDK contracts) and Substage 1.1–1.2 (module bootstrap, core
database schema) per spec section 36. Not yet installed against a live
ProcessWire instance — see the spec's Definition of Done (section 38) for
what "complete" means for each subsequent milestone.
