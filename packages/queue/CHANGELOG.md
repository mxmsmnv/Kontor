# Changelog

All notable changes to `kontor/queue` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Combined active-job filtering across pending and reserved states for the
  Queue monitor.
- Paginated recent-job queries with exact queue/status counts for the admin
  monitor.
- Bounded recent-job queries, status summaries, and queue discovery for the
  ProcessKontor queue monitor.
- State-guarded dead-letter retry support for the Queue monitor.
- Initial alpha (Substage 2.1): `kontor_jobs` migration; `JobRepository`
  (locked reservation via `FOR UPDATE SKIP LOCKED`, idempotency keys,
  retry/dead-letter/progress transitions); `Queue` (`QueueInterface`);
  `QueueWorker` with exponential backoff and dead-lettering;
  `JobRegistry`; `QueueHealthCheck`; `bin/queue` CLI worker;
  `KontorQueue.module.php` registering the `queue` capability into Kontor
  Core.
