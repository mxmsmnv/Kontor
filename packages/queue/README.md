# Kontor Queue

`kontor/queue` — asynchronous and delayed jobs, retries with exponential
backoff, a dead-letter queue, priorities, progress reporting, and a CLI
worker. Implements `Kontor\SDK\Contracts\QueueInterface` (spec section 9.10)
and registers itself as the `queue` capability in Kontor Core's
`CapabilityRegistry`, so other components dispatch jobs through that
capability rather than depending on this package directly.

This is a **separate component** from Kontor Core (spec section 5.3,
platform repositories) — it depends on `kontor/core` for the shared
migration runner and registries, not the other way around.

## Contents

- `KontorQueue.module.php` — bootstrap module; installs `kontor_jobs`
  (kontor.md#11.5) and registers the `queue` capability.
- `src/Infrastructure/Persistence/JobRepository.php` — MySQL persistence.
  Reservation uses `SELECT ... FOR UPDATE SKIP LOCKED` inside a transaction
  so concurrent workers never grab the same job (MySQL 8.0.1+).
- `src/Infrastructure/Queue.php` — the `QueueInterface` implementation
  components actually call.
- `src/Application/QueueWorker.php` — reserves and executes due jobs;
  retries with backoff (`10s * 2^(attempts-1)`, capped) until
  `max_attempts`, then moves the job to the dead-letter state.
- `src/JobRegistry.php` — maps a stored `job_type` string back to a real
  job class, since the worker that reserves a job is often a different PHP
  process than the one that dispatched it.
- `src/Health/QueueHealthCheck.php` — critical if any job has been reserved
  too long (a crashed worker), warning if the dead-letter queue is non-empty.
- `bin/queue` — CLI: `work`, `job:dispatch`, `job:cancel`, `list`.

## Not in scope for this substage

Recurring jobs and a cron runner (spec section 31 lists them, but
Substage 2.1's milestones — job table, dispatcher, CLI worker, retries,
dead-letter queue, progress — don't) are a later enhancement.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as
`kontor/core`.

## CLI

```bash
KONTOR_DB_DSN="mysql:host=127.0.0.1;dbname=kontor" \
KONTOR_DB_USER=kontor KONTOR_DB_PASS=kontor \
bin/queue job:dispatch send.invoice '{"invoiceUid":"inv_01"}' --queue=default

bin/queue list --status=pending

# Actually executing jobs needs the real job classes registered:
bin/queue work default --jobs=register-jobs.php
```

`register-jobs.php` must return a callable that registers real job classes:

```php
<?php
return function (\Kontor\Queue\JobRegistry $registry): void {
    $registry->register('send.invoice', fn (array $payload) => new SendInvoiceJob($payload));
};
```
