# Kontor GraphQL

`kontor/graphql` — schema registry, component types, permission
enforcement, complexity limits. Second component of Stage 8. kontor.md
doesn't give a detailed GraphQL specification the way section 20 does
for REST — this substage's milestones are the four named in its own
title. Depends on `kontor/core` **and `kontor/api`** — a real
cross-package reuse, the same kind `kontor/invoices` made of
`kontor/sales`'s document lines.

## One resource registry, two query languages

`kontor/graphql` never maintains its own resource list. Every type in
its schema, and every record it can resolve, comes straight from
`kontor/api`'s own `ApiResourceRegistry` — a resource registered once
(whether that's `kontor/api`'s built-in `OrganizationResource` or
`kontor/contacts`'s `ContactResource`) is queryable through both REST and
GraphQL with no extra registration step. `SchemaRegistry` (the "schema
registry" milestone) turns each registered resource's `ApiResourceSchema`
into a `GraphQLObjectType` via `GraphQLTypeMapper` (the "component types"
milestone, since each resource comes from a business *component*).

## No third-party GraphQL library

`GraphQLQueryParser` is a small hand-rolled recursive-descent parser for
a deliberately narrow subset of GraphQL query syntax — the same
"avoid a heavy dependency for a narrow need" call `kontor/documents`
already made for its own template engine. Supported: an optional leading
`query` keyword, one or more root-level `resourceKey(arguments) { fields
}` selections, and scalar arguments (`uid` for a single record,
`page`/`pageSize` for a list, or a bare `true`/`false`). Nested object
arguments (`filter: { status: "active" }`) are out of scope for this
substage and rejected with a clear syntax error rather than silently
ignored — see the parser's own doc comment.

```graphql
{
  organizations(uid: "org_01ABCDEF...") {
    uid
    name
    status
  }
}
```

The first real business-resource vertical is `contacts`: installing and
enabling `KontorContacts` adds the `Contact` type and `contacts:read` scope
to the admin schema explorer and makes organization-scoped contact list/find
queries available at `/graphql` automatically.

## Permission enforcement and complexity limits

`GraphQLExecutor` requires the authenticated `kontor/api` `ApiToken` to
have a `"{resourceKey}:read"` scope before resolving a selection — reused
directly from `ApiToken::hasScope()`, not a second permission system.
One selection failing (unknown resource, missing scope, the resource
itself throwing) is reported in `errors` against that selection's own
key rather than aborting the whole query — the same "one failure doesn't
stop the rest" behavior `Kontor\Core\Infrastructure\Events\EventDispatcher`
and `kontor/automation`'s engine already use.

`GraphQLComplexityCalculator` scores a parsed query as
`fieldCount × rowMultiplier` summed across every selection (a
single-record selection has a multiplier of 1; a list selection's is its
`pageSize`, defaulting to 50 — the same default `kontor/api`'s own
`RequestQueryParser` uses) and rejects the whole query up front if the
total exceeds a configurable maximum (1000 by default) — a single
top-level error rather than partial data, since the point is to reject
expensive queries before any resource is even touched.

## The real HTTP entry point

Served at its own `/graphql` path — not nested under `kontor/api`'s
`/api/kontor/v1/` prefix. That's both the universal convention real
GraphQL APIs already follow and a deliberate way to avoid any path-prefix
collision with `KontorAPI::hookApiRequest()`'s own
`ProcessPageView::execute` hook, which this package never modifies (the
same "not retrofitted" discipline used throughout this monorepo).
`GraphQLRequestHandler::handle(ApiHttpRequest): ApiHttpResponse` — reusing
`kontor/api`'s own request/response DTOs — is the single true,
plain-data entry point; `KontorGraphQL::hookGraphQLRequest()` is the thin
ProcessWire-glue translator. The live local ProcessWire stack is exercised
with HTTPS POST requests using a persisted scoped API token and real MySQL
contact data in addition to the package's DTO-level integration tests.

## Contents

- `src/Application/GraphQLQueryParser.php` — tokenizer + recursive-descent
  parser, see above.
- `src/Application/GraphQLTypeMapper.php` / `SchemaRegistry.php` — the
  "schema registry"/"component types" milestones. `SchemaRegistry::toSdl()`
  renders a minimal SDL-like string for introspection/documentation; the
  query engine itself resolves directly against `ApiResourceRegistry`,
  not this string.
- `src/Application/GraphQLExecutor.php` — resolves a parsed document
  against `kontor/api`'s registry, reusing its `ApiQuery`/
  `ApiFieldProjector` directly. The "permission enforcement" milestone.
- `src/Application/GraphQLComplexityCalculator.php` — the "complexity
  limits" milestone, pure.
- `src/Application/GraphQLRequestHandler.php` — the real request/response
  cycle.
- `src/Health/GraphQLHealthCheck.php` — flags a GraphQL type-name
  collision between two distinct resource keys (`SchemaRegistry`'s
  singularization is a naive heuristic — see its own doc comment), not
  just a count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Everything under `tests/Unit/` needs no database — the parser, type
mapper, schema registry, complexity calculator, executor (against an
in-memory `FakeApiResource`), and health check all run for real.
`tests/Integration/GraphQLRequestHandlerTest.php` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise — reusing
`kontor/api`'s own `TokenAuthenticator`/`ApiTokenRepository` for real
authentication, the second real consumer of
`Kontor\Core\Testing\DatabaseTestCase` outside `kontor/core` itself,
after `kontor/api`.

## Not in scope for this substage

No mutations (`create`/`update`/`delete` via GraphQL) — read-only queries
only, matching this substage's own milestone list. No nested relation
selections or filter arguments beyond `uid`/`page`/`pageSize` — see the
parser's doc comment. No GraphQL subscriptions. No schema introspection
endpoint (`__schema`/`__type` queries) — `SchemaRegistry::toSdl()` exists
for humans/documentation, not as a queryable introspection field.
