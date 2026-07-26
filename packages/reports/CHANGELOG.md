# Changelog

All notable changes to `kontor/reports` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Scheduled Reports now run end to end through ProcessWire LazyCron, the shared
  Queue worker, and private Files delivery. Dispatch is idempotent per
  recurrence slot, completed exports retain Files version history, and the
  admin workspace can create, list, archive, and manually queue schedules.
  A recurrence advances only after Files delivery succeeds, keeping retries
  on the same slot.
- First admin vertical: registered-provider discovery, schema-driven filters
  and grouping, organization-scoped report execution, result/totals rendering,
  and CSV export.
- Initial alpha (Substage 5.4): `kontor_scheduled_reports` migration
  (schema gap-fill); `ScheduledReport` domain object (daily/weekly/monthly/
  yearly recurrence, same math as `kontor/tasks`); `ScheduledReportRepository`
  (implementing `RepositoryInterface`, plus `due()`); `ReportBuilderService`
  (`run()`/`validateFor()` — validates filters/groupBy against a
  provider's own declared `ReportSchema` before executing, on top of
  `kontor/core`'s existing `ReportProviderRegistry`); `ChartDataMapper`
  (`toSeries()`/`toMultiSeries()`); `ReportExportService` (CSV/JSON/XLSX
  via `kontor/core`'s existing format writers, PDF via `kontor/documents`'
  `PdfRenderer`); `ScheduledReportService` (`schedule()`/`dueSchedules()`/
  `run()` — executes, exports, and advances the schedule);
  `ReportsHealthCheck`; permissions; en/fr/de/es translations. Fourth and
  final component of Stage 5.
