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
- [`packages/collaboration/`](packages/collaboration/) — `kontor/collaboration`
  (Substage 5.2): notes, comments (with reply threading), mentions
  (`@123`-style parsing), followers (idempotent), and unread states
  (computed from comments since a per-user/per-thread `last_read_at`, not
  a denormalized counter). `CommentService::post()` ties mentions and
  followers together — posting auto-follows the thread for its author.
  Depends only on `kontor/core` — see its own README.
- [`packages/dashboard/`](packages/dashboard/) — `kontor/dashboard`
  (Substage 5.3): a widget registry (`WidgetProviderInterface`, this
  package's own extension point — not an SDK contract, mirroring where
  `kontor/search`'s `SearchProviderRegistry` lives), layouts
  (`kontor_dashboard_widgets`: position/size/config), and personal/role
  dashboards. `DashboardService::dashboardFor()` resolves a user's
  personal default, falling back to their role's default. Ships one
  trivial built-in widget proving the pipeline end-to-end; depends only on
  `kontor/core` — see its own README.
- [`packages/reports/`](packages/reports/) — `kontor/reports`
  (Substage 5.4): the orchestration layer on top of `kontor/core`'s
  already-existing `ReportProviderRegistry` (Substage 3.3) — a report
  builder that validates filters/`groupBy` against each provider's own
  declared schema, chart-ready data mapping, exports (CSV/JSON/XLSX via
  Core's existing format writers, PDF via `kontor/documents`'
  `PdfRenderer`), and scheduled reports (same recurrence math as
  `kontor/tasks`). Closes out Stage 5 — see its own README.
- [`packages/inventory/`](packages/inventory/) — `kontor/inventory`
  (Substage 6.1): warehouses, stock balances, movements, reservations,
  transfers, and barcode support. `InventoryMovementService` implements
  kontor.md diagram 17.3 exactly — validate warehouse, lock balance
  row(s) `FOR UPDATE` in a transaction (consistent lock order for
  transfers), check available stock, update, commit, emit
  `inventory.movement.completed` — idempotent via a repeat-safe
  `idempotencyKey`. First component of Stage 6 (Operations); depends only
  on `kontor/core` — see its own README.
- [`packages/purchasing/`](packages/purchasing/) — `kontor/purchasing`
  (Substage 6.2): suppliers, purchase orders, goods receipt, and inventory
  integration. Depends on both `kontor/sales` (shared document lines) and
  `kontor/inventory` — `GoodsReceiptService::receive()` validates against
  what's still outstanding, records the receipt, and actually calls
  `InventoryMovementService::receive()` per line (not deferred, unlike
  most cross-component wiring elsewhere in this monorepo), all in one
  shared transaction — see its own README.
- [`packages/expenses/`](packages/expenses/) — `kontor/expenses`
  (Substage 6.3): expenses, categories, receipts, and approvals.
  `ExpenseWorkflowService` is a single-approver status workflow (draft →
  submitted → approved/rejected → reimbursed). `receipt_file_uid`/
  `supplier_uid` stay loose references toward `kontor/files`/
  `kontor/purchasing` — depends only on `kontor/core` — see its own
  README.
- [`packages/projects/`](packages/projects/) — `kontor/projects`
  (Substage 6.4): projects, milestones, time tracking, billable items, and
  invoicing integration. Depends on `kontor/sales` and `kontor/invoices` —
  `ProjectInvoicingService::generateInvoice()` actually creates a draft
  invoice from a project's uninvoiced time entries and billable items
  (not deferred, same choice `kontor/purchasing` made for its own
  inventory integration), left in `draft` for review before issuing.
  Closes out Stage 6 — see its own README.
- [`packages/workflow/`](packages/workflow/) — `kontor/workflow`
  (Substage 7.1): state machine, transition permissions, approvals, and
  history. A standalone, entity-agnostic engine (`WorkflowEngine`), not
  retrofitted into any already-shipped component's own hardcoded workflow
  service — kontor.md#18 requires every component to keep a safe default
  workflow even when this package isn't installed. First component of
  Stage 7 (Extensibility); depends only on `kontor/core` — see its own
  README.
- [`packages/automation/`](packages/automation/) — `kontor/automation`
  (Substage 7.2): triggers, conditions, actions, dry run, logs, and
  recursion protection — kontor.md#30's Trigger → Conditions → Actions
  pipeline. Triggers are real Kontor events: `KontorAutomation::init()`
  subscribes `AutomationEngine::handleEvent()` onto `kontor/core`'s actual
  `EventDispatcher` for every distinct active rule's `trigger_event` — a
  genuine integration, not deferred. Recursion protection is a plain
  instance depth counter, since Core's dispatcher is fully synchronous
  with no call-depth concept of its own. Depends only on `kontor/core` —
  see its own README.
- [`packages/entities/`](packages/entities/) — `kontor/entities`
  (Substage 7.3): entity builder, fields, relations, views, permissions,
  and API exposure. Custom entity records live in one generic
  `kontor_entity_records` table (`data_json`), validated for real against
  their definition's declared fields. "Relations" reuses `kontor/core`'s
  `RelationRepository` directly rather than a parallel table — the same
  reuse `kontor/tasks` established. "API exposure" shapes the data
  contract a future Stage 8 REST/GraphQL layer would consume, without
  building endpoints itself yet. Depends only on `kontor/core` — see its
  own README.

Substage 7.4 (SDK and scaffolding) closes out Stage 7 and splits across
two existing packages rather than a new one (spec section 5.6 rules out
`KontorDev` becoming a source repository for component code):

- `src/Testing/DatabaseTestCase.php` (this repository, `kontor/core`) —
  the shared base class for every component's own DB-gated integration
  tests (connect, drop tables, run migrations, seed a default
  organization, clean up), replacing the ~90 lines of boilerplate every
  package from `kontor/sales` onward hand-wrote independently. Lives in
  Core rather than the SDK because it needs `MigrationRunner`/
  `OrganizationRepository`, which themselves depend on `kontor/sdk`.
- [`packages/sdk/`](packages/sdk/)'s `Scaffolding/ComponentScaffolder`,
  `EntityScaffolder`, `MigrationScaffolder`, `ReportScaffolder`, and the
  `bin/kontor-make` CLI (`make:component`/`make:entity`/`make:migration`/
  `make:report`) — pure filesystem generators reproducing this guide's
  own conventions. See
  [`packages/sdk/docs/COMPONENT-GUIDE.md`](packages/sdk/docs/COMPONENT-GUIDE.md).

- [`packages/api/`](packages/api/) — `kontor/api` (Substage 8.1, first
  component of Stage 8): authentication (scoped API tokens, one-time
  plaintext reveal), a `ApiResourceRegistry` CRUD-resource extension
  point (inverted dependency, same shape as every other registry in this
  monorepo) with one built-in demonstrator resource over Core's own
  organizations table, filtering/pagination/sparse-fields/include,
  OpenAPI generation, webhooks (HMAC signature, exponential backoff,
  mutable delivery log, auto-disable, replay — subscribed onto
  `kontor/core`'s real `EventDispatcher`, same "distinct triggers"
  approach `kontor/automation` established), and idempotency
  (`Idempotency-Key` response caching, distinct from
  `kontor/inventory`'s own per-record idempotency column). The real
  `/api/kontor/v1/` HTTP entry point is a thin `ProcessPageView::execute`
  hook over a fully unit-tested, DTO-only `ApiRequestHandler`. First real
  consumer of `Kontor\Core\Testing\DatabaseTestCase` outside `kontor/core`
  itself. Depends only on `kontor/core` — see its own README.
- [`packages/graphql/`](packages/graphql/) — `kontor/graphql` (Substage
  8.2, second component of Stage 8): schema registry, component types,
  permission enforcement, complexity limits. Depends on `kontor/core`
  **and `kontor/api`** — the first Stage 8 package to depend on another
  Stage 8 package, reusing `kontor/api`'s own `ApiResourceRegistry`
  directly rather than a parallel resource system, so a resource
  registered once is queryable through both REST and GraphQL. Hand-rolled
  query parser (no third-party GraphQL library, same call
  `kontor/documents` made for its own template engine) supporting a
  deliberately narrow subset: `uid`/`page`/`pageSize` arguments only, no
  nested filter objects. Permission enforcement reuses `ApiToken::hasScope()`
  directly; complexity limiting rejects an overly expensive query
  (`fieldCount × pageSize` per selection) before touching any resource.
  Served at its own `/graphql` path — deliberately not nested under
  `kontor/api`'s prefix, avoiding any collision with its already-shipped
  hook. Second real consumer of `Kontor\Core\Testing\DatabaseTestCase`
  outside `kontor/core` — see its own README.
- [`packages/marketplace/`](packages/marketplace/) — `kontor/marketplace`
  (Substage 8.3, third and final component of Stage 8): official
  registry, custom registry, component metadata, advisories, publisher
  model. Depends only on `kontor/core` — reuses its own
  `ComponentManifest::fromArray()`/`DependencyChecker`/`VersionConstraint`
  directly rather than a parallel implementation. Instance-wide, same
  reasoning as `kontor_components`. `RegistrySyncService` fetches one
  registry payload (`{"components": [...], "advisories": [...]}`, each
  component a real kontor.json) and ingests both in one pass; a
  publisher only ever becomes verified via a **trusted** registry sync
  and is never un-verified by an untrusted one. `InstallabilityChecker`
  recommends (dependencies satisfied + no open critical advisory) but
  never installs — that stays `ComponentManager`'s own job. Third real
  consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
  `kontor/core` — see its own README.
- [`packages/mail/`](packages/mail/) — `kontor/mail` (Substage 9.1,
  first component of Stage 9 — Advanced capabilities): outbound history,
  inbound adapters, entity linking, shared mailboxes. Depends only on
  `kontor/core`. "Entity linking" reuses Core's own `kontor_relations`
  table directly (`relationType = 'mail_link'`), the same choice
  `kontor/tasks`/`kontor/entities` already made for their own "relations"
  milestones. `OutboundMailService`/`InboundMailService` publish real
  `mail.sent`/`mail.delivery_failed`/`mail.received` events onto Core's
  event bus, giving `kontor/automation` real new triggers. No third-party
  mail library — `RawEmailParser` is a small hand-rolled plain-text
  parser and `NativeMailSender` uses PHP's own `mail()`, both swappable
  via their own interfaces. `RawEmailForwardAdapter` is the one built-in
  inbound adapter, simulating an inbound-parse webhook via `pushRaw()`.
  Fourth real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
  `kontor/core` — see its own README.
- [`packages/portal/`](packages/portal/) — `kontor/portal` (Substage 9.2,
  second component of Stage 9): customer login, quotations, invoices,
  payments, files, profile. Depends on `kontor/core` **and**
  `kontor/contacts`, `kontor/sales`, `kontor/invoices`, `kontor/payments`,
  `kontor/files` — an aggregator over five sibling packages at once,
  reused directly, none ever modified. A portal account is always exactly
  one Contact. Quotations/invoices are a read-only, ownership-checked
  view hydrating the sibling packages' own real Domain objects (their
  constructors are public) rather than a parallel DTO, since neither
  repository exposes a "find by customer" method and neither is
  retrofitted with one. Payments reuse `kontor/payments`'s own allocation
  lookup directly — read-only history, no payment gateway. Files build
  the real signed-URL-verifying download endpoint `kontor/files`'s own
  README flagged as deferred (customer-facing, so it lives here, not
  there). Fifth real consumer of `Kontor\Core\Testing\DatabaseTestCase`
  outside `kontor/core` — see its own README.
- [`packages/ai/`](packages/ai/) — `kontor/ai` (Substage 9.3, third
  component of Stage 9): provider contract, Squad adapter, summaries,
  drafting, extraction, approval workflow. Depends only on `kontor/core`
  — `KontorAIProviderInterface`/`AIRequest`/`AIResponse` already live in
  `kontor/sdk`. "Kontor AI is optional" (kontor.md#32): no configured
  provider is a graceful `AIResponse` failure, never an exception.
  `SquadAdapter` talks to Squad only through its own
  `SquadClientInterface` — never Squad's implementation details directly
  — with `NullSquadClient` as the one built-in stub proving the pipeline
  when nothing real is wired up. `DraftingService` defaults to requiring
  confirmation (a draft mimics ready-to-send content);
  `SummaryService`/`ExtractionService` don't. `AIActionApprovalService`
  is the real approval-workflow gate: a response requiring confirmation
  is withheld and stashed as a `PendingAIAction` instead of returned
  directly. Sixth real consumer of `Kontor\Core\Testing\DatabaseTestCase`
  outside `kontor/core` — see its own README.

## Status

Stage 0 (SDK contracts), Substage 1.1–1.5 (Core: module bootstrap, core
database schema, Component Manager, backup and recovery, import and
export), all of Stage 2 (Substage 2.1 Queue, 2.2 Files, 2.3 Cache,
2.4 Search — platform infrastructure), all of Stage 3 (Substage 3.1
Contacts, 3.2 Catalog, 3.3 CRM — foundational business components), all of
Stage 4 (Substage 4.1 Sales, 4.2 Documents, 4.3 Invoices, 4.4 Payments —
sales and finance-lite), all of Stage 5 (Substage 5.1 Tasks, 5.2
Collaboration, 5.3 Dashboard, 5.4 Reports — collaboration and
productivity), all of Stage 6 (Substage 6.1 Inventory, 6.2 Purchasing,
6.3 Expenses, 6.4 Projects — operations), all of Stage 7 (Substage
7.1 Workflow, 7.2 Automation, 7.3 Custom Entities, 7.4 SDK and
scaffolding — extensibility), all of Stage 8 (Substage 8.1 REST API,
8.2 GraphQL, 8.3 Marketplace — API and external ecosystem), and
Substage 9.1–9.3 (Mail, Portal, AI — Stage 9, Advanced capabilities) per spec
section 36. Not yet installed against a live ProcessWire instance — see
the spec's Definition of Done
(section 38) for what "complete" means for each subsequent milestone.
