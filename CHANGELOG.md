# Changelog

All notable changes to `kontor/core` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Multilingual Catalog item editing for English, French, German, and Spanish
  titles and descriptions, with the organization language marked and required.
- Bulk archive/restore controls for Catalog items, with select-all behavior,
  tenant-scoped persistence, per-item audit events, and preserved list filters.
- Catalog items in global search, including localized text, SKU/barcode lookup,
  direct item links, and an entity filter in the admin search workspace.
- Catalog item CSV/JSON/JSON Lines/XLSX export and preview-first import,
  protected by verified Catalog snapshots and automatic restore on failure.
- Catalog snapshots in the Backups workspace.
- A searchable Catalog reference workspace for unit and generic tax codes,
  including active-item usage counts and links across Catalog navigation.
- Catalog price-list management with searchable/status-filtered lists,
  validity periods, item quantity tiers, editing, deletion, and audit events.
- A ProcessWire-native Kontor admin application with dashboard metrics,
  component navigation, searchable contact/company lists, and create/edit
  forms for both entity types.
- Contact and company repository list/count queries for the admin workspace.
- Component registry version synchronization during Core module upgrades.
- CRM relationship cards, contact-to-company linking, and reversible archive
  workflows for contacts and companies in the admin application.
- Contact/company address management and duplicate-contact warnings in the
  admin application.
- CSV/JSON/JSON Lines/XLSX exports and read-only import previews for
  contacts and companies.
- Two-step live imports gated by an automatically created and verified
  Contacts snapshot, with automatic restore on import failure.
- Tag management in contact/company cards and a federated global directory
  search page.
- A searchable Activity screen backed by structured audit events for admin
  mutations, exports, imports, and backup creation.
- A Backups screen for creating, listing, and verifying Core and Contacts
  snapshots without exposing destructive restore controls.
- A Health screen that runs Core and installed-component diagnostics and
  contains individual probe failures without hiding the remaining results.
- An Organization settings screen for workspace identity and localization
  defaults, with validation, permission checks, and change-only auditing.
- A Queue monitor with bounded recent-job queries, queue/status filters,
  progress, errors, and live status summaries.
- Permission-gated Queue actions for cancelling pending work and returning
  dead-letter jobs as fresh attempts, with state guards and audit events.
- Permission-gated backup archive downloads with exact ID resolution,
  path containment, CSRF protection, and download audit events.
- Dashboard operational context with recent audited activity and live Queue
  active/dead-letter status, respecting the viewer's permissions.
- Faceted Activity filtering by component, entity type, and exact action,
  composable with the existing free-text search.
- Paginated Activity results with filter-preserving previous/next navigation
  and an exact matching-event count.
- Direct Activity drill-down links from event actions, components, entity
  types, and the Dashboard's recent-activity feed.
- Permission-aware Activity CSV exports that preserve the current search and
  facet selection and record the export in the audit trail.
- Human-readable Activity field diffs with before/after values, friendly
  labels, mobile layout, and a separate metadata section.
- Operational Components overview with runtime-versus-registry version drift,
  dependency metadata, status totals, search, and attention filtering.
- CSRF-protected component registry synchronization against installed
  ProcessWire module versions, permission-gated and fully audited.
- Searchable Health results with status filtering, an unfiltered overall
  summary, preserved refresh filters, and component drill-down links.
- Global Search scoping by contacts or companies, paginated results, clearer
  result totals, preserved scope navigation, and explicit short-query help.
- Paginated Contacts and Companies lists with exact active/archive search
  totals and filter-preserving view and page navigation.
- Paginated Queue monitoring with exact filtered totals and preserved queue
  and status selection across pages.
- Searchable, filterable Backup history with verification-state facets,
  exact result totals, and pagination.
- Catalog admin workspace for searchable and paginated products and services,
  including create, edit, pricing, inventory behavior, and archive workflows.
- Catalog category management with hierarchy, ordering, lifecycle controls,
  searchable history, and item assignment.

- `Kontor\Core\Testing\DatabaseTestCase` (Substage 7.4's "testing
  helpers" milestone) — the shared abstract base class for every
  component's own DB-gated integration tests, replacing the ~90 lines of
  boilerplate every package since `kontor/sales` hand-wrote independently
  (connect, drop tables, run migrations via `MigrationRunner`, seed a
  default organization via `OrganizationRepository`, clean up in
  `tearDown()`). Lives here rather than in `kontor/sdk` because it
  depends on classes that themselves depend on the SDK. Not retrofitted
  into any already-shipped package's own copy — same "built once, adopted
  by whoever wants it next" precedent as every other registry/extension
  point added this way (e.g. `ReportProviderRegistry`).
- `ExtensionRepository`, generic CRUD for `kontor_extensions` (kontor.md#11.8)
  — missing since Substage 1.2 only created the table. First real consumer:
  `kontor/contacts`' tags (Substage 3.1).
- `ReportProviderRegistry` — `ReportProviderInterface` (kontor.md#9.9) has
  existed in the SDK since Substage 0.2 but had no registry until
  `kontor/crm`'s `PipelineReportProvider` became its first real consumer
  (Substage 3.3).
- `SequenceService`, atomic document numbering over `kontor_sequences`
  (kontor.md#11.9) — a table created in Substage 1.2 that had no service
  until `kontor/sales` needed quotation/order numbers (Substage 4.1).
  Locks the sequence row for the duration of an increment, the same
  technique `kontor/queue`'s `JobRepository::reserveNext()` uses.
- `RelationRepository`, generic CRUD + lookup over `kontor_relations`
  (kontor.md#11.7) — a table created in Substage 1.2 that had no service
  until `kontor/tasks`' "entity relations" milestone became its first real
  consumer (Substage 5.1). `relatedTo()` returns every active relation
  touching an entity: always as the relation's source, plus as the target
  when the relation was recorded `'bidirectional'`.
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
- Import and export (Substage 1.5): `ImportProviderRegistry`,
  `ExportProviderRegistry`, `RepositoryRegistry`; format readers/writers for
  CSV, JSON, JSON Lines and XLSX (`Infrastructure\ImportExport\Format`), the
  XLSX ones hand-rolled over `ZipArchive`/`DOMDocument` to avoid a
  third-party dependency that would narrow the supported PHP range;
  `FormatResolver`; `ImportManager` — field mapping, dry-run preview
  (create/update detection without ever calling `import()`), a pre-import
  backup gate (`PreImportBackupRequiredException`), progress/completed
  events, and `rollback()`, which archives every record a batch created or
  updated via the audit ledger's `correlation_id` and `RepositoryInterface`;
  `ExportManager` streaming a provider's rows into any format writer.
- Bumped the `kontor/sdk` requirement to `^0.2` (`JobInterface::handle()`
  gained a `JobProgressReporterInterface` parameter for the new
  `kontor/queue` component; see `packages/sdk/CHANGELOG.md`).

### Fixed

- Load Composer's autoloader from the ProcessWire bootstrap module so
  installation and autoload initialization can resolve Kontor classes.
- Avoid committing a transaction that MySQL already ended implicitly while
  running DDL migrations.
