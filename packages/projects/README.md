# Kontor Projects

`kontor/projects` — projects, milestones, time tracking, billable items,
and invoicing integration. Fourth and final component of Stage 6. No
dedicated schema section, permission list, or workflow diagram in
kontor.md for this substage — full gap-fill, same situation Tasks/
Collaboration/Dashboard/Reports/Purchasing/Expenses were in.

## Two real dependencies, both load-bearing

- `kontor/sales` — invoice lines generated from a project reuse shared
  `kontor_document_lines` rows, the same reuse `kontor/invoices` and
  `kontor/purchasing` already established.
- `kontor/invoices` — the "invoicing integration" milestone isn't
  deferred here, the same choice `kontor/purchasing` made for its own
  "inventory integration" milestone: `ProjectInvoicingService::generateInvoice()`
  creates a real draft `kontor/invoices` Invoice.

## Contents

- `migrations/` — `kontor_projects`, `kontor_project_milestones` (the
  "milestones" milestone — a project's own checkpoints, not this
  monorepo's own kontor.md#36 substage terminology),
  `kontor_project_time_entries` (`ended_at` is `NULL` while a timer is
  running) and `kontor_project_billable_items` (flat, non-time-based
  charges). `invoice_line_uid` on the latter two is a loose reference set
  once `ProjectInvoicingService` pulls an entry into an invoice.
- `src/Application/TimeTrackingService.php` — the "time tracking"
  milestone: `start()`/`stop()` are a real timer (`ended_at` stays `NULL`
  between the two calls, `TimeEntry::close()` computes
  `duration_minutes`); `logManual()` for a retroactively-entered duration.
- `src/Application/ProjectMilestoneService.php` — `complete()`/`reopen()`.
- `src/Application/ProjectInvoicingService.php` — the "invoicing
  integration" milestone. `generateInvoice()`:
  1. Requires the project to have a customer and a currency.
  2. Gathers every closed, billable, not-yet-invoiced time entry and
     every not-yet-invoiced billable item.
  3. Creates a draft Invoice via `kontor/invoices`' `InvoiceRepository`
     and one `DocumentLine` per item (time entries convert
     `duration_minutes` to hours against the entry's own rate, or the
     project's default rate if the entry didn't set one).
  4. Marks each source row invoiced (`invoice_line_uid`) so it can't be
     billed twice.

  Left in `'draft'` deliberately — issuing it is a separate step through
  `kontor/invoices`' own `InvoiceWorkflowService`, so whoever generates it
  can review the lines first. Everything runs in one shared transaction.
- `src/Health/ProjectsHealthCheck.php` — checks a real invariant (no time
  entry has a `duration_minutes` while `ended_at` is still `NULL`), not
  just a count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/TimeEntryTest.php` needs no database (pure timer/
duration logic) and runs for real. Everything under `tests/Integration/`
needs real MySQL (see `../../docker-compose.test.yml`) and is skipped
otherwise, same `KONTOR_TEST_DB_DSN` convention as the other packages.

## Not in scope for this substage

No project budgets or burn-rate tracking. No approval workflow for time
entries before they're billable (contrast `kontor/expenses`' explicit
approval milestone — nothing in this substage's milestones asks for one
here). No admin UI/API endpoints, no actual timer UI (start/stop are
plain method calls a future front-end would wire a button to).
