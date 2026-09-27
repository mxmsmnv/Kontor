# Changelog

All notable changes to `kontor/documents` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- The component manifest now declares Files as a hard dependency, matching the
  ProcessWire module metadata and PDF persistence path.
- Publishing a language for the first time no longer increments or archives
  the English fallback family.
- Restoring an older template version archives the currently active sibling,
  preserving one active version per key and language.
- The root ProcessWire runtime now installs `dompdf/dompdf`, so admin PDF
  rendering uses the same dependency graph loaded by `Kontor.module.php`.

### Added

- Invoice and credit-note issuance now consume `DocumentSnapshotBuilder`
  through `invoice.standard` and `credit_note.standard`.
- Sales quotation issuance is the first business-document consumer of
  `DocumentSnapshotBuilder`, resolving the active language-specific
  `quotation.standard` template.
- Rendered PDFs now flow into Kontor Files as private, entity-bound versions;
  file metadata retains the immutable template-and-data snapshot and the
  admin preview links directly to the stored output.
- First ProcessKontor admin vertical: template publishing and version ledger,
  designer-v1 markup, HTML/PDF preview rendering, immutable snapshot output,
  and archive/restore controls.
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
