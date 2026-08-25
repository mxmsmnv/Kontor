<?php

/** @var \Kontor\Sales\Domain\Order $order */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var string $customerLabel */
/** @var \Kontor\Sales\Domain\Quotation|null $sourceQuotation */
/** @var \Kontor\Invoices\Domain\Invoice|null $existingInvoice */
/** @var bool $invoicesReady */
/** @var \Kontor\Inventory\Domain\Warehouse[] $inventoryWarehouses */
/** @var int $trackedLineCount */
/** @var string|null $reservationWarehouseUid */
/** @var string|null $reservationWarehouseLabel */
/** @var bool $inventoryReady */
/** @var bool $showInventory */
/** @var bool $canReserveInventory */
/** @var bool $canShipInventory */
/** @var bool $canReleaseInventory */
/** @var bool $canConfirm */
/** @var bool $canComplete */
/** @var bool $canCancel */
/** @var bool $canCreateInvoice */
/** @var bool $canViewInvoice */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$humanize = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'completed', 'paid', 'fulfilled' => ' uk-label-success',
    'cancelled' => ' uk-label-danger',
    'confirmed', 'partially_paid', 'partially_fulfilled' => ' uk-label-warning',
    default => '',
};
$nextStep = match ($order->orderStatus) {
    'pending' => ['Confirm the customer order', $trackedLineCount > 0
        ? 'Choose the fulfillment warehouse and reserve stock before committing the order.'
        : 'Confirm the order so fulfillment and invoicing can begin.'],
    'confirmed' => ['Complete fulfillment', $reservationWarehouseUid !== null
        ? 'Ship the reserved stock and complete the customer order.'
        : 'Mark the work complete when the customer commitment has been fulfilled.'],
    'completed' => ['Order completed', 'Fulfillment is complete. Continue in Billing to manage the invoice.'],
    default => ['Order closed', 'This order is closed and no further fulfillment action is required.'],
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Sales · Order</p>
      <h2><?= $e($order->number ?? 'Sales order') ?></h2>
      <p>Coordinate customer commitment, fulfillment and billing from one workspace.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>sales/"><i class="fa fa-arrow-left"></i> All sales</a></div>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@m">
        <div class="uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($order->orderStatus) ?>"><?= $e($humanize($order->orderStatus)) ?></span></div></div>
        <h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($customerLabel) ?></h3>
        <p class="uk-text-muted uk-margin-remove"><?= $sourceQuotation !== null ? 'Created from accepted quotation ' . $e($sourceQuotation->number ?? 'draft quotation') . '.' : 'Direct customer sales order.' ?></p>
      </div>
      <div class="uk-width-auto@m uk-text-right@m"><div class="uk-text-meta">Order total</div><div class="uk-text-large"><strong><?= $e($money($order->total)) ?></strong></div></div>
    </div>
    <hr>
    <div class="uk-grid-small uk-grid-divider uk-child-width-1-2 uk-child-width-1-4@l" uk-grid>
      <div><div class="uk-text-meta">Order date</div><strong><?= $e($order->issueDate?->format('M j, Y') ?? 'Not set') ?></strong></div>
      <div><div class="uk-text-meta">Expected delivery</div><strong><?= $e($order->expectedDeliveryDate?->format('M j, Y') ?? 'Not scheduled') ?></strong></div>
      <div><div class="uk-text-meta">Payment</div><strong><?= $e($humanize($order->paymentStatus)) ?></strong></div>
      <div><div class="uk-text-meta">Fulfillment</div><strong><?= $e($humanize($order->fulfillmentStatus)) ?></strong></div>
    </div>
  </section>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Commercial terms</p>
        <h3 class="uk-card-title uk-margin-small-top">Ordered items</h3>
        <?php if ($lines !== []): ?>
          <div class="uk-overflow-auto uk-visible@m"><table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove-bottom"><thead><tr><th>Item or service</th><th>Quantity</th><th>Unit price</th><th>Tax</th><th class="uk-text-right">Line total</th></tr></thead><tbody><?php foreach ($lines as $line): ?><tr><td><strong><?= $e($line->title) ?></strong></td><td><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></td><td><?= $e($money($line->unitPrice)) ?></td><td><?= $e($line->taxRate) ?>%</td><td class="uk-text-right"><strong><?= $e($money($line->total())) ?></strong></td></tr><?php endforeach; ?></tbody></table></div>
          <ul class="uk-list uk-list-divider uk-hidden@m uk-margin-remove-bottom"><?php foreach ($lines as $line): ?><li><strong><?= $e($line->title) ?></strong><div class="uk-grid-small uk-child-width-1-2 uk-margin-small-top" uk-grid><div><span class="uk-text-meta">Quantity</span><br><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></div><div><span class="uk-text-meta">Unit price</span><br><?= $e($money($line->unitPrice)) ?></div><div><span class="uk-text-meta">Tax</span><br><?= $e($line->taxRate) ?>%</div><div><span class="uk-text-meta">Line total</span><br><strong><?= $e($money($line->total())) ?></strong></div></div></li><?php endforeach; ?></ul>
        <?php else: ?><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No ordered items are available.</p></div><?php endif; ?>
        <hr>
        <div class="uk-grid-small uk-flex-right" uk-grid><div class="uk-width-1-1 uk-width-1-2@s"><dl class="uk-description-list uk-margin-remove">
          <div class="uk-flex uk-flex-between"><dt>Subtotal</dt><dd><?= $e($money($order->subtotal)) ?></dd></div>
          <?php if ($order->discount->amountMinor() > 0): ?><div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Discount</dt><dd>−<?= $e($money($order->discount)) ?></dd></div><?php endif; ?>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Tax</dt><dd><?= $e($money($order->tax)) ?></dd></div>
          <?php if ($order->shipping->amountMinor() > 0): ?><div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Shipping</dt><dd><?= $e($money($order->shipping)) ?></dd></div><?php endif; ?>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt><strong>Total</strong></dt><dd><strong><?= $e($money($order->total)) ?></strong></dd></div>
        </dl></div></div>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next step</p><h3 class="uk-card-title uk-margin-small-top"><?= $e($nextStep[0]) ?></h3><p class="uk-text-muted"><?= $e($nextStep[1]) ?></p>
        <?php if ($order->isPending() && $trackedLineCount > 0): ?>
          <?php if (!$inventoryReady): ?><div class="uk-alert-warning" uk-alert><p><strong>Inventory is required for tracked products.</strong> Enable Inventory before confirming this order.</p></div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
          <?php elseif ($inventoryWarehouses === []): ?><div class="uk-alert-warning" uk-alert><p><strong>An active warehouse is required.</strong> Add a fulfillment warehouse before reserving stock.</p></div><?php if ($showInventory): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>inventory/">Open inventory</a><?php endif; ?>
          <?php elseif ($canConfirm && $canReserveInventory): ?><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>sales-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><label class="uk-form-label" for="order-warehouse">Fulfillment warehouse</label><select class="uk-select uk-margin-small-top" id="order-warehouse" name="warehouse_uid" required><option value="">Select warehouse</option><?php foreach ($inventoryWarehouses as $warehouse): ?><option value="<?= $e($warehouse->uid->toString()) ?>"><?= $e($warehouse->code . ' · ' . $warehouse->name) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Stock for tracked products will be reserved here.</div><button class="uk-button uk-button-primary uk-width-1-1 uk-margin" name="action" value="confirm" type="submit" data-kontor-confirm="Reserve stock and confirm this order?"><i class="fa fa-check"></i> Reserve and confirm</button></form>
          <?php else: ?><p class="uk-text-meta">Order confirmation and inventory reservation access are required.</p><?php endif; ?>
        <?php elseif ($order->isPending() && $canConfirm): ?><form method="post" action="<?= $e($adminUrl) ?>sales-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="confirm" type="submit" data-kontor-confirm="Confirm this customer order?"><i class="fa fa-check"></i> Confirm order</button></form>
        <?php elseif ($order->isConfirmed() && $reservationWarehouseUid !== null && $canComplete && $canShipInventory): ?><form method="post" action="<?= $e($adminUrl) ?>sales-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="complete" type="submit" data-kontor-confirm="Ship reserved stock and complete this order?"><i class="fa fa-truck"></i> Ship and complete</button></form>
        <?php elseif ($order->isConfirmed() && $reservationWarehouseUid === null && $canComplete): ?><form method="post" action="<?= $e($adminUrl) ?>sales-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="complete" type="submit" data-kontor-confirm="Mark this order completed?"><i class="fa fa-check-circle"></i> Complete order</button></form>
        <?php elseif ($order->isConfirmed() && $reservationWarehouseUid !== null): ?><p class="uk-text-meta">Inventory shipping and order completion access are required.</p><?php endif; ?>
        <?php if ($order->isOpen() && $canCancel && ($reservationWarehouseUid === null || $canReleaseInventory)): ?><hr><form method="post" action="<?= $e($adminUrl) ?>sales-order-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>"><button class="uk-button uk-button-text uk-text-danger" name="action" value="cancel" type="submit" data-kontor-confirm="Cancel this order<?= $reservationWarehouseUid !== null ? ' and release its stock reservation' : '' ?>?">Cancel order</button></form><?php endif; ?>
      </section>

      <?php if (in_array($order->orderStatus, ['confirmed', 'completed'], true)): ?>
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Billing</p><h3 class="uk-card-title uk-margin-small-top">Customer invoice</h3>
          <?php if ($existingInvoice !== null && $canViewInvoice): ?><p class="uk-text-muted">An invoice has already been created from this order.</p><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>invoice/?id=<?= $e(rawurlencode($existingInvoice->uid->toString())) ?>"><i class="fa fa-file-text"></i> Open <?= $e($existingInvoice->number ?? 'invoice draft') ?></a>
          <?php elseif ($existingInvoice !== null): ?><p class="uk-text-muted uk-margin-remove-bottom">An invoice exists for this order. Invoice access is required to open it.</p>
          <?php elseif ($canCreateInvoice): ?><p class="uk-text-muted">Create a draft invoice with the customer and commercial lines already filled in.</p><form method="post" action="<?= $e($adminUrl) ?>invoice-from-order/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="order_uid" value="<?= $e($order->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" type="submit"><i class="fa fa-file-text"></i> Create invoice draft</button></form>
          <?php elseif (!$invoicesReady): ?><p class="uk-text-muted">Enable Invoices to continue from the sales order into billing.</p><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
          <?php else: ?><p class="uk-text-muted uk-margin-remove-bottom">Invoice creation access is required to start billing.</p><?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($sourceQuotation !== null || ($existingInvoice !== null && $canViewInvoice) || $reservationWarehouseLabel !== null): ?>
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected work</p><h3 class="uk-card-title uk-margin-small-top">Related records</h3><ul class="uk-list uk-list-divider uk-margin-remove-bottom">
          <?php if ($sourceQuotation !== null): ?><li><div class="uk-text-meta">Source quotation</div><strong><?= $e($sourceQuotation->number ?? 'Draft quotation') ?></strong><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>sales-quotation/?id=<?= $e(rawurlencode($sourceQuotation->uid->toString())) ?>">Open quotation</a></div></li><?php endif; ?>
          <?php if ($existingInvoice !== null && $canViewInvoice): ?><li><div class="uk-text-meta">Invoice</div><strong><?= $e($existingInvoice->number ?? 'Invoice draft') ?></strong><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>invoice/?id=<?= $e(rawurlencode($existingInvoice->uid->toString())) ?>">Open invoice</a></div></li><?php endif; ?>
          <?php if ($reservationWarehouseLabel !== null): ?><li><div class="uk-text-meta">Fulfillment warehouse</div><strong><?= $e($reservationWarehouseLabel) ?></strong><?php if ($showInventory): ?><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>inventory/">Open inventory</a></div><?php endif; ?></li><?php endif; ?>
        </ul></section>
      <?php endif; ?>
    </div>
  </div>
</div>
