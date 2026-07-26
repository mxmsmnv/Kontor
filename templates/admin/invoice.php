<?php

/** @var \Kontor\Invoices\Domain\Invoice $invoice */
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
</div>
