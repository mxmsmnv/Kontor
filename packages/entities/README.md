# Kontor Custom Entities

`kontor/entities` — entity builder, fields, relations, views,
permissions, and API exposure. Third component of Stage 7. No dedicated
schema section in kontor.md for this substage — full gap-fill. Depends
only on `kontor/core`.

## Relations reuse Core's own table

The "relations" milestone doesn't get a parallel custom-entities-only
table — `EntityRelationService` is a thin wrapper over
`Kontor\Core\Infrastructure\Persistence\RelationRepository`
(`kontor_relations`, kontor.md#11.7), the exact same reuse `kontor/tasks`'
own `TaskRelationService` already established for that repository. A
custom entity record's "entity type" for relation purposes is just its
definition's `entity_key`.

## Records are a generic table, not one table per entity type

`kontor_entity_records` stores every custom entity type's rows in one
physical table (`definition_uid` + `data_json`) — a dynamically-created
real table per entity type isn't feasible, since definitions are created
at runtime, long after this package's own migrations have already run.
Same deliberate EAV-style shape `kontor_extensions` (kontor.md#11.8)
already uses for arbitrary keyed metadata, just with real per-record
identity and lifecycle instead of a bag of key-value pairs.

## Contents

- `migrations/` — `kontor_entity_definitions` (`view_permission`/
  `edit_permission` are permission-name strings a caller is expected to
  have already checked, the same pattern `kontor/workflow`'s transitions
  use for their own `required_permission`; `api_exposed` is the "API
  exposure" milestone's flag), `kontor_entity_fields`, `kontor_entity_records`,
  `kontor_entity_views`.
- `src/Application/EntityBuilderService.php` — the "entity builder"
  milestone: `defineEntity()`/`addField()`. `EntityField::create()` itself
  rejects an unsupported `fieldType` (`string`/`int`/`decimal`/`bool`/
  `date`/`datetime` — the same small scalar-type vocabulary
  `kontor/reports`' `ReportSchema` uses for provider fields).
- `src/Application/EntityRecordService.php` — the "fields" milestone's
  other half: `create()`/`update()` validate a record's data against its
  definition's declared fields for real — required fields must be
  present, every value must match its field's type, and a key that isn't
  a declared field at all is rejected outright (catches typos rather than
  silently keeping dead data).
- `src/Application/EntityViewService.php` — the "views" milestone.
  `filterRecords()`/`sortRecords()` are pure functions (no database) doing
  real filter/sort logic — the same small operator vocabulary
  `kontor/automation`'s `ConditionEvaluator` uses (`equals`/`not_equals`/
  `greater_than`/`less_than`/`contains`), duplicated here rather than a
  cross-package dependency for a few lines of comparison logic (the same
  call made for `kontor/tasks`'/`kontor/reports`' own recurrence-interval
  tables). `apply()` is the DB-touching convenience wrapper around them.
- `src/Application/EntitySchemaService.php` — the "API exposure"
  milestone. `describe()`/`describeExposed()` shape the dynamic contract and
  mark which entities opt in via `api_exposed`.
- `src/Infrastructure/API/CustomEntityResource.php` — turns every active,
  exposed entity key into an `entities_{entity_key}` REST resource. The
  resource resolves the caller's organization-specific definition, validates
  writes through `EntityRecordService`, paginates records, and soft-deletes
  them. GraphQL discovers the same resource automatically through API's shared
  registry.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/EntityFieldTest.php` (field-type validation) and
`tests/Unit/Application/EntityViewServiceTest.php` (`filterRecords()`/
`sortRecords()`) need no database and run for real. Everything under
`tests/Integration/` needs real MySQL (see `../../docker-compose.test.yml`)
and is skipped otherwise, same `KONTOR_TEST_DB_DSN` convention as the
other packages.

## Not in scope for this substage

No field types beyond the six scalar ones (no file/reference/relation
field type — cross-entity linking goes through `EntityRelationService`
instead of a field).
