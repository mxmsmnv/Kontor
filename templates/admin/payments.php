<?php

/** @var \Kontor\Payments\Domain\Payment[] $payments */
/** @var array<string, string> $payerLabels */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Cash received</p>
      <h2>Payments</h2>
      <p>Track confirmed receipts and their invoice allocations.</p>
    </div>
    <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-file-text"></i> Invoices</a>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <?php if ($payments !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Payment</th><th>Payer</th><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($payment->uid->toString())) ?>"><?= $e($payment->number ?? 'Draft payment') ?></a></strong><span class="kontor-secondary"><?= $e($payment->transactionReference ?? 'No reference') ?></span></td>
              <td><?= $e($payerLabels[$payment->payerType . ':' . $payment->payerUid] ?? $payment->payerUid) ?></td>
              <td><?= $e($payment->paymentDate?->format('Y-m-d') ?? 'Not set') ?></td>
              <td><?= $e(str_replace('_', ' ', $payment->method)) ?></td>
              <td><?= $e($money($payment->amount)) ?></td>
              <td><span class="uk-label kontor-pill<?= $payment->isReversed() ? ' kontor-pill--inactive' : '' ?>"><?= $e($payment->status) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-money"></i><h3>No payments yet</h3><p>Open a sent invoice to record and allocate the first payment.</p></div>
    <?php endif; ?>
  </section>
</div>
