# Kontor

Open-source modular ERP, CRM and business operations ecosystem for
ProcessWire. See [`docs/KONTOR-SPECIFICATION.md`](docs/KONTOR-SPECIFICATION.md)
for the canonical architecture specification.

This repository is **Kontor Core** (`kontor/core`) — the service container,
capability/component registries, migrations, audit log, and the single
`ProcessKontor` admin shell that business components register into. It is
also the monorepo staging ground for the ecosystem's other components
while they're still pre-split into separate repositories (spec section 5.6
/ `KontorDev`) — each one is its own Composer package under `packages/`,
depending on `kontor/core`/`kontor/sdk` rather than the other way around,
exactly as it would across separate repos.

## Layout

```text
Kontor.module.php          Core bootstrap module (autoload, singular)
ProcessKontor.module.php   The one public admin Process module
kontor.json                Core's own component manifest
bin/kontor                  CLI: recovery mode, backup create/list, restore
src/                        Kontor\Core\... (Domain, Application, Infrastructure, Admin)
migrations/                 Core schema migrations
packages/sdk/               kontor/sdk — contracts, DTOs, event envelope, value objects
packages/queue/              kontor/queue — jobs, retries, dead-letter queue, CLI worker
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
`import:*`/`export:*` CLI commands aren't wired up yet either — Core has no
import/export providers of its own; those arrive with the first business
component (e.g. Contacts) that registers one.

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

## Other components

- [`packages/queue/`](packages/queue/) — `kontor/queue` (Substage 2.1):
  asynchronous/delayed jobs, retries with backoff, dead-letter queue,
  priorities, progress, and a CLI worker. Registers itself as the `queue`
  capability in Core's `CapabilityRegistry` rather than being depended on
  directly. Has its own `composer.json`, `kontor.json`, tests and
  `docker-compose.test.yml`-based integration tests — see its own README.
- [`packages/files/`](packages/files/) — `kontor/files` (Substage 2.2):
  local private storage, file metadata, signed URLs, versions. Registers
  itself as the `storage` capability. Same independent-package structure
  as `kontor/queue` — see its own README.
- [`packages/cache/`](packages/cache/) — `kontor/cache` (Substage 2.3):
  namespaced, tag-invalidated caching over a swappable store (in-memory,
  ProcessWire, Redis). Registers itself as the `cache` capability. No
  database table — see its own README.
- [`packages/search/`](packages/search/) — `kontor/search` (Substage 2.4):
  provider registry, federated global search, a reusable SQL full-text
  provider, and an indexing queue built on `kontor/queue`. Registers
  itself as the `search` capability. No database table of its own — see
  its own README.

## Status

Stage 0 (SDK contracts), Substage 1.1–1.5 (Core: module bootstrap, core
database schema, Component Manager, backup and recovery, import and
export), and all of Stage 2 (Substage 2.1 Queue, 2.2 Files, 2.3 Cache,
2.4 Search — platform infrastructure) per spec section 36. Not yet
installed against a live ProcessWire instance — see the spec's Definition
of Done (section 38) for what "complete" means for each subsequent
milestone.
