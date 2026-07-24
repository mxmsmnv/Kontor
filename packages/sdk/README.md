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
  `JobInterface`.
- `DTO/` — request/response objects used by the contracts above.
- `Events/` — `KontorEvent`, the canonical event envelope (spec section 21).
- `ValueObjects/` — `Uid` (ULID), `Money` (minor units), `OrganizationId`.

## Testing

```bash
composer install
vendor/bin/phpunit
```
