# Changelog

All notable changes to `kontor/invoices` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 4.3): `kontor_invoices` migration (kontor.md#15.3
  plus a `kind`/`credited_invoice_uid` gap-fill for credit notes);
  `Invoice` domain object (`paid`/`due` kept in sync via
  `applyTotalsFromLines()`); `InvoiceRepository` (implementing
  `RepositoryInterface`, plus `findSentPastDue()` for the overdue sweep);
  `InvoiceWorkflowService` (`issue()`/`send()`/`cancel()`,
  `markOverdue()`/`sweepOverdue()` for the "overdue state" milestone,
  `issueCreditNote()` for the "credit notes" milestone — full credit,
  negated lines, separate `CN-` sequence, original moves to `credited`);
  `InvoicesHealthCheck`; permissions (kontor.md#19.6); en/fr/de/es
  translations. First package with a real cross-package dependency on
  another business component (`kontor/sales`, for the shared
  `kontor_document_lines` table and `DocumentLine`/
  `DocumentLineRepository`).
