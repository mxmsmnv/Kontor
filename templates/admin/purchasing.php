<?php

/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var array<string, string> $supplierLabels */
/** @var \Kontor\Purchasing\Domain\PurchaseOrder[] $orders */
/** @var bool $canCreateSupplier */
/** @var bool $canCreateOrder */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Operations · Buy side</p>
      <h2>Purchasing</h2>
      <p>Suppliers, purchase orders, goods receipts, and inventory integration.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <?php if ($canCreateSupplier): ?>
        <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>purchasing-supplier/">
          <i class="fa fa-address-card"></i> New supplier
        </a>
      <?php endif; ?>
      <?php if ($canCreateOrder): ?>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>purchase-order/">
          <i class="fa fa-plus"></i> New purchase order
        </a>
      <?php endif; ?>
    </div>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Documents</p><h3>Purchase orders</h3></div></header>
    <?php if ($orders !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Order</th><th>Supplier</th><th>Expected</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>purchase-order/?id=<?= $e(rawurlencode($order->uid->toString())) ?>"><?= $e($order->number ?? 'Draft') ?></a></strong></td>
              <td><?= $e($supplierLabels[$order->supplierUid] ?? $order->supplierUid) ?></td>
              <td><?= $e($order->expectedDate?->format('Y-m-d') ?? '—') ?></td>
              <td><?= $e($money($order->total)) ?></td>
              <td><span class="uk-label kontor-pill<?= in_array($order->status, ['received', 'issued'], true) ? '' : ' kontor-pill--inactive' ?>"><?= $e(str_replace('_', ' ', $order->status)) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-truck"></i><h3>No purchase orders</h3><p>Create a supplier, then draft the first order.</p></div>
    <?php endif; ?>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Vendor directory</p><h3>Suppliers</h3></div></header>
    <?php if ($suppliers !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Code</th><th>Supplier</th><th>Contact</th><th>Terms</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($suppliers as $supplier): ?>
            <tr>
              <td><strong><?= $e($supplier->code) ?></strong></td>
              <td><?= $e($supplier->legalName) ?></td>
              <td><?= $e($supplier->email ?? $supplier->phone ?? '—') ?></td>
              <td><?= $e($supplier->paymentTermsDays) ?> days · <?= $e($supplier->currencyCode) ?></td>
              <td><span class="uk-label kontor-pill<?= $supplier->isActive() ? '' : ' kontor-pill--inactive' ?>"><?= $e($supplier->status) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-address-card"></i><h3>No suppliers</h3><p>Add the first vendor to start purchasing.</p></div>
    <?php endif; ?>
  </section>
</div>
