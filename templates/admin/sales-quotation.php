<?php

/** @var \Kontor\Sales\Domain\Quotation|null $quotation */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Sales\Domain\Order|null $existingOrder */
/** @var \Kontor\Documents\Domain\DocumentTemplate|null $quotationTemplate */
/** @var array<string, mixed>|null $issuedFile */
/** @var array<string, string> $values */
/** @var array<string, string> $customers */
/** @var bool $contactsReady */
/** @var bool $canCreateContact */
/** @var bool $canCreateCompany */
/** @var array<string, string> $languageOptions */
/** @var \Kontor\CRM\Domain\Deal|null $sourceDeal */
/** @var bool $mailReady */
/** @var \Kontor\Mail\Domain\Mailbox[] $mailboxes */
/** @var string $customerEmail */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Sales · Quotation</p>
      <h2><?= $e($quotation?->number ?? ($quotation !== null ? 'Draft quotation' : 'New quotation')) ?></h2>
      <p><?= $quotation === null ? 'Choose the customer, commercial terms and starting item for the offer.' : 'Review the offer, customer decision and order handoff in one workspace.' ?></p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>sales/"><i class="fa fa-arrow-left"></i> All sales</a></div>
  </header>

  <?php if ($error !== ''): ?>
    <div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Quotation could not be saved.</strong> <?= $e($error) ?></p></div>
  <?php endif; ?>

  <?php if ($sourceDeal !== null): ?>
    <section class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><strong>Based on won deal: <?= $e($sourceDeal->title) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $sourceDeal->value !== null ? $e($money($sourceDeal->value)) . ' · ' : '' ?>Customer and value have been carried into this quotation.</div></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($sourceDeal->uid->toString())) ?>">Open deal</a></div></div>
    </section>
  <?php endif; ?>

  <?php if ($quotation === null): ?>
    <?php if ($customers === []): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-text-center">
        <span class="fa fa-address-book-o fa-2x uk-text-muted"></span>
        <h3><?= $contactsReady ? 'Add your first customer' : 'Customer records are not available' ?></h3>
        <p class="uk-text-muted"><?= $contactsReady ? 'A quotation needs a contact or company so the offer and resulting order remain connected.' : 'Enable the Contacts component before creating customer quotations.' ?></p>
        <div class="uk-flex uk-flex-center uk-flex-wrap uk-grid-small" uk-grid>
          <?php if (!$contactsReady): ?><div><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a></div><?php endif; ?>
          <?php if ($canCreateContact): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>contact/"><i class="fa fa-user-plus"></i> New contact</a></div><?php endif; ?>
          <?php if ($canCreateCompany): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>company/"><i class="fa fa-building"></i> New company</a></div><?php endif; ?>
        </div>
        <?php if ($contactsReady && !$canCreateContact && !$canCreateCompany): ?><p class="uk-text-meta uk-margin-small-top">Ask a workspace administrator to add a customer or grant customer creation access.</p><?php endif; ?>
      </section>
    <?php else: ?>
      <form class="uk-form-stacked" method="post" action="./">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <?php if ($sourceDeal !== null): ?>
          <input type="hidden" name="deal_uid" value="<?= $e($sourceDeal->uid->toString()) ?>">
        <?php endif; ?>
        <div class="uk-grid-medium" uk-grid>
          <div class="uk-width-1-1 uk-width-2-3@l">
            <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customer offer</p>
              <h3 class="uk-card-title uk-margin-small-top">Who and what are you quoting?</h3>
              <p class="uk-text-muted">Start with one clearly described item. The draft will remain unnumbered until it is issued.</p>
              <div class="uk-margin">
                <label class="uk-form-label" for="quotation-customer">Customer</label>
                <select class="uk-select uk-margin-small-top" id="quotation-customer" name="customer" required><option value="">Select a contact or company</option><?php foreach ($customers as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['customer'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
                <div class="uk-text-meta uk-margin-small-top">The offer and any resulting order stay connected to this customer.</div>
              </div>
              <div class="uk-margin">
                <label class="uk-form-label" for="quotation-line-title">Item or service</label>
                <input class="uk-input uk-margin-small-top" id="quotation-line-title" name="line_title" value="<?= $e($values['lineTitle']) ?>" placeholder="For example: Process modernization workshop" required>
                <div class="uk-text-meta uk-margin-small-top">Describe the deliverable in language the customer will recognize.</div>
              </div>
              <div class="uk-grid-small" uk-grid>
                <div class="uk-width-1-2 uk-width-1-4@m"><label class="uk-form-label" for="quotation-quantity">Quantity</label><input class="uk-input uk-margin-small-top" id="quotation-quantity" type="number" min="0.01" step="any" name="quantity" value="<?= $e($values['quantity']) ?>" required><div class="uk-text-meta uk-margin-small-top">Must be above zero.</div></div>
                <div class="uk-width-1-2 uk-width-1-4@m"><label class="uk-form-label" for="quotation-unit">Unit</label><input class="uk-input uk-margin-small-top" id="quotation-unit" name="unit_code" value="<?= $e($values['unitCode']) ?>" placeholder="pcs, hours, days" required><div class="uk-text-meta uk-margin-small-top">How quantity is measured.</div></div>
                <div class="uk-width-1-2 uk-width-1-4@m"><label class="uk-form-label" for="quotation-price">Unit price</label><input class="uk-input uk-margin-small-top" id="quotation-price" type="number" min="0" step="0.01" name="unit_price" value="<?= $e($values['unitPrice']) ?>" placeholder="0.00" required><div class="uk-text-meta uk-margin-small-top">Amount before tax.</div></div>
                <div class="uk-width-1-2 uk-width-1-4@m"><label class="uk-form-label" for="quotation-tax">Tax rate</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon uk-form-icon-flip">%</span><input class="uk-input" id="quotation-tax" type="number" min="0" max="100" step="0.01" name="tax_rate" value="<?= $e($values['taxRate']) ?>" required></div><div class="uk-text-meta uk-margin-small-top">Between 0 and 100.</div></div>
              </div>
            </section>
          </div>
          <div class="uk-width-1-1 uk-width-1-3@l">
            <section class="uk-card uk-card-default uk-card-small uk-card-body">
              <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Document settings</p>
              <h3 class="uk-card-title uk-margin-small-top">Set the commercial terms</h3>
              <div class="uk-margin"><label class="uk-form-label" for="quotation-currency">Currency</label><input class="uk-input uk-margin-small-top uk-text-uppercase" id="quotation-currency" name="currency" value="<?= $e($values['currency']) ?>" maxlength="3" pattern="[A-Za-z]{3}" placeholder="EUR" required><div class="uk-text-meta uk-margin-small-top">Three-letter currency used for every amount in this offer.</div></div>
              <div class="uk-margin"><label class="uk-form-label" for="quotation-language">Document language</label><select class="uk-select uk-margin-small-top" id="quotation-language" name="language"><?php foreach ($languageOptions as $language => $label): ?><option value="<?= $e($language) ?>"<?= $values['language'] === $language ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Used when Kontor renders the customer-facing quotation.</div></div>
              <div class="uk-margin"><label class="uk-form-label" for="quotation-valid-until">Valid until <span class="uk-text-meta">(optional)</span></label><input class="uk-input uk-margin-small-top" id="quotation-valid-until" type="date" name="valid_until" value="<?= $e($values['validUntil']) ?>"><div class="uk-text-meta uk-margin-small-top">Leave blank when the offer has no fixed expiry date.</div></div>
              <hr>
              <button class="uk-button uk-button-primary uk-width-1-1" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Create draft</button>
              <a class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top uk-link-reset" href="<?= $e($adminUrl) ?>sales/">Cancel</a>
              <p class="uk-text-meta uk-text-center uk-margin-small-top uk-margin-remove-bottom">Review the draft before issuing a numbered PDF.</p>
            </section>
          </div>
        </div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-documenthead">
      <div>
        <span class="uk-label kontor-pill<?= $quotation->isDraft() ? ' kontor-pill--inactive' : '' ?>"><?= $e($quotation->status) ?></span>
        <strong><?= $e($customers[$quotation->customerType . ':' . $quotation->customerUid] ?? $quotation->customerUid) ?></strong>
        <span><?= $e($quotation->documentLanguage) ?> · valid until <?= $e($quotation->validUntil?->format('Y-m-d') ?? 'not set') ?></span>
      </div>
      <strong><?= $e($money($quotation->total)) ?></strong>
    </section>

    <?php if ($quotation->isDraft() && $quotationTemplate === null): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-alert uk-alert-warning kontor-warning">
        <div>
          <strong>Issuance needs a document template</strong>
          <p>Publish an active <code>quotation.standard</code> template in <?= $e(strtoupper($quotation->documentLanguage)) ?> (or English fallback) first.</p>
        </div>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>documents/">Open Documents</a>
      </section>
    <?php elseif ($quotationTemplate !== null): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
        <p class="kontor-eyebrow"><?= $quotation->isDraft() ? 'Issuance template' : 'Immutable issued output' ?></p>
        <h3><?= $e($quotationTemplate->name) ?> · v<?= $e((string) $quotationTemplate->versionNumber) ?></h3>
        <p><code><?= $e($quotationTemplate->templateKey) ?></code> · <?= $e(strtoupper($quotationTemplate->language)) ?></p>
        <?php if ($issuedFile !== null): ?>
          <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $issuedFile['uid'])) ?>">Open private PDF</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>

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

    <div class="kontor-documentactions">
      <?php if ($quotation->status === 'draft'): ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <?php if ($quotationTemplate !== null): ?>
            <button class="uk-button uk-button-primary kontor-button" name="action" value="issue" type="submit">Issue quotation + PDF</button>
          <?php endif; ?>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        </form>
      <?php elseif ($quotation->isOpen()): ?>
        <?php if ($mailReady && $mailboxes !== []): ?>
          <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
            <label>From
              <select name="mailbox_uid" required>
                <?php foreach ($mailboxes as $mailbox): ?><option value="<?= $e($mailbox->uid->toString()) ?>"><?= $e($mailbox->name . ' · ' . $mailbox->emailAddress) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label>Recipient <input type="email" name="recipient" value="<?= $e($customerEmail) ?>" required></label>
            <label><input type="checkbox" name="dry_run" value="1" checked> Simulate delivery</label>
            <button class="uk-button uk-button-primary kontor-button" name="action" value="send" type="submit"><?= $quotation->status === 'sent' ? 'Send again via Mail' : 'Send via Mail' ?></button>
          </form>
        <?php elseif ($mailReady): ?>
          <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>mail/">Create an active mailbox first</a>
        <?php else: ?>
          <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>components/">Enable Kontor Mail first</a>
        <?php endif; ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <button class="uk-button uk-button-primary kontor-button" name="action" value="accept" type="submit">Accept quotation</button>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel</button>
        </form>
      <?php elseif ($quotation->isAccepted() && $existingOrder === null): ?>
        <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <button class="uk-button uk-button-primary kontor-button" name="action" value="convert" type="submit">Create sales order</button>
        </form>
      <?php elseif ($existingOrder !== null): ?>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($existingOrder->uid->toString())) ?>">Open <?= $e($existingOrder->number ?? 'sales order') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
