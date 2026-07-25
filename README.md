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
- [`packages/contacts/`](packages/contacts/) — `kontor/contacts`
  (Substage 3.1): the first business component. Contacts, companies,
  addresses, memberships, tags, duplicate detection, import/export. Unlike
  the platform components, it *consumes* Core/Search rather than providing
  a capability — see its own README.
- [`packages/catalog/`](packages/catalog/) — `kontor/catalog`
  (Substage 3.2): items (products/services via `item_type`), categories
  (another schema gap-fill), price lists with quantity-tier/date-window
  price resolution, units of measure and tax code reference registries.
  First real consumer of the SDK's `Money` value object — see its own
  README.
- [`packages/crm/`](packages/crm/) — `kontor/crm` (Substage 3.3): leads,
  pipelines, stages, deals, lead-to-deal conversion, Kanban board data, and
  a pipeline report. The one component the spec gives a full worked
  manifest example for (section 22.1) — followed as closely as this
  substage's milestones allow. First real consumer of
  `ReportProviderInterface`, which had no registry in Core until now — see
  its own README.
- [`packages/sales/`](packages/sales/) — `kontor/sales` (Substage 4.1):
  quotations, orders, a document-lines table shared with future invoices,
  quotation-to-order conversion, and status workflows. Leanest dependency
  graph so far (`kontor/core` only). First real consumer of the new
  `SequenceService` (document numbering) — see its own README.
- [`packages/documents/`](packages/documents/) — `kontor/documents`
  (Substage 4.2): document templates (versioned like `kontor/files`), a
  small placeholder/loop/conditional template engine ("document designer
  v1"), PDF rendering via `dompdf/dompdf`, multilingual template
  resolution with an English fallback, and immutable issued-document
  snapshots. Same lean dependency graph as Sales (`kontor/core` only) — see
  its own README.
- [`packages/invoices/`](packages/invoices/) — `kontor/invoices`
  (Substage 4.3): invoices, issue workflow, `INV-`-numbered sequencing, the
  overdue state (`Sent → Overdue` sweep), and credit notes (modeled as
  another invoice row via a `kind`/`credited_invoice_uid` gap-fill, not a
  parallel table). First package with a real dependency on another
  business component (`kontor/sales`, reusing its shared
  `kontor_document_lines` table directly) — see its own README.
- [`packages/payments/`](packages/payments/) — `kontor/payments`
  (Substage 4.4): payments, allocations, partial payments, and reversals.
  Depends on `kontor/invoices` and is the first package to actually mutate
  another business component's records — `PaymentAllocationService`
  recomputes an invoice's `paid`/`due`/`status` (from scratch, on every
  allocate/reverse) directly through `kontor/invoices`' own
  `InvoiceRepository`, which is exactly what that package's README left
  "not actively driven" pending this substage. Closes out Stage 4 — see
  its own README.
- [`packages/tasks/`](packages/tasks/) — `kontor/tasks` (Substage 5.1):
  tasks, reminders, recurrence (completing a recurring task spawns its
  next occurrence), a `dueBetween()` query backing the "calendar"
  milestone, and entity relations. First component of Stage 5, and the
  first real consumer of a new `kontor/core` gap-fill,
  `RelationRepository` over `kontor_relations` (existed since Substage 1.2
  with no service) — see its own README.

## Status

Stage 0 (SDK contracts), Substage 1.1–1.5 (Core: module bootstrap, core
database schema, Component Manager, backup and recovery, import and
export), all of Stage 2 (Substage 2.1 Queue, 2.2 Files, 2.3 Cache,
2.4 Search — platform infrastructure), all of Stage 3 (Substage 3.1
Contacts, 3.2 Catalog, 3.3 CRM — foundational business components), all of
Stage 4 (Substage 4.1 Sales, 4.2 Documents, 4.3 Invoices, 4.4 Payments —
sales and finance-lite), and Substage 5.1 (Tasks) per spec section 36. Not
yet installed against a live ProcessWire instance — see the spec's
Definition of Done (section 38) for what "complete" means for each
subsequent milestone.
