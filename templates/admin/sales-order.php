<?php

/** @var \Kontor\Sales\Domain\Order $order */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var string $customerLabel */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>sales/"><i class="fa fa-arrow-left"></i> Back to Sales</a>
    <p class="kontor-eyebrow">Sales · Order</p>
    <h2><?= $e($order->number ?? 'Sales order') ?></h2>
    <p>Confirmation and completion handoff into fulfillment.</p>
  </header>

  <section class="kontor-card kontor-documenthead">
    <div>
      <span class="kontor-pill<?= $order->isPending() ? ' kontor-pill--inactive' : '' ?>"><?= $e($order->orderStatus) ?></span>
      <strong><?= $e($customerLabel) ?></strong>
      <span><?= $e($order->paymentStatus) ?> · <?= $e($order->fulfillmentStatus) ?></span>
    </div>
    <strong><?= $e($money($order->total)) ?></strong>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <table class="kontor-table">
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
        <?php if ($order->isPending()): ?><button class="kontor-button" name="action" value="confirm" type="submit">Confirm order</button><?php endif; ?>
        <?php if ($order->isConfirmed()): ?><button class="kontor-button" name="action" value="complete" type="submit">Complete order</button><?php endif; ?>
        <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
      </form>
    </div>
  <?php endif; ?>
</div>
