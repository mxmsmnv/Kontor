# Kontor Reports

`kontor/reports` — report builder, charts, exports, and scheduled reports.
Fourth and final component of Stage 5. Depends on `kontor/core` and
`kontor/documents` (for PDF export).

## The "provider registry" milestone already existed

`Kontor\Core\Infrastructure\Registry\ReportProviderRegistry` was built in
Substage 3.3, with `kontor/crm`'s `PipelineReportProvider` as its first
consumer — a table created in one substage, given a service by a later
one, same "gap found, filled where the first real consumer needs it"
pattern as `ExtensionRepository`/`SequenceService`/`RelationRepository`,
just inverted: here the *registry itself* came first, and this package is
the orchestration layer that finally sits on top of it. `kontor/reports`
does not add a second registry.

## Contents

- `src/Application/ReportBuilderService.php` — the "report builder"
  milestone: `run()` validates a query's filters/`groupBy` against the
  chosen provider's own declared `ReportSchema` before executing, rather
  than silently producing an empty or wrong result for a field the
  provider never declared as filterable/groupable. `validateFor()` does
  the same check without executing — used when scheduling a report, so a
  typo'd field fails at schedule time without paying for a query the
  caller isn't ready to run yet. `ReportProviderInterface`'s fixed-shape
  design (kontor.md#9.9) means this validates against a provider's
  declared fields rather than building arbitrary SQL — a fully generic
  ad-hoc query engine is out of scope, the same boundary
  `PipelineReportProvider`'s own doc comment already drew.
- `src/Application/ChartDataMapper.php` — the "charts" milestone:
  `toSeries()`/`toMultiSeries()` turn a `ReportResult`'s rows into
  label/value series. No charting UI/library — this is the data shape a
  future chart component would consume.
- `src/Application/ReportExportService.php` — the "exports" milestone
  (kontor.md#29: "Excel/CSV/JSON/PDF export"). CSV/JSON/XLSX reuse
  `Kontor\Core\Infrastructure\ImportExport`'s existing format writers
  (Substage 1.5) directly — they already operate on a plain iterable of
  associative arrays plus a field list, exactly `ReportResult::$rows`'
  shape. PDF reuses `kontor/documents`' `PdfRenderer` against a minimal
  generated HTML table, not the template engine — a report export isn't
  an "issued document" with a customizable template, just tabular data in
  a fourth format.
- `migrations/` — `kontor_scheduled_reports` (schema gap-fill, no
  dedicated spec section, same situation Tasks/Collaboration/Dashboard
  were in). `src/Domain/ScheduledReport.php`'s recurrence math is the same
  small `'daily'|'weekly'|'monthly'|'yearly'` set as `kontor/tasks`' own
  `Task::nextOccurrenceDueAt()` — duplicated rather than a shared
  cross-package dependency for four lines of interval strings.
  `advance()` moves `next_run_at` forward relative to *itself*, not to
  "now" — a schedule that's fallen behind catches up one interval at a
  time rather than snapping to whenever `run()` happened to execute.
- `src/Application/ScheduledReportService.php` — `run()` actually
  executes the report (via `ReportBuilderService`), exports it (via
  `ReportExportService`) and advances the schedule — but nothing
  dispatches it automatically; see "Not in scope".

## Testing

```bash
composer install
vendor/bin/phpunit
```

Unlike most packages in this monorepo, `tests/Unit/Application/ReportExportServiceTest.php`
needs no database — CSV/JSON/XLSX writers are pure filesystem and dompdf
is a pure-PHP Composer dependency — so CSV/JSON/PDF export all run for
real. `ReportBuilderServiceTest`/`ChartDataMapperTest`/`ScheduledReportTest`
need no database either (plain objects/registries). Everything under
`tests/Integration/` needs real MySQL (see `../../docker-compose.test.yml`)
and is skipped otherwise, same `KONTOR_TEST_DB_DSN` convention as the
other packages.

## Not in scope for this substage

No scheduler/cron wiring for due schedules — `ScheduledReportService::dueSchedules()`
is the query, and `run()` the method, a future cron/queue-backed dispatcher
(Stage 7) would call, same deferred-integration choice `kontor/tasks`'
reminders and `kontor/invoices`' `sweepOverdue()` already made. No delivery
of an exported report (email attachment, upload) — `run()` only produces
the file on disk. No admin UI/API endpoints, no actual charting library —
see `ChartDataMapper` above.
