# Kontor API

`kontor/api` — authentication, CRUD resource registry, filtering,
OpenAPI, webhooks, idempotency. First component of Stage 8 (API and
external ecosystem). kontor.md#20 gives the full REST specification
(`/api/kontor/v1/`, standard response/error envelopes, pagination,
filtering, sorting, sparse fields, relation expansion, idempotency,
webhooks); this substage's milestones are the six named in its own
title, all built against that spec. Depends only on `kontor/core`.

## Resources are registered, not owned

`kontor/api` never depends on a business component's classes.
`ApiResourceInterface` (`src/Contracts/`) is this package's own extension
point — a business component implements it and registers into
`ApiResourceRegistry`, the exact same inverted-dependency shape every
other registry in this monorepo already uses (`ReportProviderRegistry`,
`WidgetRegistry`, `ActionHandlerRegistry`). `OrganizationResource` is the
trivial built-in resource proving the pipeline over Core. `kontor/contacts`
is now the first business adopter: it registers `/contacts` with standard
CRUD, pagination, filters, sparse fields, soft delete, and idempotent
creation. Further business endpoints remain owned by their components.

## The real HTTP entry point is a thin wrapper over a pure core

`ApiRequestHandler::handle(ApiHttpRequest): ApiHttpResponse` is the
single true request/response cycle — routing, authentication, query
parsing, CRUD dispatch, idempotency, error envelopes — and takes/returns
plain DTOs with no superglobals or `$this->wire()` dependency, so it's
fully unit- and integration-tested without requiring a live HTTP server.
`KontorAPI::hookApiRequest()` (hooked onto `ProcessPageView::execute`,
guarded to the `api/kontor/v1` path prefix) is the thin ProcessWire-glue
translator from a real request to `ApiHttpRequest` and back — the same
"pure core, thin I/O wrapper" split used throughout this monorepo for
testability. The Contacts adopter also exercises that glue through the live
local HTTPS endpoint, covering bearer authentication and the real
`POST`/`PATCH`/filtered `GET`/`DELETE` request cycle.

## Contents

- `migrations/` — `kontor_api_tokens` (authentication — only
  `token_hash` is ever stored), `kontor_webhook_subscriptions`,
  `kontor_webhook_deliveries` (mutated across retries, not
  append-only), `kontor_idempotency_keys` (caches a whole response
  envelope per organization + `Idempotency-Key`, a distinct concern from
  `kontor/inventory`'s own per-record `idempotency_key` column). Full
  gap-fill — no dedicated schema section in kontor.md for this
  component.
- `src/Application/TokenAuthenticator.php` — the "authentication"
  milestone: `issue()` returns the plaintext token exactly once;
  `authenticate()` hashes, looks up, and checks active/expired/scope.
  ProcessWire session authentication (kontor.md#20.1's other supported
  method) is a separate path left to the HTTP-glue layer, since it needs
  the live `$session`/`$user` API this class deliberately avoids.
- `src/Contracts/ApiResourceInterface.php` /
  `src/Infrastructure/Registry/ApiResourceRegistry.php` — the "CRUD
  resources" milestone's extension point (see above).
- `src/Application/RequestQueryParser.php` / `ApiFieldProjector.php` —
  the "filtering" milestone: pagination (`page[number]`/`page[size]`,
  capped at 200), `filter[x]`, `sort` (`-field` for descending), sparse
  `fields[resource]`, and `include` — all pure, parsed from the
  already-decoded query-string array `parse_str()` produces.
- `src/Application/ApiRouter.php` — matches method + path to a
  registered resource's standard CRUD action. Only the five standard
  routes; kontor.md#20.10's own sub-action routes (`POST
  /leads/{uid}/convert`, …) are each business component's own concern to
  add when it registers its resource.
- `src/Application/OpenApiGenerator.php` — the "OpenAPI" milestone:
  generates a 3.0 document straight from whatever's currently registered
  in `ApiResourceRegistry`, so it always matches exactly what the router
  will accept.
- `src/Application/WebhookDispatcher.php` / `WebhookDeliveryService.php`
  / `WebhookBackoffCalculator.php` — the "webhooks" milestone:
  HMAC-SHA256 signature, exponential backoff retries (pure decision
  logic extracted into the calculator), a mutable delivery log, disabling
  a subscription after repeated consecutive failures, and `replay()`.
  Subscribed onto Core's real `EventDispatcher` for every distinct active
  subscription's event — the same "distinct triggers" approach
  `kontor/automation` already established (delivery runs synchronously,
  inline with the triggering event, since Core's dispatcher itself is
  synchronous — routing through `kontor/queue` for async delivery is a
  deliberately deferred enhancement, not a requirement of this
  milestone). `src/Contracts/HttpClientInterface.php` is the injectable
  I/O boundary; `src/Infrastructure/Http/CurlHttpClient.php` is the real
  implementation.
- `src/Application/IdempotencyService.php` — the "idempotency"
  milestone: caches a whole response envelope per `Idempotency-Key`;
  reusing a key with a different request body is a conflict (409), not a
  silent cache hit.
- `src/Health/ApiHealthCheck.php` — flags disabled webhook subscriptions
  and exhausted deliveries, not just a row count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Everything under `tests/Unit/` needs no database — `ApiRouter`,
`RequestQueryParser`, `ApiFieldProjector`, `ApiResponseFactory`,
`OpenApiGenerator`, `WebhookBackoffCalculator`, `IdempotencyService`
(against a fake `IdempotencyStoreInterface`), the `ApiToken`/
`WebhookSubscription`/`WebhookDelivery` domain objects, and
`OrganizationResource`'s unsupported-operation paths all run for real.
Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, including
`WebhookDeliveryServiceTest` (against a fake `HttpClientInterface`, since
this sandbox has no outbound network access) and `ApiRequestHandlerTest`
(a full pass through auth, routing, CRUD, and filtering via
`ApiRequestHandler::handle()`) — the first real consumers of
`Kontor\Core\Testing\DatabaseTestCase` (Substage 7.4) outside
`kontor/core` itself.

## Not in scope for this substage

No async webhook delivery (kontor/queue integration) — synchronous,
inline delivery only. No per-route scope enforcement (a token's scopes
exist and are checked when explicitly requested, but `ApiRequestHandler`
doesn't yet map a route to a required scope automatically). No
ProcessWire session authentication wired into `ApiRequestHandler` yet —
only bearer tokens. No GraphQL (that's Substage 8.2's own job). Contacts is
the first business resource; invoices, quotations, and other components still
need to register their own adapters.
