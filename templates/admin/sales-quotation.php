<?php

/** @var \Kontor\Sales\Domain\Quotation|null $quotation */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Sales\Domain\Order|null $existingOrder */
/** @var \Kontor\Documents\Domain\DocumentTemplate|null $quotationTemplate */
/** @var array<string, mixed>|null $issuedFile */
/** @var array<string, string> $values */
/** @var array<string, string> $customers */
/** @var string $error */
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
    <p class="kontor-eyebrow">Sales · Quotation</p>
    <h2><?= $e($quotation?->number ?? ($quotation !== null ? 'Draft quotation' : 'New quotation')) ?></h2>
    <p>Customer, first commercial line, issue, acceptance, and order conversion.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <?php if ($quotation === null): ?>
    <?php if ($customers === []): ?>
      <section class="kontor-card kontor-empty"><h3>A customer is required</h3><p>Create a contact or company before preparing a quotation.</p></section>
    <?php else: ?>
      <form class="kontor-card kontor-nativeform" method="post" action="./">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <label class="kontor-nativefield kontor-nativefield--wide">
          <span>Customer *</span>
          <select name="customer" required>
            <option value="">Select customer</option>
            <?php foreach ($customers as $value => $label): ?>
              <option value="<?= $e($value) ?>"<?= $values['customer'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="kontor-nativefield"><span>Currency</span><select name="currency">
          <?php foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'] as $currency): ?>
            <option value="<?= $e($currency) ?>"<?= $values['currency'] === $currency ? ' selected' : '' ?>><?= $e($currency) ?></option>
          <?php endforeach; ?>
        </select></label>
        <label class="kontor-nativefield"><span>Language</span><select name="language">
          <?php foreach (['en', 'de', 'fr', 'es'] as $language): ?>
            <option value="<?= $e($language) ?>"<?= $values['language'] === $language ? ' selected' : '' ?>><?= $e(strtoupper($language)) ?></option>
          <?php endforeach; ?>
        </select></label>
        <label class="kontor-nativefield"><span>Valid until</span><input type="date" name="valid_until" value="<?= $e($values['validUntil']) ?>"></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>First line title *</span><input name="line_title" value="<?= $e($values['lineTitle']) ?>" required></label>
        <label class="kontor-nativefield"><span>Quantity</span><input name="quantity" inputmode="decimal" value="<?= $e($values['quantity']) ?>"></label>
        <label class="kontor-nativefield"><span>Unit</span><input name="unit_code" value="<?= $e($values['unitCode']) ?>"></label>
        <label class="kontor-nativefield"><span>Unit price</span><input name="unit_price" inputmode="decimal" value="<?= $e($values['unitPrice']) ?>" required></label>
        <label class="kontor-nativefield"><span>Tax rate %</span><input name="tax_rate" inputmode="decimal" value="<?= $e($values['taxRate']) ?>"></label>
        <div class="kontor-nativeform__actions">
          <button class="kontor-button" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Create draft</button>
          <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>sales/">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <section class="kontor-card kontor-documenthead">
      <div>
        <span class="kontor-pill<?= $quotation->isDraft() ? ' kontor-pill--inactive' : '' ?>"><?= $e($quotation->status) ?></span>
        <strong><?= $e($customers[$quotation->customerType . ':' . $quotation->customerUid] ?? $quotation->customerUid) ?></strong>
        <span><?= $e($quotation->documentLanguage) ?> · valid until <?= $e($quotation->validUntil?->format('Y-m-d') ?? 'not set') ?></span>
      </div>
      <strong><?= $e($money($quotation->total)) ?></strong>
    </section>

    <?php if ($quotation->isDraft() && $quotationTemplate === null): ?>
      <section class="kontor-card kontor-warning">
        <div>
          <strong>Issuance needs a document template</strong>
          <p>Publish an active <code>quotation.standard</code> template in <?= $e(strtoupper($quotation->documentLanguage)) ?> (or English fallback) first.</p>
        </div>
        <a class="kontor-button" href="<?= $e($adminUrl) ?>documents/">Open Documents</a>
      </section>
    <?php elseif ($quotationTemplate !== null): ?>
      <section class="kontor-card">
        <p class="kontor-eyebrow"><?= $quotation->isDraft() ? 'Issuance template' : 'Immutable issued output' ?></p>
        <h3><?= $e($quotationTemplate->name) ?> · v<?= $e((string) $quotationTemplate->versionNumber) ?></h3>
        <p><code><?= $e($quotationTemplate->templateKey) ?></code> · <?= $e(strtoupper($quotationTemplate->language)) ?></p>
        <?php if ($issuedFile !== null): ?>
          <a class="kontor-button" href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $issuedFile['uid'])) ?>">Open private PDF</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>

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
      <?php if ($quotation->status === 'draft'): ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <?php if ($quotationTemplate !== null): ?>
            <button class="kontor-button" name="action" value="issue" type="submit">Issue quotation + PDF</button>
          <?php endif; ?>
          <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        </form>
      <?php elseif ($quotation->isOpen()): ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <button class="kontor-button" name="action" value="accept" type="submit">Accept quotation</button>
          <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        </form>
      <?php elseif ($quotation->isAccepted() && $existingOrder === null): ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <button class="kontor-button" name="action" value="convert" type="submit">Create sales order</button>
        </form>
      <?php elseif ($existingOrder !== null): ?>
        <a class="kontor-button" href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($existingOrder->uid->toString())) ?>">Open <?= $e($existingOrder->number ?? 'sales order') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
