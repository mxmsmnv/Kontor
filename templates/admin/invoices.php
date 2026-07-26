<?php

/** @var \Kontor\Invoices\Domain\Invoice[] $invoices */
/** @var array<string, string> $customerLabels */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Accounts receivable</p>
      <h2>Invoices</h2>
      <p>Issue order-backed invoices, track amounts due, and create credit notes.</p>
    </div>
    <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>sales/">
      <i class="fa fa-shopping-cart"></i> Sales orders
    </a>
  </header>

  <section class="kontor-card kontor-tablewrap">
    <?php if ($invoices !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>invoice/?id=<?= $e(rawurlencode($invoice->uid->toString())) ?>"><?= $e($invoice->number ?? 'Draft invoice') ?></a></strong><span class="kontor-secondary"><?= $e($invoice->kind) ?></span></td>
              <td><?= $e($customerLabels[$invoice->customerType . ':' . $invoice->customerUid] ?? $invoice->customerUid) ?></td>
              <td><?= $e($money($invoice->total)) ?></td>
              <td><?= $e($money($invoice->due)) ?></td>
              <td><span class="kontor-pill<?= $invoice->isDraft() ? ' kontor-pill--inactive' : '' ?>"><?= $e($invoice->status) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty"><i class="fa fa-file-text"></i><h3>No invoices yet</h3><p>Open a confirmed or completed Sales order to create the first invoice.</p></div>
    <?php endif; ?>
  </section>
</div>
