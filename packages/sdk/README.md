# Kontor SDK

Canonical PHP contracts, DTOs, the event envelope and value objects shared by
Kontor Core and every Kontor component. See
[`docs/KONTOR-SPECIFICATION.md`](../../docs/KONTOR-SPECIFICATION.md) section 9
for the interfaces this package implements.

This package has no dependency on ProcessWire or Kontor Core — components
depend on it, never the other way around.

## Contents

- `Contracts/` — `ComponentInterface`, `CapabilityRegistryInterface`,
  `EventDispatcherInterface`, `RepositoryInterface`, `BackupProviderInterface`,
  `ImportProviderInterface`, `ExportProviderInterface`,
  `SearchProviderInterface`, `ReportProviderInterface`, `QueueInterface`,
  `StorageInterface`, `KontorAIProviderInterface`, `HealthCheckInterface`,
  `JobInterface`, `CacheInterface`, `CacheStoreInterface`,
  `LocalizationProviderInterface`.
- `DTO/` — request/response objects used by the contracts above.
- `Events/` — `KontorEvent`, the canonical event envelope (spec section 21).
- `ValueObjects/` — `Uid` (ULID), `Money` (minor units), `OrganizationId`.
- `Scaffolding/` — `ComponentScaffolder`/`EntityScaffolder`/
  `MigrationScaffolder`/`ReportScaffolder` (Substage 7.4's `make:*`
  milestones), driven by the `bin/kontor-make` CLI. See
  [`docs/COMPONENT-GUIDE.md`](docs/COMPONENT-GUIDE.md) for the
  conventions they reproduce and how to use them.

## Scaffolding a new component

```bash
bin/kontor-make make:component --name=Widgets --slug=widgets \
    --title="Kontor Widgets" --description="..." --target=../widgets
```

See [`docs/COMPONENT-GUIDE.md`](docs/COMPONENT-GUIDE.md) for the full
`make:*` command reference (`make:entity`, `make:migration`,
`make:report`) and every convention a Kontor component follows.

## Testing

```bash
composer install
vendor/bin/phpunit
```
