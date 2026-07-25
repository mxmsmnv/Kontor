# Changelog

All notable changes to `kontor/documents` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 4.2): `kontor_documents_templates` migration
  (schema gap-fill, versioned like `kontor_files`); `DocumentTemplate`
  domain object; `TemplateRepository` (save/find/findCurrentVersion with
  English-language fallback/versionHistory/archive/restore);
  `TemplateManager` (publish workflow: new version archives the previous
  one); `TemplateEngine` (`{{field}}`/`{{#each}}`/`{{#if}}` — "document
  designer v1"); `PdfRenderer` (wraps `dompdf/dompdf`);
  `DocumentRenderService` (body HTML / HTML preview / PDF rendering, no
  persistence dependency); `DocumentSnapshotBuilder` (immutable
  issued-document snapshot payload for future consumers);
  `DocumentsHealthCheck` (real render + PDF round trip, no database
  needed); permissions (kontor.md#26); en/fr/de/es translations.
