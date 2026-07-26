<?php

/** @var \Kontor\Purchasing\Domain\PurchaseOrder|null $order */
/** @var array{supplierUid: string, warehouseUid: string, itemUid: string, quantity: string, unitPrice: string, currencyCode: string, expectedDate: string} $values */
/** @var string $error */
/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var \Kontor\Inventory\Domain\Warehouse[] $warehouses */
/** @var array<string, array{label: string, unitCode: string}> $items */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Purchasing\Domain\GoodsReceipt[] $receipts */
/** @var bool $canIssue */
/** @var bool $canReceive */
/** @var bool $canCancel */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Back to purchasing</a>
    <p class="kontor-eyebrow">Purchasing · Order</p>
    <h2><?= $e($order?->number ?? ($order === null ? 'Create purchase order' : 'Draft purchase order')) ?></h2>
    <p><?= $order === null ? 'Create a one-line draft for an inventory-tracked item.' : 'Issue the order, then record one or more goods receipts.' ?></p>
  </header>
  <?php if ($error !== ''): ?><div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div><?php endif; ?>

  <?php if ($order === null): ?>
    <form class="kontor-card kontor-nativeform" method="post" action="./">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Supplier *</span><select name="supplier_uid" aria-label="Supplier" required><option value="">Select supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= $e($supplier->uid->toString()) ?>"<?= $values['supplierUid'] === $supplier->uid->toString() ? ' selected' : '' ?>><?= $e($supplier->code . ' · ' . $supplier->legalName) ?></option><?php endforeach; ?></select></label>
      <label class="kontor-nativefield"><span>Receiving warehouse *</span><select name="warehouse_uid" aria-label="Receiving warehouse" required><option value="">Select warehouse</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= $e($warehouse->uid->toString()) ?>"<?= $values['warehouseUid'] === $warehouse->uid->toString() ? ' selected' : '' ?>><?= $e($warehouse->code . ' · ' . $warehouse->name) ?></option><?php endforeach; ?></select></label>
      <label class="kontor-nativefield"><span>Item *</span><select name="item_uid" aria-label="Item" required><option value="">Select tracked item</option><?php foreach ($items as $uid => $item): ?><option value="<?= $e($uid) ?>"<?= $values['itemUid'] === $uid ? ' selected' : '' ?>><?= $e($item['label']) ?></option><?php endforeach; ?></select></label>
      <label class="kontor-nativefield"><span>Quantity *</span><input name="quantity" type="number" min="0.001" step="0.001" value="<?= $e($values['quantity']) ?>" required></label>
      <label class="kontor-nativefield"><span>Unit price *</span><input name="unit_price" type="number" min="0" step="0.01" value="<?= $e($values['unitPrice']) ?>" required></label>
      <label class="kontor-nativefield"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" required></label>
      <label class="kontor-nativefield"><span>Expected date</span><input name="expected_date" type="date" value="<?= $e($values['expectedDate']) ?>"></label>
      <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Create draft</button><a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>purchasing/">Cancel</a></div>
    </form>
  <?php else: ?>
    <section class="kontor-card">
      <div class="kontor-detailgrid">
        <div><span>Status</span><strong><?= $e(str_replace('_', ' ', $order->status)) ?></strong></div>
        <div><span>Total</span><strong><?= $e($money($order->total)) ?></strong></div>
        <div><span>Expected</span><strong><?= $e($order->expectedDate?->format('Y-m-d') ?? '—') ?></strong></div>
        <div><span>Receipts</span><strong><?= $e(count($receipts)) ?></strong></div>
      </div>
      <div class="kontor-pagehead__actions">
        <?php if ($canIssue): ?><form method="post" action="<?= $e($adminUrl) ?>purchase-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><input type="hidden" name="action" value="issue"><button class="kontor-button" type="submit"><i class="fa fa-paper-plane"></i> Issue</button></form><?php endif; ?>
        <?php if ($canReceive): ?><a class="kontor-button" href="<?= $e($adminUrl) ?>purchasing-receipt/?order=<?= $e(rawurlencode($order->uid->toString())) ?>"><i class="fa fa-truck"></i> Receive goods</a><?php endif; ?>
        <?php if ($canCancel): ?><form method="post" action="<?= $e($adminUrl) ?>purchase-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><input type="hidden" name="action" value="cancel"><button class="kontor-button kontor-button--ghost" type="submit">Cancel order</button></form><?php endif; ?>
      </div>
    </section>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table"><thead><tr><th>Item</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead><tbody><?php foreach ($lines as $line): ?><tr><td><?= $e($line->title) ?></td><td><?= $e(number_format($line->quantity, 3, '.', '')) ?> <?= $e($line->unitCode) ?></td><td><?= $e($money($line->unitPrice)) ?></td><td><?= $e($money($line->total())) ?></td></tr><?php endforeach; ?></tbody></table>
    </section>
  <?php endif; ?>
</div>
