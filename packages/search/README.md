# Kontor Search

`kontor/search` — a provider registry, federated global search, a reusable
SQL full-text provider, and an asynchronous indexing queue. Implements
`Kontor\SDK\Contracts\SearchProviderInterface` (spec section 9.8) and
registers itself as the `search` capability in Kontor Core's
`CapabilityRegistry`.

Separate component from Kontor Core (spec section 5.3), same independent-
package structure as `kontor/queue`/`kontor/files`/`kontor/cache`. Unlike
those, it depends on **kontor/queue directly** (not just `kontor/core`) —
the indexing queue milestone is dispatched through KontorQueue rather than
building a second queue. The ProcessWire module also requires
**kontor/cache** for short-lived federated query results.

## How it fits together

- Business components each register a `SearchProviderInterface` for their
  own entity type into `SearchProviderRegistry` (Substage 2.4 "provider
  registry") — typically backed by `SqlFullTextSearchProvider` (Substage 2.4
  "SQL search"), a generic MySQL FULLTEXT query runner any component can
  configure instead of writing the query by hand.
- `GlobalSearchService` fans a query out to every provider matching the
  requested entity types (or all of them — Ctrl+K-style search across
  entities, settings and components at once, per spec section 28's
  "global search UI" / "command palette"), merges hits by score, and
  re-paginates the merged set. It implements `SearchProviderInterface`
  itself, which is what makes it registrable as the `search` capability
  the same way every other capability maps to its own SDK contract. Results
  are cached for 30 seconds in the `search` namespace; the cache key includes
  organization, term, entity filters and pagination.
- `ComponentsSearchProvider` is a real, working provider (not a stub) that
  searches installed components via Core's `ComponentRegistry` — "components
  search" from section 28, registered automatically.
- `SearchIndexJob` + `SearchIndexerRegistry` + `SearchIndexDispatcher` are
  the "indexing queue" milestone: after writing an entity, a component
  calls `SearchIndexDispatcher::reindex()`, which dispatches a job through
  KontorQueue; the worker reconstructs it with the real
  `SearchIndexerRegistry` (via `JobRegistry`'s factory-closure pattern from
  Substage 2.1) and pushes to whatever `SearchIndexerInterface`
  implementations are registered for that entity type. A successful job
  invalidates the cache's `results` tag. Pure SQL FULLTEXT
  needs no indexer at all — the index lives in the same table, maintained
  automatically by MySQL. This only matters once an external engine
  (Meilisearch, Typesense, OpenSearch, Elasticsearch — section 28) is
  wired in as a separate component.

No database table of its own: `SqlFullTextSearchProvider` queries other
components' own tables directly (federated, not centralized), so there's
nothing here to migrate.

## A caught bug worth noting

`SqlFullTextSearchProvider` resolves `SearchQuery->organizationId` (the
public uid, per spec section 10.3) to the real `organization_id` `BIGINT`
column (spec section 10.4) via `OrganizationRepository` *before* binding it
— this is the exact bug `AuditLogger` had in Substage 1.3 (binding a uid
string against a BIGINT FK column), caught here before shipping instead of
after.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages.
