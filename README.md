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
bin/kontor                  CLI: recovery mode, backup create/list, restore
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

Integration/migration tests need a real MySQL/MariaDB (see
`docker-compose.test.yml`) and are skipped otherwise:

```bash
docker compose -f docker-compose.test.yml up -d
KONTOR_TEST_DB_DSN="mysql:host=127.0.0.1;port=3399;dbname=kontor_test" \
KONTOR_TEST_DB_USER=kontor KONTOR_TEST_DB_PASS=kontor \
vendor/bin/phpunit
```

## CLI

`bin/kontor` covers the Substage 1.4 "CLI restore" milestone. It talks
directly to MySQL (no ProcessWire bootstrap needed) via environment
variables:

```bash
KONTOR_DB_DSN="mysql:host=127.0.0.1;dbname=kontor" \
KONTOR_DB_USER=kontor KONTOR_DB_PASS=kontor \
KONTOR_BACKUP_DIR=/var/backups/kontor \
bin/kontor backup:create core full org_01
bin/kontor backup:list
bin/kontor restore:run /var/backups/kontor/core-full-<id> core org_01 --dry-run
bin/kontor recovery:status
```

This is not the full command-family CLI framework of spec section 35
(`component:*`, `import:*`, etc.) — that's a later stage. It's just enough
to actually run a backup and a restore from the command line today.

## Status

Stage 0 (SDK contracts) and Substage 1.1–1.4 (module bootstrap, core
database schema, Component Manager, backup and recovery) per spec section
36. Not yet installed against a live ProcessWire instance — see the spec's
Definition of Done (section 38) for what "complete" means for each
subsequent milestone.
