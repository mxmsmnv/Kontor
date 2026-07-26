# Development

## Setup

```bash
composer install
vendor/bin/phpunit
```

`packages/sdk` (and every other sibling package a given package depends
on) is wired in via Composer path repositories, so `kontor/sdk`,
`kontor/core`, etc. resolve to the local copy without needing a
Packagist release. Each package under `packages/` is developed the same
way, from its own directory:

```bash
cd packages/<name>
composer install
vendor/bin/phpunit
```

## Integration tests

Integration/migration tests need a real MySQL/MariaDB and are skipped
cleanly otherwise:

```bash
docker compose -f docker-compose.test.yml up -d
KONTOR_TEST_DB_DSN="mysql:host=127.0.0.1;port=3399;dbname=kontor_test" \
KONTOR_TEST_DB_USER=kontor KONTOR_TEST_DB_PASS=kontor \
vendor/bin/phpunit
```

Every package's own DB-gated tests follow the same `KONTOR_TEST_DB_DSN`
convention, and most extend the shared
`Kontor\Core\Testing\DatabaseTestCase` (connect, drop tables, run
migrations, seed a default organization, clean up) rather than
hand-rolling that boilerplate.

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
`import:*`/`export:*` CLI commands aren't wired up yet either — Core has no
import/export providers of its own; those arrive with the first business
component (e.g. Contacts) that registers one.

`bin/kontor-make` (in `packages/sdk/`) covers Substage 7.4's
`make:component`/`make:entity`/`make:migration`/`make:report`
scaffolding commands — see
[`packages/sdk/docs/COMPONENT-GUIDE.md`](../packages/sdk/docs/COMPONENT-GUIDE.md).

## Import and export

`ImportManager` and `ExportManager` (`src/Application/`) are format-agnostic:
components register an `ImportProviderInterface`/`ExportProviderInterface`
per entity type, and the manager handles field mapping, dry-run preview,
progress events, and — via `RepositoryRegistry` — batch rollback (archiving
every record a batch created or updated, found through the audit ledger's
`correlation_id`).

Supported formats (`src/Infrastructure/ImportExport/Format/`): CSV, JSON,
JSON Lines (streaming), and XLSX. The XLSX reader/writer are hand-rolled
(`ZipArchive` + `DOMDocument`, no Composer dependency) rather than a
third-party library, specifically to avoid pinning this package to a
narrower PHP-version range than the rest of Kontor — see the reader's and
writer's doc comments for what's deliberately out of scope (styles,
formulas, multiple sheets). The writer produces inline-string cells; the
reader also handles shared strings, so files from real spreadsheet software
read correctly, not just round-trips of our own output.

A live (non-dry-run) import is gated behind a verified pre-import backup,
the same pattern `ComponentManager::update()` uses (see
`PreImportBackupRequiredException` / `PreUpdateBackupRequiredException`).
