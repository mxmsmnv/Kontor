<?php

/** @var \Kontor\Sales\Domain\Quotation[] $quotations */
/** @var \Kontor\Sales\Domain\Order[] $orders */
/** @var array<string, string> $customerLabels */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Quote to cash</p>
      <h2>Sales</h2>
      <p>Prepare quotations and turn accepted offers into sales orders.</p>
    </div>
    <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>sales-quotation/">
      <i class="fa fa-plus"></i> New quotation
    </a>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Documents</p><h3>Quotations</h3></div>
      <span class="kontor-secondary"><?= $e(count($quotations)) ?> shown</span>
    </header>
    <?php if ($quotations !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Quotation</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($quotations as $quotation): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>sales-quotation/?id=<?= $e(rawurlencode($quotation->uid->toString())) ?>"><?= $e($quotation->number ?? 'Draft') ?></a></strong></td>
              <td><?= $e($customerLabels[$quotation->customerType . ':' . $quotation->customerUid] ?? $quotation->customerUid) ?></td>
              <td><?= $e($money($quotation->total)) ?></td>
              <td><span class="uk-label kontor-pill<?= $quotation->status === 'draft' ? ' kontor-pill--inactive' : '' ?>"><?= $e($quotation->status) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-file-text-o"></i><h3>No quotations yet</h3><p>Create the first sales offer.</p></div>
    <?php endif; ?>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Fulfillment handoff</p><h3>Sales orders</h3></div>
      <span class="kontor-secondary"><?= $e(count($orders)) ?> shown</span>
    </header>
    <?php if ($orders !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($order->uid->toString())) ?>"><?= $e($order->number ?? 'Pending order') ?></a></strong></td>
              <td><?= $e($customerLabels[$order->customerType . ':' . $order->customerUid] ?? $order->customerUid) ?></td>
              <td><?= $e($money($order->total)) ?></td>
              <td><span class="uk-label kontor-pill<?= $order->orderStatus === 'pending' ? ' kontor-pill--inactive' : '' ?>"><?= $e($order->orderStatus) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-shopping-cart"></i><h3>No sales orders yet</h3><p>Accepted quotations can be converted here.</p></div>
    <?php endif; ?>
  </section>
</div>
