# Changelog

All notable changes to `kontor/entities` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- First ProcessKontor admin vertical: entity and typed-field builder, dynamic
  record forms, saved filters and sorting, cross-component relation links,
  permission-aware access, and visible API schema contracts.
- Initial alpha (Substage 7.3): `kontor_entity_definitions`,
  `kontor_entity_fields`, `kontor_entity_records` (generic EAV-style
  storage for every custom entity type), `kontor_entity_views` migrations
  (full schema gap-fill); `EntityDefinition`/`EntityField`/`EntityRecord`/
  `EntityView` domain objects; `EntityDefinitionRepository`
  (implementing `RepositoryInterface`)/`EntityFieldRepository`/
  `EntityRecordRepository` (with `countOrphaned()`)/`EntityViewRepository`;
  `EntityBuilderService` (`defineEntity()`/`addField()`);
  `EntityRecordService` (`create()`/`update()` — real validation against
  declared fields, not just storage); `EntityViewService`
  (`filterRecords()`/`sortRecords()` pure, `apply()` DB-backed);
  `EntityRelationService` (the "relations" milestone, reusing
  `kontor/core`'s `RelationRepository` directly); `EntitySchemaService`
  (`describe()`/`describeExposed()` — the "API exposure" milestone's data
  contract for a future Stage 8 REST/GraphQL layer); `EntitiesHealthCheck`
  (checks for orphaned records referencing a missing/archived definition,
  not just a count); permissions; en/fr/de/es translations. Third
  component of Stage 7.
