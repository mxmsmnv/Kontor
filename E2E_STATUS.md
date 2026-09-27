# Kontor release-candidate test status

Last updated: 2026-09-27

This is the durable checkpoint for the current full-system test programme. It
records verified evidence, defects fixed during the run and work that still
requires a later compatibility or external-service pass. It does not treat a
browser route smoke as a substitute for package-level coverage.

## Environment

- Source baseline: `aef9f0e` on `feat/configurable-crm-intake`, plus the fixes
  listed below
- Runtime: PHP 8.5.8, ProcessWire 3.0.259, MariaDB, local PHP development server
- Site: disposable local installation with its own database
- Lifecycle site: persistent clone with an independent database for destructive
  install/upgrade/uninstall verification; the primary test site was not mutated
- Components: all 35 Kontor components installed and enabled; MCP Server was
  installed as the explicit external dependency of Kontor MCP
- External side effects: none; no production mail, payment, AI, webhook or
  object-storage endpoint was used

## Automated evidence

| Area | Result |
|---|---:|
| Core/root PHPUnit | 185 tests, 510 assertions |
| Contacts, CRM, intake, catalog, sales, invoices, payments, portal | 304 tests, 796 assertions |
| Inventory, purchasing, expenses, projects, tasks, collaboration, workflow, automation, documents, ledger, Germany | 205 tests, 489 assertions |
| SDK, API, GraphQL, files, mail, queue, cache, settings, MCP, marketplace, dashboard, reports, search, entities, AI | 409 tests, 853 assertions |
| Total | 1,103 tests, 2,648 assertions |

Four Redis-specific cache tests were skipped because the optional `ext-redis`
extension is not installed. The in-memory cache implementation and the rest of
the cache package passed. A final syntax pass checked 1,021 PHP source and test
files; all 35 Kontor manifests and all 36 Composer manifests parsed
successfully.

A repository-wide dependency contract now verifies that every ProcessWire hard
dependency is also present in the owning package's Composer runtime graph. It
found and closed missing Queue, Files and Cache requirements in Collaboration,
Documents, Dashboard and Search respectively; all Composer manifests validate.

A deterministic webhook transport fake now covers timeout persistence, retry
state, replay with the same delivery identifier and successful recovery without
creating a duplicate delivery. No network endpoint was contacted.

## ProcessWire and browser evidence

- Fresh ProcessWire installation completed and all 35 Kontor components were
  installed in dependency order.
- Components reported 35 registered, 35 enabled and zero needing attention.
- Health reported 32 healthy, two warnings and zero critical. The warnings were
  expected configuration states: an unsynchronised official marketplace
  registry and no default CRM Intake profile.
- Forty admin routes rendered without a server or template error: dashboard,
  contacts, companies, CRM, intake, deals, sales, invoices, payments, tasks,
  collaboration, reports, inventory, purchasing, expenses, projects,
  workflows, demo, automations, mail, portal, files, cache, documents, AI,
  ledger, Germany, marketplace, GraphQL, API, custom entities, search, catalog,
  activity, backups, health, organization, settings migration, queue and
  components.
- A real connected demo journey completed in the browser: create scenario,
  prepare proposal, request approval, approve, start delivery, issue invoice
  and record full payment. The result was completed/settled with 21 linked
  records, a paid invoice, closed task and balanced ledger.
- A second demo scenario exercised the rejection path: proposal preparation,
  approval request and rejection with a required reason. The scenario returned
  to an active proposal, displayed the rejection note and offered a new
  approval request without creating delivery, invoice or payment records.
- Five real-database integration tests now cover the demo service's complete
  order-to-cash lifecycle, approval rejection/retry, denied transitions and
  transaction rollback for failed intake and transition effects.
- Six real-database CRM Intake validation tests cover invalid profile schemas,
  required and typed answers, normalization, unsupported entity types, missing
  profiles and same-entity tenant isolation.
- The lifecycle matrix uninstalled all 36 Kontor/ProcessWire modules in reverse
  dependency order, verified identical row counts, checksums and normalized DDL
  for all 84 `kontor_*` tables, reinstalled every module, ran every available
  upgrade hook and confirmed preserved data remained connected.
- The CRM Intake browser permission matrix passed: anonymous access returned
  the login boundary; a lead viewer could inspect the active profile but saw no
  management action; a user with intake administration but no Settings grant
  was denied the Settings route and no longer sees a dead-end action; a fully
  authorized administrator could open Settings migration.
- CRM Intake passed a 375x812 responsive pass without horizontal overflow,
  keyboard focus reached both page actions, light and dark themes retained
  readable contrast, the console contained no warnings/errors, and a captured
  reload produced no failed requests or HTTP responses at or above 400.
- A restricted automation/workflow user could see definitions but not the
  seeded execution-log sentinel or workflow history. An administrator could
  see both.
- A restricted purchasing/expense user could view order and expense queues but
  not a seeded supplier or expense category. Giving create permissions without
  the corresponding supplier/category view permission still denied direct
  create-form access at the missing reference-data permission.

## Defects fixed in this run

- Backup restore order and content-integrity verification.
- Cross-organization portal account/profile association and legacy health
  diagnostics.
- Automation-log and workflow-history permission leakage.
- Supplier-directory and expense-category permission leakage, including direct
  form access.
- Collaboration and Documents dependency manifest drift.
- Stale Invoices documentation for order-to-invoice conversion.
- Narrow-screen contact action wrapping and CRM Intake badge/title overlap.
- Demo transition effects running before the workflow permission check.
- CRM Intake profiles accepted blank or storage-overflowing names when callers
  used the public service directly instead of the settings adapter.
- Primary page-header links could inherit UIKit link-reset text color over a
  dark primary background, making the CRM Intake action label invisible.
- CRM Intake showed Settings migration to intake administrators who lacked
  both Settings export and import permissions, leading to a guaranteed denial.
- Creating a task with a contact or company relation did not enforce the
  declared relation-management permission before persisting the task.
- Customer file listing and signed-download generation did not independently
  enforce the current organization when used through the Portal service.
- Four package Composer manifests omitted ProcessWire-declared runtime
  dependencies, allowing incomplete standalone dependency resolution.
- Test assumptions that depended on MySQL JSON formatting, unordered query
  results or unread PDO result sets.

## Remaining release-matrix work

The current disposable run is a strong release-candidate pass, not a claim that
every supported environment and real provider has been exercised. The
following remain explicit:

- commit a repeatable automated browser runner for the critical journeys;
- run Firefox/WebKit compatibility passes; only the Chromium-based in-app
  browser is currently exposed to the automation surface;
- exercise Redis with `ext-redis`, plus deterministic mail, webhook, payment,
  AI and object-storage fakes in their full HTTP failure/retry paths;
- execute historical source-version upgrade fixtures; current-schema upgrade
  hooks and the full preserve-data uninstall/reinstall matrix have passed.

Portal file access is now organization-scoped in the service itself. Within one
organization, customer/contact ownership remains deliberately enforced by the
Portal composition boundary: `ProcessKontor` first resolves quotations and
invoices through contact-scoped repositories and only then lists and signs
their files. Moving that customer concept into the generic file-signing service
would require a public API redesign and is recorded as an explicit architectural
boundary rather than silently duplicating Sales and Invoices ownership rules.

The recurring thread heartbeat named **Kontor full E2E program** continues from
this checkpoint and reports only meaningful progress, failures or required
user action.

## Cleanup

The agent-controlled browser tab and local PHP server were stopped after the
run; no test worker or watcher was left running. At the owner's request, the
`/Users/mas/dev/processwire/e2e/kontor-full` site and its `kontor_e2e_full`
database are now a persistent reusable test environment. It currently has one
organization and all 35 Kontor components installed. Future runs must preserve
its files and database while continuing to remove only short-lived per-suite
verification databases and stop idle processes. The independent lifecycle
clone at `/Users/mas/dev/processwire/e2e/kontor-lifecycle` and database
`kontor_e2e_lifecycle` are also retained for repeatable destructive module
matrix runs; its final state is fully reinstalled.
