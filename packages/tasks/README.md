# Kontor Tasks

`kontor/tasks` — tasks, reminders, recurrence, calendar, and entity
relations. First business component of Stage 5 (kontor.md#36). Depends
on `kontor/core`, plus Automation for `tasks.create`, and Queue/Mail for
delayed email reminder delivery.

## A fourth Core gap, filled here

`kontor_relations` (kontor.md#11.7) has existed since Substage 1.2 with no
service — the "entity relations" milestone needed one, so
`Kontor\Core\Infrastructure\Persistence\RelationRepository` was added to
`kontor/core` (same "gap found, filled where the first real consumer needs
it" pattern as `ExtensionRepository`/`ReportProviderRegistry`/
`SequenceService`). `TaskRelationService` in this package is its first real
consumer: a task is always the relation's source, linked to any other
entity (`'contact'`, `'crm_deal'`, an invoice, …) by uid.

## Also its own schema gap

Sections 11–16 of kontor.md never gave Tasks a schema section, so
`kontor_tasks` and `kontor_task_reminders` are this package's own gap-fill
(same situation Documents was in for its templates table).

## Contents

- `migrations/` — `kontor_tasks` and `kontor_task_reminders` (a lighter
  junction-style table, like `kontor_payment_allocations`: no `version`/
  `archived_at`, `sent_at` is its own lifecycle marker).
- `src/Domain/Task.php` — `nextOccurrenceDueAt()` is the "recurrence"
  milestone's actual math: `'daily'|'weekly'|'monthly'|'yearly'` (a small
  named set, not a full RFC 5545 RRULE — same scope choice as
  `SequenceService`'s `reset_policy`), advancing from the task's own due
  date, capped by an optional `recurrence_until`.
- `src/Application/TaskWorkflowService.php` — `complete()` is where
  recurrence actually does something: completing a recurring task spawns
  its next occurrence (same title/description/priority/assignee, advanced
  due date) rather than just closing the loop.
- `src/Application/TaskReminderService.php` — the "reminders" milestone:
  `schedule()`/`dueReminders()`/`markSent()`. A task can have more than one
  reminder.
- `src/Application/TaskReminderDispatcher.php` and
  `TaskReminderDeliveryJob` — scheduling an email reminder persists it and
  immediately creates a delayed, idempotent `tasks.reminder` job. The worker
  delivers through Mail, links the message back to the task, and only then
  sets `sent_at`; transport failures remain eligible for Queue retry.
- `src/Application/TaskRelationService.php` — the "entity relations"
  milestone, thin wrapper over `RelationRepository`.
- `CreateTaskActionHandler` registers `tasks.create` with Automation. It
  interpolates `{{ dot.path }}` values from the trigger payload, creates the
  organization-scoped task, optionally links it to the triggering entity, and
  can schedule the existing Queue → Mail reminder path.
- `TaskRepository::dueBetween()` — the "calendar" milestone's actual query
  surface: tasks with a due date inside a range. This is the data a future
  calendar view would render.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/TaskTest.php` needs no database (pure recurrence/status
logic) and runs for real. Everything under `tests/Integration/` needs real
MySQL (see `../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The root `ProcessKontor` module provides a deliberately narrow first task
workflow: list and filter tasks, create or edit one, start it, complete or
cancel it, and archive or restore it. Completing a recurring task exposes the
next occurrence created by the existing workflow service. An assigned task can
also schedule email reminders and shows their scheduled/sent lifecycle.

## Not in scope for this substage

No API endpoints or calendar rendering. Task assignment (`assigned_to`) stores
a plain ProcessWire user id, matching `created_by`/`updated_by`'s convention
(kontor.md#10.4). Assignment itself does not notify; a reminder must be
scheduled explicitly, including when configured in a `tasks.create` action.
