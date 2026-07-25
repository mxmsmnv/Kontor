# Changelog

All notable changes to `kontor/tasks` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 5.1): `kontor_tasks` and `kontor_task_reminders`
  migrations (schema gap-fill); `Task` domain object with recurrence math
  (`nextOccurrenceDueAt()` for `'daily'|'weekly'|'monthly'|'yearly'`, capped
  by `recurrence_until`) and `TaskReminder`; `TaskRepository` (implementing
  `RepositoryInterface`, plus `dueBetween()` for the "calendar" milestone)
  and `TaskReminderRepository`; `TaskWorkflowService`
  (`start()`/`complete()`/`cancel()` — completing a recurring task spawns
  its next occurrence); `TaskReminderService`; `TaskRelationService` (the
  "entity relations" milestone, first real consumer of `kontor/core`'s new
  `RelationRepository`); `TasksHealthCheck`; permissions; en/fr/de/es
  translations. First component of Stage 5.

### Changed (in `kontor/core`, same commit)

- Added `RelationRepository` over `kontor_relations` (kontor.md#11.7) — a
  table created in Substage 1.2 with no service until now. See the root
  `CHANGELOG.md`.
