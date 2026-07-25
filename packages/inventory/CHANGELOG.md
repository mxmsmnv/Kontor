# Changelog

All notable changes to `kontor/inventory` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 6.1): `kontor_inventory_warehouses`,
  `kontor_inventory_balances`, `kontor_inventory_movements` migrations
  (kontor.md#16.1–16.3) and `kontor_inventory_barcodes` (gap-fill for the
  "barcode support" milestone); `Warehouse`/`InventoryMovement` domain
  objects and `StockBalance` read model; `WarehouseRepository`
  (implementing `RepositoryInterface`), `BalanceRepository` (lock-and-get-
  or-create plus update, for use inside a transaction), `MovementRepository`
  (append-only, with idempotency-key lookup), `BarcodeRepository`;
  `InventoryMovementService` — `receive()`/`transfer()`/`adjustIncrease()`/
  `adjustDecrease()`/`reserve()`/`release()`, implementing kontor.md
  diagram 17.3 (validate warehouse -> lock balances -> check available
  stock -> update -> commit -> emit `inventory.movement.completed`),
  idempotent, with consistent lock ordering for transfers;
  `InventoryHealthCheck` (checks the on_hand/reserved/available invariant
  across every balance row, not just a count); permissions (kontor.md#19.8);
  en/fr/de/es translations. First component of Stage 6.
