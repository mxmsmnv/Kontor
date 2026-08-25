# Changelog

All notable changes to `kontor/core` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Changed

- Task details now presents a focused status workspace with owner, priority,
  due date, recurrence and connected records; editing and reminders use guided
  dialogs, completed work suppresses obsolete reminders, and collaboration is
  organized into native discussion and internal-note tabs.
- Tasks now works as a responsive daily inbox with all, assigned, today,
  overdue and upcoming focus views, human due-date context and assignee names,
  compact filters, a separate archive entry point and mobile task cards.
- Files now separates the searchable document library from focused file
  details, uses a guided upload dialog, presents human file types and storage
  health, links available business records, and keeps raw storage metadata in
  an explicit technical disclosure.
- Portal now centers the customer access directory, account readiness and
  customer-visible documents; account creation uses a guided modal, account
  details use a responsive profile-and-content workspace, and sign-in
  diagnostics remain a secondary support action.
- Files now presents a business-focused document library and version summary,
  keeping record identifiers, checksums, storage paths and structured metadata
  inside explicit technical disclosures.
- Portal now keeps credential diagnostics collapsed, uses customer names
  instead of contact identifiers, prevents credential autofill in diagnostics,
  and provides a clear account-empty state.
- Documents now uses a business-focused library and template detail workflow,
  friendly preview fields, controlled version publishing and private PDF output;
  template source, styling and custom preview data remain advanced options.
- Collaboration now resolves comments and notes to permission-aware human
  links for their Project, CRM, Tasks, Contacts, and Companies records.
- Detail summaries across all components once again use a responsive shared
  grid with readable labels, values and wrapping.
- The AI workspace now presents outcome-focused tasks, provider readiness and
  human review without exposing provider classes, raw identifiers or JSON setup.

- Contact and company records now expose one permission-aware connected
  workspace for related CRM leads, deals and active tasks, with contextual
  create actions that preselect the customer and gracefully disappear when an
  optional component is unavailable.
- Task creation can now preserve contact or company context, create the shared
  relation atomically with the task, and show a human-readable linked customer
  on the task while keeping technical identifiers out of the primary UI.
- Kontor navigation now resolves optional-component availability and user
  permissions through a shared tested resolver, so partial installations no
  longer advertise unreachable workspaces.
- Task and collaboration screens now show ProcessWire author names instead of
  internal user IDs, and task forms use the same guided native UIkit fields as
  the rest of the workspace.
- Kontor workspaces now share one vertical rhythm across page headers, forms,
  cards, filters and empty states; form context headers no longer create a
  second framed panel, and Expenses uses a native responsive UIkit status nav.
- Dashboard users can now replace the default hero copy with a personal
  motivational headline and optional supporting note, or restore the defaults.
- The Dashboard intro dialog now keeps its close control as a native UIkit
  close icon instead of inheriting ProcessWire's full button treatment.
- Component cards now use larger unframed icons and native UIkit labels with
  system spacing for readable wrapped dependencies, and no longer expose Source.
- Every component card now resolves its workspace from navigation metadata and
  exposes a direct open action; configurable modules also show a settings action.
- The Kontor menu now keeps Components as a fixed destination directly above
  the renamed Quick Access editor, while preventing duplicate pinned entries.
- Contacts now uses a clear status-and-archive view switcher, focused search,
  grouped import/export actions, responsive directory rows and confirmed
  lifecycle actions instead of an overextended filter toolbar.
- Dashboard Catalog overview now separates primary inventory metrics, lifecycle
  context, actionable data-quality issues and recently updated items into a
  clearer responsive UIkit hierarchy.
- ProcessWire and native UIkit forms now provide accessible field descriptions
  plus practical completion notes without adding a second page-introduction
  layer or disrupting the existing section headers.
- Dashboard now follows one consistent UIkit spacing and grid system, fills
  personal-layout gaps with the widget picker, presents metrics as plain
  facts, and keeps links only for explicit navigation and entity opening.
- Price lists now matches the native UIkit catalog workflow with a focused
  filter card, selection-only bulk actions, plain table facts, and an
  actionable empty state.
- Catalog now uses a focused native UIkit search and filter flow, reveals bulk
  actions only after selection, and presents item details with a concise
  business summary and collapsed optional translations.
- Components now uses native UIkit status navigation, filters, matched card
  grids, labels and actions without its former page-specific CSS layer.
- Sections now shares one explicit UIkit grid for its header, toolbar and
  cards, with uniform spacing and only essential navigation links/actions.
- Sections now relies on native UIkit cards, grid, search, checkbox, label and
  button components without custom hover movement or compound borders.
- ProcessKontor removes AdminThemeUikit's redundant `#pw-content-body`
  wrapper after initialization while leaving the rest of the admin untouched.
- Sections now uses a clearer launcher UI with full-card navigation, live
  result counts, accessible pin controls and underline-free links.
- The complete component directory and per-user quick-access editor now live
  on their own `Sections & quick access` page instead of expanding Dashboard.
- Standardized every Kontor admin screen on the ProcessWire design system:
  native UIkit cards, buttons, tables, forms, labels, alerts and empty states
  now share the live AdminTheme tokens instead of hardcoded colors and
  component-specific visual overrides.

### Fixed

- Long component identifiers, cache namespaces and action groups now wrap or
  reflow on narrow screens instead of widening the entire admin page.
- Workflow instance pages use a readable page title while keeping the source
  entity UID in the detail summary, so technical identifiers cannot overflow
  the ProcessWire headline on narrow screens.
- Personal Dashboard widgets now use their persisted 12-column width and
  horizontal position, with responsive cards and design-system controls.
- Dashboard directory groups now stack vertically with compact responsive
  launch tiles, preserving complete section names without equal-height gaps.
- ProcessWire navigation caches are now invalidated per user whenever the
  Kontor navigation definition changes, preventing the top menu from being
  stuck on an old four-item component list.
- REST tokens now enforce resource-specific read/write scopes on every CRUD
  route.
- Dashboard hashes scoped widget cache identities so WireCache never truncates
  otherwise valid organization, layout, configuration, and tag dimensions.
- Entity-bound Files lookups can now enforce the owning organization.
- Search serializes cached result DTOs into WireCache-compatible snapshots
  and validates them while rebuilding the result.
- Files now enforces organization boundaries across version lookup, sharing,
  reading, archiving and restoring; restores leave exactly one active version
  and failed metadata writes remove newly stored bytes.
- German chart seeding is now conflict-safe and idempotent, avoiding
  partial localized charts on repeated setup runs.
- Archived ledger accounts are represented in the domain and rejected by
  the posting service, which also prevents account-currency mismatches.
- Documents now loads its PDF runtime from the root application dependency
  graph, publishes language families independently of English fallback, and
  keeps exactly one restored version active per family.
- Directed task relations can now be looked up from their target entity
  without weakening the generic relation API's direction semantics.

### Added

- Kontor AI now accepts idempotent, redacted external approval records through a public API; the Mailbox bridge forwards approve/reject decisions through Mailbox's own permission and separation-of-duties checks before changing Kontor state.
- ProcessKontor module version advanced to `160` for the external-approval decision bridge.

- A complete grouped workspace directory on Dashboard with live search and
  per-user quick-access pinning persisted in ProcessWire user metadata.
- The Kontor top menu is now a short personal quick-access list rather than a
  dump of every installed component.
- Consistent ProcessWire page titles and hierarchical breadcrumbs across
  every Kontor list, detail, designer and import route.
- `KontorDemo`, an executable order-to-cash reference workflow that
  creates and connects real Contacts, CRM, Catalog, Sales, Workflow,
  Tasks, Collaboration, Files, Mail, Projects, Invoices, Ledger and
  Payments records with an explicit approval gate and full traceability.
- The Health screen now discovers and runs health checks for every
  installed Kontor component rather than only the original five.
- Issued quotations now flow through Mail with linked outbound history and a
  delivery-gated transition to `sent`.
- Invoice issuance, credit notes, and cancellation now flow into Ledger as
  balanced, idempotent receivables, revenue, and sales-tax entries.
- Inventory-tracked Sales orders now reserve stock on confirmation, ship the
  reservation on completion, and release it on cancellation.
- Payment allocation and reversal now propagate invoice settlement state back
  to the originating Sales order.
- Won CRM deals now open prefilled Sales quotation drafts, persist their source
  deal, and expose linked quotations in both directions.
- Contacts now adopts AI summaries with an on-demand customer brief built
  from identity, notes, tags, and company relationships.
- Sending an issued invoice now runs through Mail, records outbound history,
  links the message back to the invoice, and only advances after delivery.
- Reimbursed Expenses now post idempotent debit Expense / credit Bank entries
  into Ledger and expose the immutable posting from expense detail.
- Payments now closes the finance loop into Ledger: invoice allocations create
  balanced receivable-clearing entries, reversals append inverse entries, and
  payment detail links to the accounting trail.
- API-exposed Custom Entities now register real dynamic REST resources and
  automatically become queryable through the shared GraphQL schema.
- Tasks now registers the first component-owned Dashboard widget: each user can
  add a cached, linked view of their own open and overdue assignments.
- Expenses is the first Workflow adopter: its safe business lifecycle now
  mirrors into `expenses.standard` with approval requests, generic history,
  and state visibility in the expense workspace.
- Automation now drives real Tasks work through the component-owned
  `tasks.create` action, including trigger relations and optional delayed
  Queue → Mail reminders.
- Scheduled Reports now dispatch idempotent Queue jobs from LazyCron and
  deliver confidential versioned exports to Files, with schedule management
  and manual due dispatch in the Reports workspace.
- Contacts now flows through the shared API registry into GraphQL: the admin
  schema explorer discovers the `Contact` type and live scoped queries read
  real organization-owned contact records.
- Contacts now registers the first business API resource with live
  bearer-authenticated REST CRUD, filters, pagination, sparse fields,
  idempotent create, and soft delete.
- Assigned tasks can schedule delayed Queue reminders; workers deliver through
  Mail, write task-linked history, and mark reminders sent exactly once.
- Collaboration comments now queue idempotent mention/follower notifications;
  Queue workers deliver them through Mail with outbound history and entity
  links.
- Dashboard widget payloads now flow through Cache with scoped keys, TTLs,
  visible hit/miss state, and layout-driven tag invalidation.
- Invoice and credit-note issuance now persists exact template snapshots and
  confidential PDFs through Documents and Files.
- Issuing a Sales quotation now resolves `quotation.standard`, persists the
  immutable template snapshot in Sales, and stores a confidential PDF in Files.
- Documents now persists generated PDFs and immutable render snapshots through
  Files, producing private entity-bound versions linked from the render flow.
- Search now consumes Cache end to end: organization-scoped query keys,
  30-second result reuse, and tag invalidation after indexing.
- First Cache admin vertical: live ProcessWire adapter health,
  organization-scoped namespace workbench, JSON values, TTLs, tags, and
  generation-based tag or namespace invalidation.
- First Files admin vertical: private uploads, checksum and metadata detail,
  entity-bound versions, signed authenticated downloads, and reversible
  archive/restore lifecycle.
- First Germany localization admin vertical: capability visibility, local
  VAT-ID checksum validation, conflict-safe illustrative SKR03 seeding into
  Ledger, and an XRechnung XML preview workbench.
- First Ledger admin vertical: chart-of-accounts creation and lifecycle,
  live normal-side balances, balanced two-line journal posting, immutable
  entry detail, and optional business-document references.
- First AI admin vertical: production-provider visibility, deterministic
  network-free capability previews, summaries and extraction, plus a human
  approval queue that withholds critical draft output until a decision.
- First Documents admin vertical: versioned multilingual template publishing,
  designer-v1 markup, HTML/PDF rendering, immutable issue snapshots, and
  reversible version lifecycle.
- First Portal admin vertical: customer-account provisioning and lifecycle,
  credential verification, safe profile editing, and customer-scoped previews
  of quotations, invoices, payments, and signed file downloads.
- First Mail admin vertical: shared mailboxes, safe outbound simulation with
  persistent history, raw-email inbound adapter, message detail, and entity
  linking.
- First Marketplace admin vertical: registry management, deterministic JSON
  synchronization, component metadata, publisher trust, advisories, and
  installability recommendations.
- First GraphQL admin vertical: shared resource/type explorer, SDL visibility,
  authenticated query bench, permission errors, and complexity-limit feedback.
- First REST API admin vertical: one-time scoped token issuance and revocation,
  registered-resource/OpenAPI inspection, webhook subscription management,
  and delivery-log observability.
- First Custom Entities admin vertical: schema definition, typed fields,
  dynamic records, reusable filtered/sorted views, relation links, permission
  gates, and API-exposure visibility.
- First Automation admin vertical: trigger rules, condition/action builder,
  JSON dry/live execution bench, and execution-log observability.
- First Workflow admin vertical: state-machine designer, transition graph,
  runtime instances, approval decisions, and transition history.
- First Projects admin vertical: customer-backed projects, milestones,
  billable time and items, and generation of real draft invoices.
- First Expenses admin vertical: category creation, expense drafts, optional
  supplier and receipt references, submission, approval/rejection, cancellation,
  and reimbursement.
- First Purchasing admin vertical: supplier creation, one-line purchase-order
  drafting and issue, goods receipts, cumulative receipt status, and atomic
  Inventory stock updates.
- First Inventory admin vertical: warehouse lifecycle, catalog-backed item
  selection, stock receipts/transfers/adjustments/reservations/releases,
  balance visibility, and the movement ledger.
- First Reports admin vertical: provider discovery, organization-scoped
  execution, schema-driven filters and grouping, result tables, totals, and
  CSV export.
- Dashboard package integration on the existing Kontor home: personal default
  dashboard creation, registered widget placement, persisted layout controls,
  and live widget rendering.
- First Collaboration admin vertical: recent notes/comments, task-attached
  discussion, auto-following, unread-state reads, and archive actions.
- First Tasks admin vertical: organization-scoped listing and filters,
  create/edit, start/complete/cancel, recurrence spawning, and archive/restore.
- First Payments admin vertical: sent-invoice payment capture, automatic
  confirmation and allocation, paid-state synchronization, payment listing,
  detail, and reversal.
- First Invoices admin vertical: completed Sales order conversion, invoice
  list/detail, issue/send lifecycle, due tracking, and credit-note action.
- First Sales admin vertical: one-line quotation drafting, issue and
  acceptance workflow, quotation-to-order conversion, and order
  confirmation/completion.
- First CRM deal vertical: standard pipeline creation, stage-based deal entry,
  Kanban movement, won/lost closure, and deal archiving.
- First CRM admin vertical: Lead listing, search, status/archive filters,
  contact/company linking, and create/edit/archive/restore lifecycle.
- Root Composer autoload coverage for every bundled Kontor component namespace
  and migration namespace, enabling dependency-ordered ProcessWire installs.
- Query-, facet-, archive-, and page-preserving returns after individual
  Catalog item archive and restore actions.
- Direct currency and status drill-downs from Catalog item pricing coverage to
  filtered Price lists.
- Composable active/inactive filters and clickable status badges for Contacts
  and Companies.
- Human-readable Global Search result badges and a query-preserving return to
  all result types.
- Direct entity-type scoping from Global Search result badges.
- Internal type and exact-search facets for Catalog reference labels and codes.
- Composable row facets for Category status and Price list currency, validity,
  and status.
- Composable Catalog row facets for item type, category, inventory, pricing,
  unit, and status.
- Exact matching and shown-result feedback beside Activity filters.
- Direct dependency drill-down from Components requirement badges.
- Composable Activity result facets that retain search and existing filters.
- Composable Backups result facets on component names and verification states.
- Direct Dashboard summary-card drill-down for contacts, companies, enabled
  components, and active Queue jobs.
- Composable Queue table facets on queue names and job statuses.
- Direct Queue summary-card drill-down for all, active, completed, and
  dead-letter jobs, including a combined pending/reserved active filter.
- Exact matching-result feedback for Components and Health filters.
- Selected-state styling and accessible current-page semantics for Health
  summary filters.
- Selected-state styling and accessible current-page semantics for Components
  summary-card filters.
- Direct Components summary-card drill-down for registered, enabled, and
  attention states.
- Direct health-summary drill-down for healthy, warning, and critical checks.
- Clear search action for the global Kontor search workspace.
- Clear search actions for Contacts and Companies, preserving archive context.
- Dashboard visibility for active uncategorized Catalog items with direct
  drill-down.
- Explicit Clear filters action in the Catalog References workspace.
- Dashboard visibility for upcoming Catalog price lists with direct drill-down.
- Dashboard visibility for expired Catalog price lists with direct drill-down.
- Direct dashboard drill-down from the inventory-tracked Catalog count.
- Dashboard visibility for active Catalog items without a sales price, linking
  directly to the corresponding filtered view.
- Catalog item filtering for positions with or without a configured sales price.
- Organization-scoped sales-currency filtering for Catalog items, preserved
  across pagination, archive views, and bulk actions.
- Unit-of-measure and tax-code filters for Catalog items, including direct
  drill-down links from the References workspace.
- Organization-scoped currency filtering for Catalog price lists, preserved
  across pagination and bulk status actions.
- Current, upcoming, and expired validity filters for Catalog price lists,
  preserved across pagination and bulk status actions.
- Context-aware Clear filters actions for Catalog items, categories, and price
  lists, retaining archive mode where applicable.
- Catalog inventory-tracking filtering, preserved across pagination and bulk
  actions.
- Bulk Activate/Deactivate/Discontinue controls for Catalog items, preserving
  filters and recording tenant-scoped audit events.
- Bulk Activate/Deactivate controls for Catalog categories, preserving filters
  and recording tenant-scoped audit events.
- Catalog category status filtering, preserved across pagination and bulk
  archive/restore flows.
- Catalog item status filtering, preserved across pagination and bulk
  archive/restore flows.
- Uncategorized filtering in Catalog, preserved across pagination and bulk
  archive/restore flows.
- Direct Add price tier action on Catalog item forms, with price-list choice
  and the current item preselected.
- Pricing coverage on Catalog item forms, listing every quantity tier across
  the organization's price lists with direct edit links.
- Category filtering for Catalog items, category labels in item rows, and
  linked active-item counts in the category workspace.
- Bulk Activate/Deactivate controls for Catalog price lists, preserving list
  filters and recording tenant-scoped audit events.
- Bulk archive/restore controls for Catalog categories, with tenant-scoped
  transactional persistence and select-all behavior.
- One-click Catalog price-list duplication that transactionally copies all
  quantity tiers into a new inactive draft.
- Catalog dashboard overview with linked product, service, archive, price-list,
  inventory-tracking, and recently updated item summaries.
- One-click Catalog item duplication that copies localized content and
  commercial settings while clearing unique identifiers and creating an
  inactive, audited draft.
- Multilingual Catalog category editing for English, French, German, and
  Spanish names, including default-language list rendering.
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
