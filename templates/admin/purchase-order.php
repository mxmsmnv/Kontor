<?php

/** @var \Kontor\Purchasing\Domain\PurchaseOrder|null $order */
/** @var array{supplierUid: string, warehouseUid: string, itemUid: string, quantity: string, unitPrice: string, currencyCode: string, expectedDate: string} $values */
/** @var string $error */
/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var \Kontor\Inventory\Domain\Warehouse[] $warehouses */
/** @var array<string, array{label: string, unitCode: string}> $items */
/** @var string $supplierLabel */
/** @var string $warehouseLabel */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Purchasing\Domain\GoodsReceipt[] $receipts */
/** @var bool $canIssue */
/** @var bool $canReceive */
/** @var bool $canCancel */
/** @var bool $canCreateSupplier */
/** @var bool $canViewInventory */
/** @var bool $canManageWarehouses */
/** @var bool $canViewCatalog */
/** @var bool $canCreateCatalogItem */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = static fn (string $status): string => match ($status) {
    'issued', 'partially_received' => ' uk-label-warning',
    'received' => ' uk-label-success',
    'cancelled' => ' uk-label-danger',
    default => '',
};
$readyToCreate = $suppliers !== [] && $warehouses !== [] && $items !== [];
$missingCount = (int) ($suppliers === []) + (int) ($warehouses === []) + (int) ($items === []);
$orderUid = $order?->uid->toString() ?? '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back uk-link-reset" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Back to purchasing</a>
    <p class="kontor-eyebrow">Purchasing · Purchase order</p>
    <h2><?= $e($order?->number ?? ($order === null ? 'Create purchase order' : 'Draft purchase order')) ?></h2>
    <p><?= $order === null ? 'Prepare a reviewable supplier commitment with a destination, item, price and expected date.' : 'Follow the commitment from draft and issue through warehouse receipt.' ?></p>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-warning" uk-alert><p><i class="fa fa-exclamation-triangle uk-margin-small-right"></i><strong><?= $e($error) ?></strong></p></div><?php endif; ?>

  <?php if ($order === null): ?>
    <?php if (!$readyToCreate): ?><div class="uk-alert-warning uk-margin-medium-bottom" uk-alert><p><strong><?= $e((string) $missingCount) ?> prerequisite<?= $missingCount === 1 ? ' is' : 's are' ?> missing.</strong><br>Prepare the records highlighted below before creating a purchase-order draft.</p></div><?php endif; ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="./">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Draft foundation</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Define the supplier commitment</h3><p class="uk-text-muted uk-margin-small-top">The draft remains editable. Review it before issue creates an operational commitment.</p></div><div><span class="uk-label<?= $readyToCreate ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $readyToCreate ? 'Ready' : $e((string) $missingCount) . ' missing' ?></span></div></div>

          <fieldset class="uk-fieldset uk-margin-medium-top">
            <legend class="uk-legend">1. Choose supplier and destination</legend>
            <p class="uk-text-muted uk-margin-small-top">Connect the commercial partner to the warehouse that will receive the goods.</p>
            <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
              <label class="kontor-nativefield"><span>Supplier *</span><select name="supplier_uid" aria-label="Supplier" required<?= $suppliers === [] ? ' disabled' : '' ?>><option value=""><?= $suppliers === [] ? 'No active suppliers available' : 'Select supplier' ?></option><?php foreach ($suppliers as $supplier): ?><option value="<?= $e($supplier->uid->toString()) ?>"<?= $values['supplierUid'] === $supplier->uid->toString() ? ' selected' : '' ?>><?= $e($supplier->code . ' · ' . $supplier->legalName) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The approved vendor that will receive this purchase order.</span><span class="kontor-field-note"><strong>Note:</strong> Confirm currency and payment terms on the supplier record first.</span></span></label>
              <label class="kontor-nativefield"><span>Receiving warehouse *</span><select name="warehouse_uid" aria-label="Receiving warehouse" required<?= $warehouses === [] ? ' disabled' : '' ?>><option value=""><?= $warehouses === [] ? 'No active warehouses available' : 'Select warehouse' ?></option><?php foreach ($warehouses as $warehouse): ?><option value="<?= $e($warehouse->uid->toString()) ?>"<?= $values['warehouseUid'] === $warehouse->uid->toString() ? ' selected' : '' ?>><?= $e($warehouse->code . ' · ' . $warehouse->name) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The location where quantities will be recorded when the delivery arrives.</span><span class="kontor-field-note"><strong>Note:</strong> Choose the operational destination, not a billing address.</span></span></label>
            </div>
            <?php if ($suppliers === [] || $warehouses === []): ?><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-margin-small-top" uk-grid><?php if ($suppliers === []): ?><div><div class="uk-alert-warning" uk-alert><p><strong>No active supplier.</strong><br><?php if ($canCreateSupplier): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset uk-margin-small-top" href="<?= $e($adminUrl) ?>purchasing-supplier/">Create supplier <i class="fa fa-angle-right"></i></a><?php else: ?>Ask a purchasing administrator to create or activate one.<?php endif; ?></p></div></div><?php endif; ?><?php if ($warehouses === []): ?><div><div class="uk-alert-warning" uk-alert><p><strong>No receiving warehouse.</strong><br><?php if ($canManageWarehouses): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset uk-margin-small-top" href="<?= $e($adminUrl) ?>inventory-warehouse/">Create warehouse <i class="fa fa-angle-right"></i></a><?php elseif ($canViewInventory): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset uk-margin-small-top" href="<?= $e($adminUrl) ?>inventory/">Open inventory <i class="fa fa-angle-right"></i></a><?php else: ?>Ask an inventory administrator to prepare one.<?php endif; ?></p></div></div><?php endif; ?></div><?php endif; ?>
          </fieldset>

          <hr class="uk-margin-medium">
          <fieldset class="uk-fieldset">
            <legend class="uk-legend">2. Add the first order line</legend>
            <p class="uk-text-muted uk-margin-small-top">Begin with the inventory-tracked item you expect this supplier to deliver.</p>
            <div class="uk-grid-small" uk-grid>
              <label class="kontor-nativefield uk-width-1-1 uk-width-2-3@m"><span>Tracked item *</span><select name="item_uid" aria-label="Tracked item" required<?= $items === [] ? ' disabled' : '' ?>><option value=""><?= $items === [] ? 'No inventory-tracked items available' : 'Select tracked item' ?></option><?php foreach ($items as $uid => $item): ?><option value="<?= $e($uid) ?>"<?= $values['itemUid'] === $uid ? ' selected' : '' ?>><?= $e($item['label']) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The catalog product whose received quantity will update inventory.</span><span class="kontor-field-note"><strong>Note:</strong> Services and non-stock items are not available for warehouse receipt.</span></span></label>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-3@m"><span>Quantity *</span><input name="quantity" type="number" min="0.001" step="0.001" inputmode="decimal" value="<?= $e($values['quantity']) ?>" placeholder="1" required><span class="kontor-field-guidance"><span class="kontor-field-description">The quantity requested from the supplier.</span><span class="kontor-field-note"><strong>Note:</strong> Use decimals only when the item's unit supports them.</span></span></label>
            </div>
            <?php if ($items === []): ?><div class="uk-alert-warning uk-margin-small-top" uk-alert><p><strong>No inventory-tracked item is ready.</strong><br><?php if ($canCreateCatalogItem): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset uk-margin-small-top" href="<?= $e($adminUrl) ?>catalog-item/">Create catalog item <i class="fa fa-angle-right"></i></a><?php elseif ($canViewCatalog): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset uk-margin-small-top" href="<?= $e($adminUrl) ?>catalog/">Open catalog <i class="fa fa-angle-right"></i></a><?php else: ?>Ask a catalog administrator to create a product with inventory tracking enabled.<?php endif; ?></p></div><?php endif; ?>
          </fieldset>

          <hr class="uk-margin-medium">
          <fieldset class="uk-fieldset">
            <legend class="uk-legend">3. Confirm price and timing</legend>
            <p class="uk-text-muted uk-margin-small-top">Record the quoted unit price and the date the receiving team should plan around.</p>
            <div class="uk-grid-small" uk-grid>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-2@m"><span>Unit price *</span><div class="uk-inline uk-width-1-1"><span class="uk-form-icon"><i class="fa fa-money"></i></span><input name="unit_price" type="number" min="0" step="0.01" inputmode="decimal" value="<?= $e($values['unitPrice']) ?>" placeholder="129.90" required></div><span class="kontor-field-guidance"><span class="kontor-field-description">The supplier's price for one unit before tax.</span><span class="kontor-field-note"><strong>Note:</strong> Enter the amount without a currency symbol.</span></span></label>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-4@m"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" pattern="[A-Za-z]{3}" placeholder="EUR" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">The currency used for every value on this order.</span><span class="kontor-field-note"><strong>Note:</strong> Use a three-letter ISO code.</span></span></label>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-4@m"><span>Expected date</span><input name="expected_date" type="date" value="<?= $e($values['expectedDate']) ?>"><span class="kontor-field-guidance"><span class="kontor-field-description">The planned arrival date used by purchasing and receiving.</span><span class="kontor-field-note"><strong>Note:</strong> Leave blank only when the supplier has not confirmed a date.</span></span></label>
            </div>
          </fieldset>

          <div class="uk-alert-primary uk-margin-medium-top" uk-alert><p><i class="fa fa-info-circle uk-margin-small-right"></i>Creating this record saves an editable <strong>Draft</strong>. It does not send or issue the purchase order.</p></div>
          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"<?= $readyToCreate ? '' : ' disabled' ?>>Create draft <i class="fa fa-angle-right"></i></button></div></div>
        </form>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <aside class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Readiness</p><h3 class="uk-card-title uk-margin-small-top">Required records</h3><p class="uk-text-muted">The draft connects Purchasing, Catalog and Inventory in one workflow.</p><ul class="uk-list uk-list-divider uk-margin-medium-top"><li><i class="fa fa-<?= $suppliers !== [] ? 'check-circle uk-text-success' : 'exclamation-circle uk-text-warning' ?> uk-margin-small-right"></i><strong>Active supplier</strong><div class="uk-text-meta uk-margin-small-top"><?= $suppliers !== [] ? $e((string) count($suppliers)) . ' available' : 'Required before ordering' ?></div></li><li><i class="fa fa-<?= $warehouses !== [] ? 'check-circle uk-text-success' : 'exclamation-circle uk-text-warning' ?> uk-margin-small-right"></i><strong>Receiving warehouse</strong><div class="uk-text-meta uk-margin-small-top"><?= $warehouses !== [] ? $e((string) count($warehouses)) . ' available' : 'Required for goods receipt' ?></div></li><li><i class="fa fa-<?= $items !== [] ? 'check-circle uk-text-success' : 'exclamation-circle uk-text-warning' ?> uk-margin-small-right"></i><strong>Tracked catalog item</strong><div class="uk-text-meta uk-margin-small-top"><?= $items !== [] ? $e((string) count($items)) . ' available' : 'Required for inventory movement' ?></div></li></ul></aside>
        <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">What happens next</p><h3 class="uk-card-title uk-margin-small-top">Draft, review, receive</h3><ol class="uk-list uk-list-divider"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Review the draft</strong><br><span class="uk-text-meta">Confirm the supplier, destination, item, price and date.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Issue the order</strong><br><span class="uk-text-meta">Issue only when the commitment is approved.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Record delivery</strong><br><span class="uk-text-meta">Receive actual quantities when goods arrive.</span></span></div></li></ol></aside>
      </div>
    </div>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($order->status) ?>"><?= $e($statusLabel($order->status)) ?></span></div><div><span class="uk-text-meta"><?= $e($supplierLabel) ?></span></div></div><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Supplier commitment</h3><p class="uk-text-muted uk-margin-small-top">Review the order state and choose only the next valid operational action.</p></div><div class="pw-module-actions kontor-pagehead__actions"><?php if ($canIssue): ?><form method="post" action="<?= $e($adminUrl) ?>purchase-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($orderUid) ?>"><input type="hidden" name="action" value="issue"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-paper-plane"></i> Issue order</button></form><?php endif; ?><?php if ($canReceive): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>purchasing-receipt/?order=<?= $e(rawurlencode($orderUid)) ?>"><i class="fa fa-truck"></i> Record receipt</a><?php endif; ?><?php if ($canCancel): ?><form method="post" action="<?= $e($adminUrl) ?>purchase-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($orderUid) ?>"><input type="hidden" name="action" value="cancel"><button class="uk-button uk-button-default" type="submit">Cancel order</button></form><?php endif; ?></div></div>
      <hr><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-4@s" uk-grid><div><span class="uk-text-meta">Supplier</span><div class="uk-margin-small-top"><strong><?= $e($supplierLabel) ?></strong></div></div><div><span class="uk-text-meta">Receiving warehouse</span><div class="uk-margin-small-top"><strong><?= $e($warehouseLabel) ?></strong></div></div><div><span class="uk-text-meta">Expected arrival</span><div class="uk-margin-small-top"><strong><?= $order->expectedDate !== null ? $e($order->expectedDate->format('M j, Y')) : 'Not scheduled' ?></strong></div></div><div><span class="uk-text-meta">Order total</span><div class="uk-margin-small-top"><strong><?= $e($money($order->total)) ?></strong></div></div></div>
    </section>

    <div class="uk-grid-medium" uk-grid><div class="uk-width-1-1 uk-width-2-3@l"><section class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Order content</p><h3 class="uk-card-title uk-margin-small-top">Items</h3></div><div><span class="uk-label"><?= $e((string) count($lines)) ?> line<?= count($lines) === 1 ? '' : 's' ?></span></div></div><?php if ($lines !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($lines as $line): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($line->title) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e(number_format($line->quantity, 3, '.', ',')) ?> <?= $e($line->unitCode) ?> × <?= $e($money($line->unitPrice)) ?></div></div><div class="uk-text-right@m"><strong><?= $e($money($line->total())) ?></strong><div class="uk-text-meta uk-margin-small-top">Line total</div></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">This draft has no order lines.</p></div><?php endif; ?></section></div><div class="uk-width-1-1 uk-width-1-3@l"><aside class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next decision</p><h3 class="uk-card-title uk-margin-small-top"><?= $order->isDraft() ? 'Review before issue' : ($order->isReceivable() ? 'Receive what arrived' : 'Order history') ?></h3><p class="uk-text-muted"><?= $order->isDraft() ? 'Issuing turns the draft into an active supplier commitment.' : ($order->isReceivable() ? 'Record actual quantities so inventory and open commitments remain accurate.' : 'This order no longer needs an operational action.') ?></p><ul class="uk-list uk-list-divider uk-margin-medium-top"><li><strong><?= $e((string) count($receipts)) ?> receipt<?= count($receipts) === 1 ? '' : 's' ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $receipts === [] ? 'No delivery recorded yet' : 'Latest: ' . $e(end($receipts)->receivedAt->format('M j, Y')) ?></div></li><li><strong><?= $e($statusLabel($order->status)) ?></strong><div class="uk-text-meta uk-margin-small-top">Current workflow state</div></li></ul></aside></div></div>
  <?php endif; ?>
</div>
