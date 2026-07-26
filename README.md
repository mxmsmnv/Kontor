# Kontor

![Kontor — modular business operations, assembled together](assets/kontor-doodle.png)

Open-source modular ERP, CRM and business operations ecosystem for
ProcessWire. Contacts, sales, invoicing, inventory, projects, a REST
API and GraphQL layer, automation, a marketplace, and more — each as its
own independently installable component sharing one core.

## Features

- **Core** — service container, capability/component registries,
  migrations, audit log, backup & recovery, import/export (CSV, JSON,
  JSONL, XLSX), and the single `ProcessKontor` admin shell every
  component registers into
- **Platform** — async jobs & queue, private file storage with signed
  URLs, namespaced cache, federated search
- **CRM & sales** — contacts, companies, catalog & price lists, leads
  and pipelines, quotations, orders, invoices, payments and allocations
- **Collaboration** — tasks with recurrence, notes, threaded comments,
  mentions, personal/role dashboards, a report builder with scheduling
- **Operations** — warehouses & stock movements, purchasing with
  inventory integration, expense approvals, projects with time tracking
  and invoicing
- **Extensibility** — an entity-agnostic workflow engine, event-driven
  automation (trigger → conditions → actions), a custom-entity builder,
  and a scaffolding CLI (`make:component`, `make:entity`, …)
- **API & ecosystem** — a REST API (tokens, filtering, OpenAPI,
  webhooks, idempotency), a GraphQL layer, and a component marketplace
  (registries, advisories, publisher trust)
- **Advanced** — outbound/inbound mail, a customer self-service portal,
  an optional AI layer (summaries, drafting, extraction) with an
  approval workflow, and double-entry ledger foundations with a
  Germany localization package
- **Connected demo** — an executable order-to-cash reference vertical
  that creates real records across Contacts, CRM, Catalog, Workflow,
  Sales, Projects, Files, Mail, Invoices, Ledger and Payments

Every component is its own Composer package under `packages/`, each
independently versioned and — per the target architecture — destined
for its own repository; see
[`docs/PACKAGES.md`](docs/PACKAGES.md) for the full list.

## Requirements

- ProcessWire 3.0.240+
- PHP 8.2+
- MySQL 8.x / MariaDB where compatible

## Installation

Kontor is still pre-release and not yet installed against a live
ProcessWire instance — see [Status](#status). Once it is:

1. Copy this repository (and any `packages/` you want) into
   `site/modules/`
2. Run `composer install` in each package you're installing
3. In PW Admin go to **Modules → Refresh**, then install **Kontor**
   first, followed by whichever business components you need
4. Business components declare their own dependencies (e.g.
   `kontor/invoices` requires `kontor/sales`) — install order is
   enforced at install time

## Development

```bash
composer install
vendor/bin/phpunit
```

See [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) for per-package setup,
running the DB-gated integration tests against a real MySQL/MariaDB, the
`bin/kontor` recovery CLI, and import/export format details.

## Documentation

- [`docs/KONTOR-SPECIFICATION.md`](docs/KONTOR-SPECIFICATION.md) — the
  canonical architecture specification this entire ecosystem is built
  against
- [`docs/PACKAGES.md`](docs/PACKAGES.md) — every package, what it does,
  and how it fits together
- [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) — setup, testing, CLI
- [`packages/sdk/docs/COMPONENT-GUIDE.md`](packages/sdk/docs/COMPONENT-GUIDE.md) —
  conventions for building a new component, plus the `bin/kontor-make`
  scaffolding CLI

## Status

Every stage and substage of the specification's own development-stages
build plan (section 36) is built — see
[`docs/PACKAGES.md`](docs/PACKAGES.md#build-status) for the full
breakdown. Not yet installed against a live ProcessWire instance;
translation completeness, security review, load testing and the release
milestones (spec section 37) remain.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for Core's own changes, and each
package's own `CHANGELOG.md` for its history.

## License

MIT © Maxim Semenov
