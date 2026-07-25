# Changelog

All notable changes to `kontor/crm` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

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
