# Changelog

All notable changes to `kontor/sales` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Organization-scoped quotation and order search, status, archive, count, and
  pagination queries for the shared admin workflow.
- Order lookup by source quotation.

### Fixed

- Reject repeated conversion of the same accepted quotation into multiple
  orders.
- Keep the component registry version synchronized during module upgrades.

### Added

- Initial alpha (Substage 4.1): `kontor_sales_quotations`,
  `kontor_sales_orders`, `kontor_document_lines` migrations;
  `Quotation`/`Order`/`DocumentLine` domain objects — `DocumentLine` with
  real subtotal/discount/tax/total calculation; `QuotationRepository`/
  `OrderRepository` (implementing `RepositoryInterface`),
  `DocumentLineRepository`; `QuotationWorkflowService` (draft → issued →
  sent → accepted/rejected/expired/cancelled) and `OrderWorkflowService`
  (pending → confirmed → completed/cancelled);
  `QuotationToOrderConversionService` (copies lines into a new order on
  acceptance); `SalesHealthCheck`; permissions (kontor.md#19.5); en/fr/de/es
  translations.
