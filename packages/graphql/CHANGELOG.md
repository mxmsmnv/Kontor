# Changelog

All notable changes to `kontor/graphql` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- API-exposed Custom Entities now appear automatically as typed
  `entities_{entity_key}` root queries through the shared API registry.
- Contacts is the first external business component proven end to end through
  the shared registry: its `Contact` type appears automatically in the admin
  schema explorer and live `/graphql` queries enforce `contacts:read`.
- First ProcessKontor admin vertical: shared resource/type and SDL explorer
  plus an authenticated query bench exposing permission and complexity errors.
- Initial alpha (Substage 8.2): `GraphQLQueryParser` (hand-rolled
  tokenizer + recursive-descent parser for a deliberately narrow query
  subset — no third-party GraphQL library); `GraphQLTypeMapper` +
  `SchemaRegistry` (the "schema registry"/"component types" milestones —
  built straight from `kontor/api`'s own `ApiResourceRegistry`, so a
  resource registered once is queryable through both REST and GraphQL;
  `toSdl()` for introspection/documentation); `GraphQLExecutor` (the
  "permission enforcement" milestone — requires a
  `"{resourceKey}:read"` scope on the authenticated `kontor/api`
  `ApiToken`, reusing its `hasScope()`; one selection failing doesn't
  abort the rest); `GraphQLComplexityCalculator` (the "complexity
  limits" milestone — `fieldCount × rowMultiplier` per selection,
  rejects the whole query up front past a configurable maximum);
  `GraphQLRequestHandler` (the real request/response entry point,
  reusing `kontor/api`'s own DTOs) and `KontorGraphQL::hookGraphQLRequest()`
  (the real `/graphql` HTTP endpoint, deliberately not nested under
  `kontor/api`'s own `/api/kontor/v1/` prefix to avoid any collision with
  its hook); `GraphQLHealthCheck` (flags a type-name collision from
  `SchemaRegistry`'s naive singularization); en/fr/de/es translations.
  Second component of Stage 8 (API and external ecosystem); the first
  package in this monorepo to depend on another Stage 8 package
  (`kontor/api`) rather than only `kontor/core`.
