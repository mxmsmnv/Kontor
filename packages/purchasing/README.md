# Kontor Purchasing

`kontor/purchasing` — suppliers, purchase orders, goods receipt, and
inventory integration. Second component of Stage 6. No dedicated schema
section, permission list, or workflow diagram in kontor.md for this
substage — the whole shape here is a gap-fill, same situation
Tasks/Collaboration/Dashboard/Reports were in, modeled on the equivalent
buy-side of what `kontor/sales`/`kontor/invoices` already built for the
sell-side.

## Two real dependencies, both load-bearing

- `kontor/sales` — purchase order lines are shared `kontor_document_lines`
  rows (`document_type = 'purchase_order'`), reusing `DocumentLine`/
  `DocumentLineRepository` directly, the same reuse `kontor/invoices`
  already established for that table.
- `kontor/inventory` — the "inventory integration" milestone isn't
  deferred here the way most cross-component wiring has been elsewhere in
  this monorepo: `GoodsReceiptService::receive()` calls
  `InventoryMovementService::receive()` for real, so recording a goods
  receipt actually moves the destination warehouse's stock balance.

## Contents

- `migrations/` — `kontor_purchasing_suppliers`, `kontor_purchasing_orders`
  (`warehouse_uid` is the default receiving destination — the link between
  the two packages), `kontor_purchasing_receipts` (a purchase order can be
  received across several partial shipments, so this is its own
  append-only event table, not a single flag on the order) and
  `kontor_purchasing_receipt_lines` (a lighter junction table, like
  `kontor_payment_allocations`).
- `src/Application/PurchaseOrderWorkflowService.php` — the "purchase
  orders" milestone's lifecycle: `issue()` (computes totals from lines,
  assigns a number via `SequenceService`, `PO-` prefix) and `cancel()`
  (blocked once any receiving has completed).
- `src/Application/GoodsReceiptService.php` — the "goods receipt" and
  "inventory integration" milestones together. `receive()`:
  1. Validates every requested line actually belongs to the order and
     that the requested quantity doesn't exceed what's still outstanding
     (`ordered - already received`, summed across every prior receipt).
  2. Records the receipt and its lines.
  3. Calls `InventoryMovementService::receive()` per line — real stock
     movement, idempotent via the receipt line's own uid as the
     movement's idempotency key.
  4. Recomputes the order's status from cumulative receipt totals:
     `partially_received` once any line has received stock,
     `received` once every line is fully received.

  Everything runs inside one transaction shared with
  `InventoryMovementService` (which detects the ambient transaction and
  defers commit/rollback to it), so a receipt and the stock movements it
  causes are atomic together — a failure partway through can't leave the
  purchasing side and the inventory side disagreeing.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/PurchaseOrderTest.php` needs no database (pure status/
totals logic, reusing `kontor/sales`' real `DocumentLine` calculation) and
runs for real. Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The main Kontor Process module now exposes supplier creation, purchase-order
drafting and issue, and goods receipt entry. The first workflow uses a real
inventory-tracked Catalog item and active Inventory warehouse, so completing
the receipt exercises the package's atomic cross-component stock update.

## Not in scope for this substage

No supplier price lists or lead times — a purchase order's line prices are
entered directly, the same way `kontor/sales`' quotations work. No
approval workflow for issuing a purchase order — there's no workflow
engine yet (kontor.md#18). No API endpoints.
