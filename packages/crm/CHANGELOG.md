# Changelog

All notable changes to `kontor/crm` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Won deals with a customer now connect directly to prefilled Sales quotation
  drafts and list every active quotation created from the deal.
- Organization-scoped lead search, status filtering, archive listing, exact
  counts, and pagination support for the Core admin workflow.
- Organization-scoped deal search/count/archive queries for the admin board,
  including active/archived stage loading.

### Fixed

- Reject moves to stages belonging to a different pipeline and keep only one
  default pipeline per organization/entity type.
- Register CRM import and export providers with the entity-type keys required
  by the current Core registry API.
- Yield associative export rows so sparse field selections do not include
  duplicate numeric PDO indexes.
- Keep the component registry version synchronized during module upgrades.

### Added

- Initial alpha (Substage 3.3), following kontor.md#22.1's canonical
  manifest example: `kontor_crm_leads`/`kontor_crm_pipelines`/
  `kontor_crm_stages`/`kontor_crm_deals` migrations; `Lead`/`Pipeline`/
  `Stage`/`Deal` domain objects; `LeadRepository`/`DealRepository`
  (implementing `RepositoryInterface`, registered for import rollback),
  `PipelineRepository`, `StageRepository`; `CRMServiceInterface` +
  `CRMService` (the "crm" capability); `LeadConversionService`
  (lead-to-deal conversion); `KanbanBoardService`; `PipelineReportProvider`
  (first real `ReportProviderInterface` consumer); real
  `LeadImportProvider`/`DealImportProvider`/`LeadExportProvider`/
  `DealExportProvider`; search registration via `kontor/search`;
  `CRMHealthCheck`; permissions (kontor.md#19.4); en/fr/de/es
  translations.
