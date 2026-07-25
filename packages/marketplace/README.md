# Kontor Marketplace

`kontor/marketplace` — official registry, custom registry, component
metadata, advisories, publisher model. Third and final component of
Stage 8. kontor.md doesn't give a detailed marketplace specification —
full gap-fill, this substage's milestones are the five named in its own
title. Depends only on `kontor/core` — reuses its
`ComponentManifest`/`DependencyChecker`/`VersionConstraint` directly
rather than a parallel implementation.

## Instance-wide, like Core's own component registry

Which components are known/available to *this* Kontor installation isn't
a per-organization concern, the same reasoning
`Kontor\Core\Infrastructure\Registry\ComponentRegistry` (`kontor_components`,
kontor.md#11.2) already follows — every table here has no
`organization_id` and is addressed by `name` rather than a `uid` (except
`kontor_marketplace_advisories`, which does get a `uid` since it's more
of an identity-bearing record than a keyed-by-name row).

## One registry sync, four milestones at once

A registry (`Registry` domain: `name`, `url`, `type` — `official` or
`custom` — and `trusted`) serves a single JSON payload shaped
`{"components": [...], "advisories": [...]}`, where each component entry
is a real kontor.json (kontor.md#22.1). `RegistrySyncService::sync()`
fetches it once and, in one pass:

- **component metadata**: each entry is parsed with
  `Kontor\Core\Domain\ComponentManifest::fromArray()` directly — the
  exact same validation Core's own `LocalDiscovery` applies to a
  locally-installed component's manifest — and upserted into
  `MarketplaceListing`, keeping the full raw manifest alongside a few
  surfaced columns (title/description/license/repository).
- **publisher model**: the entry's `author` becomes a `Publisher`,
  upserted by name so every listing from the same author shares one
  identity. A publisher only ever becomes `verified` the first time it's
  seen via a **trusted** registry sync, and a later untrusted sync never
  un-verifies it.
- **advisories**: the payload's own `advisories` array is ingested
  alongside its components — a deliberate simplification over a separate
  feed, since this substage doesn't require one. Re-syncing the same
  payload doesn't duplicate an advisory (`AdvisoryRepository::findExisting()`).

A malformed entry (missing a required manifest field, or a missing
required advisory field) is skipped and reported in
`RegistrySyncResult::$skipped` — one bad entry doesn't abort the rest of
the sync, the same "one failure doesn't stop the rest" behavior used
throughout this monorepo.

`RegistryManagementService` is the "official"/"custom registry"
milestones' management side — `"official"` is a reserved name, seeded
once by `KontorMarketplace::___install()`; this service only ever
adds/enables/disables *custom* registries.

## Recommends, never installs

`InstallabilityChecker` answers "can/should I install this listing?" by
combining `kontor/core`'s own `DependencyChecker` (requires/conflicts
against what's currently installed) with `AdvisoryService` (does an open
**critical** advisory affect this exact version — a lower-severity
advisory is surfaced but doesn't block on its own). Actually installing
a component remains `Kontor\Core\Application\ComponentManager`'s own job
(Substage 1.3) — not retrofitted here.

## Contents

- `migrations/` — `kontor_marketplace_registries`,
  `kontor_marketplace_publishers`, `kontor_marketplace_listings`
  (unique per `(registry_name, package)` — the same component can appear
  in more than one registry), `kontor_marketplace_advisories`.
- `src/Contracts/RegistryClientInterface.php` /
  `src/Infrastructure/Http/CurlRegistryClient.php` — the injectable
  outbound-HTTP boundary (a GET fetch; a distinct interface from
  `kontor/api`'s own `HttpClientInterface`, which is POST-only for
  webhook delivery).
- `src/Application/RegistrySyncService.php` — see above.
- `src/Application/AdvisoryService.php` — version-matching/severity
  logic split into pure static methods (`filterAffecting()`/
  `containsCritical()`) so it's unit-testable without a database.
- `src/Application/InstallabilityChecker.php` — see above.
- `src/Health/MarketplaceHealthCheck.php` — flags a registry that hasn't
  synced in over 30 days, and any listing affected by an open critical
  advisory — not just a row count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/` (`Registry`/`Publisher` domain mutators,
`AdvisoryService`'s pure filtering, `RegistryManagementService`'s
reserved-name guard) needs no database and runs for real.
`tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise —
`RegistrySyncServiceTest` uses a fake `RegistryClientInterface` since
this sandbox has no outbound network access, the third real consumer of
`Kontor\Core\Testing\DatabaseTestCase` outside `kontor/core`, after
`kontor/api` and `kontor/graphql`.

## Not in scope for this substage

No actual package download/installation — Marketplace only discovers and
recommends; installing goes through `ComponentManager`. No separate
advisory feed — bundled into the same registry payload as components
(see above). No registry authentication (a private custom registry
requiring credentials isn't supported by `RegistryClientInterface` yet).
No admin UI — same deferral every other component in this monorepo has
made, since none exists yet anywhere.
