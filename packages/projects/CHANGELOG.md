# Changelog

All notable changes to `kontor/projects` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- First ProcessKontor admin vertical: tenant-scoped project listing and
  creation, milestone workflow, billable time and item capture, and draft
  invoice generation through the existing Sales and Invoices components.
- Initial alpha (Substage 6.4): `kontor_projects`,
  `kontor_project_milestones`, `kontor_project_time_entries`,
  `kontor_project_billable_items` migrations (full schema gap-fill, no
  dedicated spec section); `Project`/`ProjectMilestone`/`TimeEntry`/
  `BillableItem` domain objects; `ProjectRepository` (implementing
  `RepositoryInterface`) and `MilestoneRepository`/`TimeEntryRepository`
  (with `uninvoicedBillableFor()`)/`BillableItemRepository` (with
  `uninvoicedFor()`); `TimeTrackingService` (`start()`/`stop()` — a real
  timer — plus `logManual()`); `ProjectMilestoneService`
  (`complete()`/`reopen()`); `ProjectInvoicingService::generateInvoice()` —
  the "invoicing integration" milestone, creating a real draft
  `kontor/invoices` Invoice from a project's uninvoiced time and billable
  items, all in one shared transaction; `ProjectsHealthCheck` (checks that
  no time entry has a duration without an end time, not just a count);
  permissions; en/fr/de/es translations. Fourth and final component of
  Stage 6.
