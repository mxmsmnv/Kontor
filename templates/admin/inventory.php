<?php

/** @var \Kontor\Inventory\Domain\Warehouse[] $warehouses */
/** @var array<string, string> $warehouseLabels */
/** @var array<string, string> $itemLabels */
/** @var \Kontor\Inventory\Domain\StockBalance[] $balances */
/** @var \Kontor\Inventory\Domain\InventoryMovement[] $movements */
/** @var bool $canManageWarehouses */
/** @var bool $canMoveStock */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Operations · Stock</p>
      <h2>Inventory</h2>
      <p>Warehouses, available stock, reservations, and the append-only movement ledger.</p>
    </div>
    <div class="kontor-pagehead__actions">
      <?php if ($canManageWarehouses): ?>
        <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>inventory-warehouse/">
          <i class="fa fa-building"></i> New warehouse
        </a>
      <?php endif; ?>
      <?php if ($canMoveStock): ?>
        <a class="kontor-button" href="<?= $e($adminUrl) ?>inventory-movement/">
          <i class="fa fa-exchange"></i> Stock movement
        </a>
      <?php endif; ?>
    </div>
  </header>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Locations</p><h3>Warehouses</h3></div>
    </header>
    <?php if ($warehouses !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Code</th><th>Warehouse</th><th>Status</th><th><span class="kontor-visually-hidden">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($warehouses as $warehouse): ?>
            <tr>
              <td><strong><?= $e($warehouse->code) ?></strong></td>
              <td><?= $e($warehouse->name) ?></td>
              <td><span class="kontor-pill<?= $warehouse->isActive() ? '' : ' kontor-pill--inactive' ?>"><?= $e($warehouse->status) ?></span></td>
              <td class="kontor-rowaction">
                <?php if ($canManageWarehouses): ?>
                  <form method="post" action="<?= $e($adminUrl) ?>inventory-warehouse-action/">
                    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                    <input type="hidden" name="warehouse_uid" value="<?= $e($warehouse->uid->toString()) ?>">
                    <input type="hidden" name="action" value="<?= $warehouse->isActive() ? 'deactivate' : 'activate' ?>">
                    <button type="submit" aria-label="<?= $warehouse->isActive() ? 'Deactivate warehouse' : 'Activate warehouse' ?>">
                      <i class="fa fa-<?= $warehouse->isActive() ? 'pause' : 'play' ?>"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty">
        <i class="fa fa-building"></i>
        <h3>No warehouses yet</h3>
        <p>Create a warehouse before receiving stock.</p>
      </div>
    <?php endif; ?>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Current position</p><h3>Stock balances</h3></div>
    </header>
    <?php if ($balances !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Item</th><th>Warehouse</th><th>On hand</th><th>Reserved</th><th>Available</th></tr></thead>
        <tbody>
          <?php foreach ($balances as $balance): ?>
            <tr>
              <td><?= $e($itemLabels[$balance->itemUid] ?? $balance->itemUid) ?></td>
              <td><?= $e($warehouseLabels[$balance->warehouseUid] ?? $balance->warehouseUid) ?></td>
              <td><?= $e(number_format($balance->quantityOnHand, 3, '.', '')) ?></td>
              <td><?= $e(number_format($balance->quantityReserved, 3, '.', '')) ?></td>
              <td><strong><?= $e(number_format($balance->quantityAvailable, 3, '.', '')) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty">
        <i class="fa fa-cubes"></i>
        <h3>No stock balances</h3>
        <p>The first completed movement will create a balance row.</p>
      </div>
    <?php endif; ?>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Append-only ledger</p><h3>Recent movements</h3></div>
    </header>
    <?php if ($movements !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>When</th><th>Type</th><th>Item</th><th>Route</th><th>Quantity</th><th>Reason</th></tr></thead>
        <tbody>
          <?php foreach ($movements as $movement): ?>
            <tr>
              <td><?= $e($movement->occurredAt->format('Y-m-d H:i')) ?></td>
              <td><span class="kontor-pill"><?= $e(str_replace('_', ' ', $movement->movementType)) ?></span></td>
              <td><?= $e($itemLabels[$movement->itemUid] ?? $movement->itemUid) ?></td>
              <td>
                <?= $e($movement->sourceWarehouseUid !== null
                  ? ($warehouseLabels[$movement->sourceWarehouseUid] ?? $movement->sourceWarehouseUid)
                  : 'External') ?>
                →
                <?= $e($movement->destinationWarehouseUid !== null
                  ? ($warehouseLabels[$movement->destinationWarehouseUid] ?? $movement->destinationWarehouseUid)
                  : 'External') ?>
              </td>
              <td><?= $e(number_format($movement->quantity, 3, '.', '')) ?> <?= $e($movement->unitCode) ?></td>
              <td><?= $e($movement->reason ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty">
        <i class="fa fa-exchange"></i>
        <h3>No movements yet</h3>
        <p>Receipts, transfers, adjustments, reservations, and releases will appear here.</p>
      </div>
    <?php endif; ?>
  </section>
</div>
