<?php

/** @var \Kontor\Sales\Domain\Order $order */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var string $customerLabel */
/** @var \Kontor\Invoices\Domain\Invoice|null $existingInvoice */
/** @var bool $invoicesReady */
/** @var \Kontor\Inventory\Domain\Warehouse[] $inventoryWarehouses */
/** @var int $trackedLineCount */
/** @var string|null $reservationWarehouseUid */
/** @var bool $canReserveInventory */
/** @var bool $canShipInventory */
/** @var bool $canReleaseInventory */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>sales/"><i class="fa fa-arrow-left"></i> Back to Sales</a>
    <p class="kontor-eyebrow">Sales · Order</p>
    <h2><?= $e($order->number ?? 'Sales order') ?></h2>
    <p>Confirmation and completion handoff into fulfillment.</p>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-documenthead">
    <div>
      <span class="uk-label kontor-pill<?= $order->isPending() ? ' kontor-pill--inactive' : '' ?>"><?= $e($order->orderStatus) ?></span>
      <strong><?= $e($customerLabel) ?></strong>
      <span><?= $e($order->paymentStatus) ?> · <?= $e($order->fulfillmentStatus) ?></span>
    </div>
    <strong><?= $e($money($order->total)) ?></strong>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
      <thead><tr><th>Line</th><th>Quantity</th><th>Unit price</th><th>Tax</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($lines as $line): ?>
          <tr><td><strong><?= $e($line->title) ?></strong></td><td><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></td><td><?= $e($money($line->unitPrice)) ?></td><td><?= $e($line->taxRate) ?>%</td><td><?= $e($money($line->total())) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <?php if ($order->isOpen()): ?>
    <div class="kontor-documentactions">
      <form method="post" action="<?= $e($adminUrl) ?>sales-order-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($order->uid->toString()) ?>">
        <?php if ($order->isPending() && $trackedLineCount > 0): ?>
          <label class="kontor-nativefield">
            <span>Fulfillment warehouse</span>
            <select name="warehouse_uid" required>
              <option value="">Select warehouse</option>
              <?php foreach ($inventoryWarehouses as $warehouse): ?>
                <option value="<?= $e($warehouse->uid->toString()) ?>"><?= $e($warehouse->code . ' · ' . $warehouse->name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if ($canReserveInventory && $inventoryWarehouses !== []): ?>
            <button class="uk-button uk-button-primary kontor-button" name="action" value="confirm" type="submit">Reserve stock + confirm</button>
          <?php endif; ?>
        <?php elseif ($order->isPending()): ?>
          <button class="uk-button uk-button-primary kontor-button" name="action" value="confirm" type="submit">Confirm order</button>
        <?php endif; ?>
        <?php if ($order->isConfirmed() && $reservationWarehouseUid !== null && $canShipInventory): ?>
          <button class="uk-button uk-button-primary kontor-button" name="action" value="complete" type="submit">Ship stock + complete</button>
        <?php elseif ($order->isConfirmed() && $reservationWarehouseUid === null): ?>
          <button class="uk-button uk-button-primary kontor-button" name="action" value="complete" type="submit">Complete order</button>
        <?php endif; ?>
        <?php if ($reservationWarehouseUid === null || $canReleaseInventory): ?>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        <?php endif; ?>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($invoicesReady && in_array($order->orderStatus, ['confirmed', 'completed'], true)): ?>
    <div class="kontor-documentactions">
      <?php if ($existingInvoice !== null): ?>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>invoice/?id=<?= $e(rawurlencode($existingInvoice->uid->toString())) ?>">
          Open <?= $e($existingInvoice->number ?? 'invoice draft') ?>
        </a>
      <?php else: ?>
        <form method="post" action="<?= $e($adminUrl) ?>invoice-from-order/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="order_uid" value="<?= $e($order->uid->toString()) ?>">
          <button class="uk-button uk-button-primary kontor-button" type="submit"><i class="fa fa-file-text"></i> Create invoice</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
