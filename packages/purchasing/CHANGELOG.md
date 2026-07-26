# Changelog

All notable changes to `kontor/purchasing` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- First admin vertical: supplier directory and creation, organization-scoped
  purchase-order listing, one-line drafts, issue/cancel controls, and goods
  receipt entry that updates Inventory atomically.
- Initial alpha (Substage 6.2): `kontor_purchasing_suppliers`,
  `kontor_purchasing_orders`, `kontor_purchasing_receipts`,
  `kontor_purchasing_receipt_lines` migrations (full schema gap-fill, no
  dedicated spec section); `Supplier`/`PurchaseOrder`/`GoodsReceipt`/
  `GoodsReceiptLine` domain objects; `SupplierRepository`/
  `PurchaseOrderRepository` (implementing `RepositoryInterface`) and
  `GoodsReceiptRepository`/`GoodsReceiptLineRepository` (the latter with
  `totalReceivedFor()`, the cumulative-quantity query everything else
  builds on); `PurchaseOrderWorkflowService` (`issue()`/`cancel()`);
  `GoodsReceiptService::receive()` — validates against what's still
  outstanding, records the receipt, calls `kontor/inventory`'s
  `InventoryMovementService::receive()` per line for real (the "inventory
  integration" milestone), recomputes the order's status, all in one
  shared transaction; `PurchasingHealthCheck`; permissions; en/fr/de/es
  translations. Second component of Stage 6.
