# Kontor Inventory

`kontor/inventory` — warehouses, stock balances, movements, reservations,
transfers, and barcode support. First component of Stage 6 (Operations,
kontor.md#36). Depends only on `kontor/core` — `item_uid` stays a loose
reference (kontor.md#10.7: "prefer stable IDs over direct foreign keys"),
not a hard dependency on `kontor/catalog`.

## Contents

- `migrations/` — `kontor_inventory_warehouses`, `kontor_inventory_balances`,
  `kontor_inventory_movements` (kontor.md#16.1–16.3, followed exactly
  including their reduced column sets: warehouses has no
  created_at/updated_at/version/archived_at at all — `status` is its own
  lifecycle marker; balances has no `uid`/`created_at` — a balance row's
  identity is the `(organization, warehouse, item)` triple, implicitly
  existing at zero before its first movement). Plus
  `kontor_inventory_barcodes`, this package's own gap-fill for the
  "barcode support" milestone (kontor.md#16 has no schema for it) — a
  scanned barcode resolves to an `item_uid`, nothing richer.
- `src/Application/InventoryMovementService.php` — implements kontor.md
  diagram 17.3 ("Inventory movement") for every movement type: validate
  the warehouse(s) are active, lock the balance row(s) `FOR UPDATE` inside
  a transaction (mirroring `SequenceService`'s own locking technique),
  check available stock where relevant, update balances, commit, emit
  `inventory.movement.completed`. `transfer()` locks its two balance rows
  in a consistent lexicographic order regardless of direction, so two
  concurrent opposite-direction transfers of the same warehouse pair can
  never deadlock against each other. Every method accepts an optional
  `idempotencyKey` — a repeat call with the same key returns the original
  movement instead of applying it twice (kontor.md#20.9), the same pattern
  `kontor/queue`'s `JobRepository::enqueue()` already established.
  - `receive()` / `transfer()` / `adjustIncrease()` / `adjustDecrease()` —
    move `quantity_on_hand` (and, for transfers, both warehouses' at once).
  - `reserve()` / `release()` — move `quantity_reserved` without touching
    `quantity_on_hand`; `shipReserved()` consumes on-hand and reserved stock
    together. `quantity_available` is always kept in sync as
    `on_hand - reserved`.
  - `adjustDecrease()`'s `$allowNegative` parameter is what
    `kontor-inventory-negative-stock-override` (kontor.md#19.8) gates —
    permission checks themselves belong to admin controllers/API endpoints
    per that section's own list, not this service.
- `src/Health/InventoryHealthCheck.php` — checks a real invariant (every
  balance row's `quantity_available` equals `on_hand - reserved`), not
  just a count — any drift means a write happened outside
  `BalanceRepository::updateQuantities()`.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/WarehouseTest.php` needs no database and runs for real.
Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The main Kontor Process module provides warehouse lifecycle controls,
catalog-backed tracked-item selection, every movement operation supported by
`InventoryMovementService`, current balances, and the append-only movement
ledger. Live module wiring also supplies Core's event dispatcher, so completed
admin movements publish `inventory.movement.completed`.
Inventory-tracked Sales orders use the same service to reserve on
confirmation, ship on completion, and release on cancellation.

## Not in scope for this substage

When Catalog is installed, the admin form selects active inventory-tracked
items; service-level callers still trust `item_uid`, the same looseness
`kontor_document_lines.item_uid` already has. No approval
workflow for "reject or request approval" (diagram 17.3's insufficient-
stock branch) — there's no workflow engine yet (kontor.md#18); a rejected
movement is just a thrown exception. No API endpoints — barcode scanning and
stock-count reconciliation tooling remain future work.
