<?php

/** @var array{action: string, warehouseUid: string, destinationWarehouseUid: string, itemUid: string, quantity: string, unitCode: string, reason: string, idempotencyKey: string} $values */
/** @var string $error */
/** @var \Kontor\Inventory\Domain\Warehouse[] $warehouses */
/** @var array<string, array{label: string, unitCode: string}> $items */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>inventory/">
      <i class="fa fa-arrow-left"></i> Back to inventory
    </a>
    <p class="kontor-eyebrow">Inventory · Ledger</p>
    <h2>Complete stock movement</h2>
    <p>Receive, transfer, adjust, reserve, or release stock in one transaction.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <?php if ($warehouses === []): ?>
    <div class="kontor-warning">
      <i class="fa fa-exclamation-triangle"></i>
      <strong>Create an active warehouse before recording movements.</strong>
    </div>
  <?php endif; ?>

  <form class="kontor-card kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <input type="hidden" name="idempotency_key" value="<?= $e($values['idempotencyKey']) ?>">
    <label class="kontor-nativefield">
      <span>Movement *</span>
      <select name="action" aria-label="Movement">
        <?php foreach ([
          'receive' => 'Receive',
          'transfer' => 'Transfer',
          'adjust_increase' => 'Adjustment increase',
          'adjust_decrease' => 'Adjustment decrease',
          'reserve' => 'Reserve',
          'release' => 'Release',
        ] as $key => $label): ?>
          <option value="<?= $e($key) ?>"<?= $values['action'] === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Warehouse *</span>
      <select name="warehouse_uid" aria-label="Warehouse" required>
        <option value="">Select warehouse</option>
        <?php foreach ($warehouses as $warehouse): ?>
          <option value="<?= $e($warehouse->uid->toString()) ?>"<?= $values['warehouseUid'] === $warehouse->uid->toString() ? ' selected' : '' ?>>
            <?= $e($warehouse->code . ' · ' . $warehouse->name) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Transfer destination</span>
      <select name="destination_warehouse_uid" aria-label="Transfer destination">
        <option value="">Not a transfer</option>
        <?php foreach ($warehouses as $warehouse): ?>
          <option value="<?= $e($warehouse->uid->toString()) ?>"<?= $values['destinationWarehouseUid'] === $warehouse->uid->toString() ? ' selected' : '' ?>>
            <?= $e($warehouse->code . ' · ' . $warehouse->name) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Inventory item *</span>
      <?php if ($items !== []): ?>
        <select name="item_uid" aria-label="Inventory item" required>
          <option value="">Select tracked item</option>
          <?php foreach ($items as $uid => $item): ?>
            <option value="<?= $e($uid) ?>"<?= $values['itemUid'] === $uid ? ' selected' : '' ?>><?= $e($item['label']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input name="item_uid" value="<?= $e($values['itemUid']) ?>" placeholder="Catalog item UID" required>
      <?php endif; ?>
    </label>
    <label class="kontor-nativefield">
      <span>Quantity *</span>
      <input name="quantity" type="number" min="0.001" step="0.001" value="<?= $e($values['quantity']) ?>" required>
    </label>
    <label class="kontor-nativefield">
      <span>Unit</span>
      <input name="unit_code" value="<?= $e($values['unitCode']) ?>" maxlength="20">
    </label>
    <label class="kontor-nativefield kontor-nativefield--wide">
      <span>Reason / reference</span>
      <input name="reason" value="<?= $e($values['reason']) ?>" placeholder="Required for adjustments">
    </label>
    <div class="kontor-nativeform__actions">
      <button class="kontor-button" type="submit" name="submit_move" value="1"<?= $warehouses === [] ? ' disabled' : '' ?>>
        <i class="fa fa-exchange"></i> Complete movement
      </button>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>inventory/">Cancel</a>
    </div>
  </form>
</div>
