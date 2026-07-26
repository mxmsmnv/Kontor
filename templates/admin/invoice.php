<?php

/** @var \Kontor\Invoices\Domain\Invoice $invoice */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var string $customerLabel */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var bool $paymentsReady */
/** @var \Kontor\Payments\Domain\PaymentAllocation[] $allocations */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-arrow-left"></i> Back to invoices</a>
    <p class="kontor-eyebrow"><?= $e($invoice->kind === 'credit_note' ? 'Credit note' : 'Invoice') ?></p>
    <h2><?= $e($invoice->number ?? 'Draft invoice') ?></h2>
    <p>Order-backed billing document with immutable commercial lines.</p>
  </header>

  <section class="kontor-card kontor-documenthead">
    <div>
      <span class="kontor-pill<?= $invoice->isDraft() ? ' kontor-pill--inactive' : '' ?>"><?= $e($invoice->status) ?></span>
      <strong><?= $e($customerLabel) ?></strong>
      <span>Due <?= $e($invoice->dueDate?->format('Y-m-d') ?? 'not set') ?> · paid <?= $e($money($invoice->paid)) ?></span>
    </div>
    <div class="kontor-invoicetotals">
      <span>Total <?= $e($money($invoice->total)) ?></span>
      <strong>Due <?= $e($money($invoice->due)) ?></strong>
    </div>
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

  <div class="kontor-documentactions">
    <?php if ($paymentsReady && in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true) && $invoice->due->amountMinor() > 0): ?>
      <form method="post" action="<?= $e($adminUrl) ?>payment-from-invoice/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="invoice_uid" value="<?= $e($invoice->uid->toString()) ?>">
        <label>Amount <input name="amount" inputmode="decimal" value="<?= $e(number_format($invoice->due->amountMinor() / 100, 2, '.', '')) ?>" required></label>
        <label>Method
          <select name="method"><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="cash">Cash</option><option value="other">Other</option></select>
        </label>
        <label>Reference <input name="transaction_reference" value=""></label>
        <button class="kontor-button" type="submit">Record payment</button>
      </form>
    <?php endif; ?>
    <?php if ($invoice->status === 'draft'): ?>
      <form method="post" action="<?= $e($adminUrl) ?>invoice-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>">
        <button class="kontor-button" name="action" value="issue" type="submit">Issue invoice</button>
        <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
      </form>
    <?php elseif ($invoice->status === 'issued'): ?>
      <form method="post" action="<?= $e($adminUrl) ?>invoice-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>">
        <button class="kontor-button" name="action" value="send" type="submit">Mark sent</button>
        <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        <?php if ($invoice->kind === 'invoice'): ?><button class="kontor-button kontor-button--ghost" name="action" value="credit" type="submit">Issue credit note</button><?php endif; ?>
      </form>
    <?php elseif ($invoice->isCreditable()): ?>
      <form method="post" action="<?= $e($adminUrl) ?>invoice-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>">
        <button class="kontor-button kontor-button--ghost" name="action" value="credit" type="submit">Issue credit note</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($allocations !== []): ?>
    <section class="kontor-card kontor-tablewrap">
      <h3>Payments</h3>
      <table class="kontor-table">
        <thead><tr><th>Payment</th><th>Amount</th><th>Allocated</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($allocations as $allocation): ?>
            <tr>
              <td><a href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($allocation->paymentUid)) ?>"><?= $e($allocation->paymentUid) ?></a></td>
              <td><?= $e($money($allocation->amount)) ?></td>
              <td><?= $e($allocation->allocatedAt->format('Y-m-d H:i')) ?></td>
              <td><span class="kontor-pill<?= $allocation->isReversed() ? ' kontor-pill--inactive' : '' ?>"><?= $e($allocation->isReversed() ? 'reversed' : 'active') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</div>
