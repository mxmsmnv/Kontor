<?php

/** @var \Kontor\Purchasing\Domain\PurchaseOrder $order */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Inventory\Domain\Warehouse[] $warehouses */
/** @var string $warehouseUid */
/** @var array<string, float> $outstanding */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>purchase-order/?id=<?= $e(rawurlencode($order->uid->toString())) ?>"><i class="fa fa-arrow-left"></i> Back to purchase order</a>
    <p class="kontor-eyebrow">Purchasing · Inventory</p>
    <h2>Receive goods · <?= $e($order->number ?? 'Purchase order') ?></h2>
    <p>Each received line creates an idempotent Inventory receipt movement in the same transaction.</p>
  </header>
  <?php if ($error !== ''): ?><div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <form class="kontor-card kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <input type="hidden" name="order_uid" value="<?= $e($order->uid->toString()) ?>">
    <label class="kontor-nativefield kontor-nativefield--wide"><span>Receiving warehouse *</span><select name="warehouse_uid" aria-label="Receiving warehouse" required><?php foreach ($warehouses as $warehouse): ?><option value="<?= $e($warehouse->uid->toString()) ?>"<?= $warehouseUid === $warehouse->uid->toString() ? ' selected' : '' ?>><?= $e($warehouse->code . ' · ' . $warehouse->name) ?></option><?php endforeach; ?></select></label>
    <?php foreach ($lines as $line): ?>
      <label class="kontor-nativefield kontor-nativefield--wide">
        <span><?= $e($line->title) ?> · ordered <?= $e(number_format($line->quantity, 3, '.', '')) ?> · outstanding <?= $e(number_format($outstanding[$line->uid->toString()] ?? 0, 3, '.', '')) ?></span>
        <input name="quantity_<?= $e($line->uid->toString()) ?>" type="number" min="0" max="<?= $e($outstanding[$line->uid->toString()] ?? 0) ?>" step="0.001" value="<?= $e($outstanding[$line->uid->toString()] ?? 0) ?>">
      </label>
    <?php endforeach; ?>
    <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_receive" value="1"><i class="fa fa-truck"></i> Record goods receipt</button><a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>purchase-order/?id=<?= $e(rawurlencode($order->uid->toString())) ?>">Cancel</a></div>
  </form>
</div>
