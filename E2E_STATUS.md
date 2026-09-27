# Kontor release-candidate test status

Last updated: 2026-09-27

This is the durable checkpoint for the current full-system test programme. It
records verified evidence, defects fixed during the run and work that still
requires a later compatibility or external-service pass. It does not treat a
browser route smoke as a substitute for package-level coverage.

## Environment

- Source baseline: `09430bd` on `feat/configurable-crm-intake`, plus the fixes
  listed below
- Runtime: PHP 8.5.8, ProcessWire 3.0.259, MariaDB, local PHP development server
- Site: disposable local installation with its own database
- Components: all 35 Kontor components installed and enabled; MCP Server was
  installed as the explicit external dependency of Kontor MCP
- External side effects: none; no production mail, payment, AI, webhook or
  object-storage endpoint was used

## Automated evidence

| Area | Result |
|---|---:|
| Core/root PHPUnit | 170 tests, 356 assertions |
| Contacts, CRM, intake, catalog, sales, invoices, payments, portal | 302 tests, 793 assertions |
| Inventory, purchasing, expenses, projects, tasks, collaboration, workflow, automation, documents, ledger, Germany | 205 tests, 489 assertions |
| SDK, API, GraphQL, files, mail, queue, cache, settings, MCP, marketplace, dashboard, reports, search, entities, AI | 408 tests, 838 assertions |
| Total | 1,085 tests, 2,476 assertions |

Four Redis-specific cache tests were skipped because the optional `ext-redis`
extension is not installed. The in-memory cache implementation and the rest of
the cache package passed. A final syntax pass checked 1,021 PHP source and test
files; all 35 Kontor manifests and all 36 Composer manifests parsed
successfully.

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
- Test assumptions that depended on MySQL JSON formatting, unordered query
  results or unread PDO result sets.

## Remaining release-matrix work

The current disposable run is a strong release-candidate pass, not a claim that
every supported environment and real provider has been exercised. The
following remain explicit:

- commit a repeatable automated browser runner for the critical journeys;
- run a dedicated narrow-viewport visual pass after the responsive fixes (the
  current browser automation surface could not change viewport size);
- run Firefox/WebKit, keyboard-only and light/dark-theme compatibility passes;
- exercise Redis with `ext-redis`, plus deterministic mail, webhook, payment,
  AI and object-storage fakes in their full HTTP failure/retry paths;
- execute supported-version upgrade fixtures and the preserve-data
  uninstall/reinstall matrix on disposable databases;
- add deeper CRM Intake invalid/authorization cases and package tests for the
  demo orchestrator, which currently relies on the successful real browser
  journey rather than its own package suite.

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
verification databases and stop idle processes.
