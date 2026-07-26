<?php

/** @var \Kontor\Payments\Domain\Payment $payment */
/** @var \Kontor\Payments\Domain\PaymentAllocation[] $allocations */
/** @var array<string, string> $invoiceLabels */
/** @var string $payerLabel */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>payments/"><i class="fa fa-arrow-left"></i> Back to payments</a>
    <p class="kontor-eyebrow">Payment</p>
    <h2><?= $e($payment->number ?? 'Draft payment') ?></h2>
    <p>Confirmed cash receipt and its document allocations.</p>
  </header>

  <section class="kontor-card kontor-documenthead">
    <div>
      <span class="kontor-pill<?= $payment->isReversed() ? ' kontor-pill--inactive' : '' ?>"><?= $e($payment->status) ?></span>
      <strong><?= $e($payerLabel) ?></strong>
      <span><?= $e($payment->paymentDate?->format('Y-m-d') ?? 'Date not set') ?> · <?= $e(str_replace('_', ' ', $payment->method)) ?><?= $payment->transactionReference ? ' · ' . $e($payment->transactionReference) : '' ?></span>
    </div>
    <div><strong><?= $e($money($payment->amount)) ?></strong></div>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <table class="kontor-table">
      <thead><tr><th>Document</th><th>Amount</th><th>Allocated</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($allocations as $allocation): ?>
          <tr>
            <td><a href="<?= $e($adminUrl) ?>invoice/?id=<?= $e(rawurlencode($allocation->documentUid)) ?>"><?= $e($invoiceLabels[$allocation->documentUid] ?? $allocation->documentUid) ?></a></td>
            <td><?= $e($money($allocation->amount)) ?></td>
            <td><?= $e($allocation->allocatedAt->format('Y-m-d H:i')) ?></td>
            <td><span class="kontor-pill<?= $allocation->isReversed() ? ' kontor-pill--inactive' : '' ?>"><?= $e($allocation->isReversed() ? 'reversed' : 'active') ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <?php if ($payment->isConfirmed()): ?>
    <div class="kontor-documentactions">
      <form method="post" action="<?= $e($adminUrl) ?>payment-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="id" value="<?= $e($payment->uid->toString()) ?>">
        <button class="kontor-button kontor-button--ghost" name="action" value="reverse" type="submit">Reverse payment</button>
      </form>
    </div>
  <?php endif; ?>
</div>
