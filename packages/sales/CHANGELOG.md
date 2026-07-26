# Changelog

All notable changes to `kontor/sales` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Inventory-tracked order lines now reserve stock during confirmation, ship
  it on completion, and release reservations when a confirmed order is
  cancelled.
- Payment allocations against an order-backed invoice now drive the Sales
  order's payment status, including partial payments and reversals.
- Won CRM deals can now prefill quotation drafts; saved quotations retain the
  deal UID and can be discovered from the deal workspace.
- Quotation issuance now resolves `quotation.standard`, persists the exact
  template UID and immutable snapshot, and stores a confidential PDF through
  Files in the same database transaction.
- Shared admin integration for quotation and order lifecycle workflows.
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
