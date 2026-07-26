# Changelog

All notable changes to `kontor/api` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- REST authentication now enforces `{resource}:read` for list/find and
  `{resource}:write` for create/update/delete instead of only validating the
  bearer secret.

### Added

- Custom Entities now supplies dynamic `entities_{entity_key}` resources to
  the shared registry, using the existing authentication and CRUD pipeline.
- Contacts is the first external business-resource adopter, registering full
  `/contacts` CRUD, pagination, filters, sparse fields, and soft delete.
- First ProcessKontor admin vertical: one-time token issuance, revocation,
  resource/OpenAPI discovery, webhook subscription management, and recent
  delivery observability.
- Initial alpha (Substage 8.1): `kontor_api_tokens`,
  `kontor_webhook_subscriptions`, `kontor_webhook_deliveries`,
  `kontor_idempotency_keys` migrations (kontor.md#20, full gap-fill —
  no dedicated schema section for this component); `ApiToken`/
  `WebhookSubscription`/`WebhookDelivery` domain objects;
  `ApiTokenRepository`/`WebhookSubscriptionRepository`/
  `WebhookDeliveryRepository`/`IdempotencyKeyRepository`;
  `TokenAuthenticator` (the "authentication" milestone — one-time
  plaintext reveal, hash-only storage, scope checks, expiry);
  `ApiResourceInterface` + `ApiResourceRegistry` (the "CRUD resources"
  milestone's extension point — inverted dependency, same shape as every
  other registry in this monorepo) + `OrganizationResource` (the one
  built-in demonstrator, over Core's own organizations table);
  `RequestQueryParser`/`ApiFieldProjector` (the "filtering" milestone —
  pagination, filter, sort, sparse fields, include, all pure);
  `ApiRouter` (standard CRUD routes only; business sub-actions are each
  resource's own future work); `ApiResponseFactory` (kontor.md#20.2/20.3
  standard response/error envelopes); `OpenApiGenerator` (the "OpenAPI"
  milestone, generated straight from whatever's registered);
  `WebhookDispatcher`/`WebhookDeliveryService`/`WebhookBackoffCalculator`
  (the "webhooks" milestone — HMAC-SHA256 signature, exponential
  backoff, mutable delivery log, disable-after-repeated-failures,
  replay; subscribed onto `kontor/core`'s real `EventDispatcher` for
  every distinct active subscription's event, same "distinct triggers"
  approach as `kontor/automation`); `HttpClientInterface` +
  `CurlHttpClient` (the injectable outbound-HTTP boundary);
  `IdempotencyService` + `IdempotencyStoreInterface` (the "idempotency"
  milestone — caches a whole response envelope per
  organization + `Idempotency-Key`, conflicts on key reuse with a
  different body); `ApiRequestHandler` (the single true, DTO-only
  request/response entry point) and `KontorAPI::hookApiRequest()` (the
  real `/api/kontor/v1/` HTTP entry point via `ProcessPageView::execute`);
  `ApiHealthCheck`; permissions; en/fr/de/es translations. First
  component of Stage 8 (API and external ecosystem).
