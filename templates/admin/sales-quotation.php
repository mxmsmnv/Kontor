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
/** @var \Kontor\Sales\Domain\Quotation[] $existingDealQuotations */
/** @var bool $canViewExistingQuotation */
/** @var bool $mailReady */
/** @var \Kontor\Mail\Domain\Mailbox[] $mailboxes */
/** @var string $customerEmail */
/** @var bool $canIssue */
/** @var bool $canSend */
/** @var bool $canAccept */
/** @var bool $canCancel */
/** @var bool $canCreateOrder */
/** @var bool $canViewOrder */
/** @var bool $documentsReady */
/** @var bool $filesReady */
/** @var bool $showDocuments */
/** @var bool $showFiles */
/** @var bool $canManageMailboxes */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$humanize = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'accepted' => ' uk-label-success',
    'rejected', 'cancelled', 'expired' => ' uk-label-danger',
    'issued', 'sent' => ' uk-label-warning',
    default => '',
};
$duplicateQuotation = $quotation === null && $existingDealQuotations !== [];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Sales · Quotation</p>
      <h2><?= $e($duplicateQuotation ? 'Quotation already exists' : ($quotation?->number ?? ($quotation !== null ? 'Draft quotation' : 'New quotation'))) ?></h2>
      <p><?= $duplicateQuotation ? 'Continue the existing customer offer instead of creating a duplicate.' : ($quotation === null ? 'Choose the customer, commercial terms and starting item for the offer.' : 'Review the offer, customer decision and order handoff in one workspace.') ?></p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>sales/"><i class="fa fa-arrow-left"></i> All sales</a></div>
  </header>

  <?php if ($error !== ''): ?>
    <div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Quotation could not be saved.</strong> <?= $e($error) ?></p></div>
  <?php endif; ?>

  <?php if ($sourceDeal !== null && $quotation === null && !$duplicateQuotation): ?>
    <section class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><strong>Based on won deal: <?= $e($sourceDeal->title) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $sourceDeal->value !== null ? $e($money($sourceDeal->value)) . ' · ' : '' ?>Customer and value have been carried into this quotation.</div></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($sourceDeal->uid->toString())) ?>">Open deal</a></div></div>
    </section>
  <?php endif; ?>

  <?php if ($quotation === null): ?>
    <?php if ($duplicateQuotation): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div>
          <div>
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected sales workflow</p>
            <h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom">This won deal already has a quotation</h3>
            <p class="uk-text-muted uk-margin-remove">Open the existing offer to review its customer decision and continue to the sales order. Kontor prevents another quotation from being created for the same deal.</p>
          </div>
        </div>
        <?php if ($canViewExistingQuotation): ?>
          <ul class="uk-list uk-list-divider uk-margin">
            <?php foreach ($existingDealQuotations as $existingQuotation): ?>
              <li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($existingQuotation->status) ?>"><?= $e($humanize($existingQuotation->status)) ?></span><h4 class="uk-margin-small-top uk-margin-remove-bottom"><?= $e($existingQuotation->number ?? 'Draft quotation') ?></h4><div class="uk-text-meta uk-margin-small-top"><?= $e($money($existingQuotation->total)) ?></div></div><div><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>sales-quotation/?id=<?= $e(rawurlencode($existingQuotation->uid->toString())) ?>">Open quotation <i class="fa fa-angle-right"></i></a></div></div></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?><div class="uk-alert-primary uk-margin" uk-alert><p class="uk-margin-remove">A quotation is already connected to this deal. Quotation access is required to open it.</p></div><?php endif; ?>
        <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($sourceDeal->uid->toString())) ?>"><i class="fa fa-arrow-left"></i> Back to deal</a>
      </section>
    <?php elseif ($customers === []): ?>
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
                <?php if ($sourceDeal !== null): ?><input type="hidden" name="customer" value="<?= $e($values['customer']) ?>"><?php endif; ?>
                <select class="uk-select uk-margin-small-top" id="quotation-customer"<?= $sourceDeal === null ? ' name="customer" required' : ' disabled' ?>><option value="">Select a contact or company</option><?php foreach ($customers as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['customer'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
                <div class="uk-text-meta uk-margin-small-top"><?= $sourceDeal !== null ? 'Customer inherited from the won deal. Update the CRM deal if this relationship is wrong.' : 'The offer and any resulting order stay connected to this customer.' ?></div>
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
              <a class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top uk-link-reset" href="<?= $e($adminUrl) ?><?= $sourceDeal !== null ? 'crm-deal/?id=' . $e(rawurlencode($sourceDeal->uid->toString())) : 'sales/' ?>">Cancel</a>
              <p class="uk-text-meta uk-text-center uk-margin-small-top uk-margin-remove-bottom">Review the draft before issuing a numbered PDF.</p>
            </section>
          </div>
        </div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <?php
    $customerName = $customers[$quotation->customerType . ':' . $quotation->customerUid] ?? 'Customer unavailable';
    $languageName = $languageOptions[$quotation->documentLanguage] ?? strtoupper($quotation->documentLanguage);
    $nextStep = match ($quotation->status) {
        'draft' => ['Prepare the customer document', 'Issue the quotation to assign its number and lock the customer-facing PDF.'],
        'issued' => ['Send or record the decision', 'Deliver the quotation by email, then record whether the customer accepted it.'],
        'sent' => ['Await the customer decision', 'The quotation has been sent. Record acceptance when the customer confirms.'],
        'accepted' => $existingOrder === null
            ? ['Hand off to fulfillment', 'Create a sales order from this accepted quotation.']
            : ['Continue with the sales order', 'The accepted quotation has already been converted to an order.'],
        default => ['Quotation closed', 'No further sales action is required for this quotation.'],
    };
    ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-grid-medium uk-flex-middle" uk-grid>
        <div class="uk-width-1-1 uk-width-expand@m">
          <div class="uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($quotation->status) ?>"><?= $e($humanize($quotation->status)) ?></span></div><?php if ($quotation->number === null): ?><div><span class="uk-text-meta">Not issued</span></div><?php endif; ?></div>
          <h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($customerName) ?></h3>
          <p class="uk-text-muted uk-margin-remove">Customer quotation<?= $sourceDeal !== null ? ' created from a won CRM deal' : '' ?>.</p>
        </div>
        <div class="uk-width-auto@m uk-text-right@m"><div class="uk-text-meta">Quotation total</div><div class="uk-text-large"><strong><?= $e($money($quotation->total)) ?></strong></div></div>
      </div>
      <hr>
      <div class="uk-grid-small uk-grid-divider uk-child-width-1-2 uk-child-width-1-4@l" uk-grid>
        <div><div class="uk-text-meta">Issued</div><strong><?= $e($quotation->issueDate?->format('M j, Y') ?? 'Not issued yet') ?></strong></div>
        <div><div class="uk-text-meta">Valid until</div><strong><?= $e($quotation->validUntil?->format('M j, Y') ?? 'No expiry') ?></strong></div>
        <div><div class="uk-text-meta">Document language</div><strong><?= $e($languageName) ?></strong></div>
        <div><div class="uk-text-meta">Currency</div><strong><?= $e($quotation->currencyCode) ?></strong></div>
      </div>
    </section>

    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Commercial terms</p>
          <h3 class="uk-card-title uk-margin-small-top">Quoted items</h3>
          <?php if ($lines !== []): ?>
            <div class="uk-overflow-auto uk-visible@m">
              <table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove-bottom">
                <thead><tr><th>Item or service</th><th>Quantity</th><th>Unit price</th><th>Tax</th><th class="uk-text-right">Line total</th></tr></thead>
                <tbody><?php foreach ($lines as $line): ?><tr><td><strong><?= $e($line->title) ?></strong></td><td><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></td><td><?= $e($money($line->unitPrice)) ?></td><td><?= $e($line->taxRate) ?>%</td><td class="uk-text-right"><strong><?= $e($money($line->total())) ?></strong></td></tr><?php endforeach; ?></tbody>
              </table>
            </div>
            <ul class="uk-list uk-list-divider uk-hidden@m uk-margin-remove-bottom"><?php foreach ($lines as $line): ?><li><strong><?= $e($line->title) ?></strong><div class="uk-grid-small uk-child-width-1-2 uk-margin-small-top" uk-grid><div><span class="uk-text-meta">Quantity</span><br><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></div><div><span class="uk-text-meta">Unit price</span><br><?= $e($money($line->unitPrice)) ?></div><div><span class="uk-text-meta">Tax</span><br><?= $e($line->taxRate) ?>%</div><div><span class="uk-text-meta">Line total</span><br><strong><?= $e($money($line->total())) ?></strong></div></div></li><?php endforeach; ?></ul>
          <?php else: ?><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No commercial items have been added.</p></div><?php endif; ?>
          <hr>
          <div class="uk-grid-small uk-flex-right" uk-grid>
            <div class="uk-width-1-1 uk-width-1-2@s">
              <dl class="uk-description-list uk-margin-remove">
                <div class="uk-flex uk-flex-between"><dt>Subtotal</dt><dd><?= $e($money($quotation->subtotal)) ?></dd></div>
                <?php if ($quotation->discount->amountMinor() > 0): ?><div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Discount</dt><dd>−<?= $e($money($quotation->discount)) ?></dd></div><?php endif; ?>
                <div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Tax</dt><dd><?= $e($money($quotation->tax)) ?></dd></div>
                <div class="uk-flex uk-flex-between uk-margin-small-top"><dt><strong>Total</strong></dt><dd><strong><?= $e($money($quotation->total)) ?></strong></dd></div>
              </dl>
            </div>
          </div>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
            <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $quotation->isDraft() ? 'Document readiness' : 'Customer document' ?></p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $quotation->isDraft() ? 'Quotation PDF' : 'Issued quotation' ?></h3></div>
            <?php if (!$quotation->isDraft()): ?><div><span class="uk-label uk-label-success"><i class="fa fa-lock"></i> Locked output</span></div><?php endif; ?>
          </div>
          <?php if ($quotationTemplate !== null): ?>
            <p class="uk-text-muted"><?= $quotation->isDraft() ? 'Ready to render with' : 'Rendered from' ?> <?= $e($quotationTemplate->name) ?>, version <?= $e((string) $quotationTemplate->versionNumber) ?> in <?= $e($languageName) ?>.</p>
            <?php if ($quotation->isDraft() && !$filesReady): ?><div class="uk-alert-warning" uk-alert><p><strong>Private file storage is required to issue this quotation.</strong> Enable Files so Kontor can preserve the locked PDF.</p></div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a><?php endif; ?>
            <?php if ($issuedFile !== null && $showFiles): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $issuedFile['uid'])) ?>"><i class="fa fa-file-pdf-o"></i> Open issued PDF</a><?php elseif (!$quotation->isDraft()): ?><p class="uk-text-meta uk-margin-remove-bottom">The issued document is stored privately. File access is required to open it.</p><?php endif; ?>
          <?php elseif ($quotation->isDraft()): ?>
            <div class="uk-alert-warning" uk-alert><p><strong>A published quotation template is required before issue.</strong> <?= $documentsReady ? 'Publish a template for this document language or an English fallback.' : 'Enable Documents and Files to generate locked customer PDFs.' ?></p></div>
            <?php if ($showDocuments): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>documents/"><i class="fa fa-file-text-o"></i> Open document templates</a><?php elseif (!$documentsReady || !$filesReady): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a><?php endif; ?>
          <?php else: ?><p class="uk-text-muted uk-margin-remove-bottom">The customer document metadata is unavailable because Documents is not active.</p><?php endif; ?>
        </section>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next step</p>
          <h3 class="uk-card-title uk-margin-small-top"><?= $e($nextStep[0]) ?></h3>
          <p class="uk-text-muted"><?= $e($nextStep[1]) ?></p>

          <?php if ($quotation->status === 'draft'): ?>
            <?php if ($canIssue && $quotationTemplate !== null && $filesReady): ?><form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="issue" type="submit" data-kontor-confirm="Issue this quotation and lock its customer PDF?"><i class="fa fa-file-pdf-o"></i> Issue quotation</button></form><?php elseif ($canIssue): ?><p class="uk-text-meta">Complete the document setup shown beside the quotation before issuing.</p><?php endif; ?>
          <?php elseif ($quotation->isOpen()): ?>
            <?php if ($canSend && $mailReady && $mailboxes !== []): ?><button class="uk-button uk-button-primary uk-width-1-1" type="button" uk-toggle="target: #quotation-send"><i class="fa fa-envelope"></i> <?= $quotation->status === 'sent' ? 'Send again' : 'Send quotation' ?></button>
            <?php elseif ($canSend && $mailReady && $canManageMailboxes): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>mail/">Set up a mailbox</a>
            <?php elseif ($canSend && $mailReady): ?><p class="uk-text-meta">An active shared mailbox is required. Ask a Mail administrator to configure one.</p>
            <?php elseif ($canSend && !$mailReady): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Enable Mail to send</a><?php endif; ?>
            <?php if ($canAccept): ?><form class="uk-margin-small-top" method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>"><button class="uk-button uk-button-default uk-width-1-1" name="action" value="accept" type="submit" data-kontor-confirm="Record that the customer accepted this quotation?"><i class="fa fa-check"></i> Record acceptance</button></form><?php endif; ?>
          <?php elseif ($quotation->isAccepted() && $existingOrder === null && $canCreateOrder): ?>
            <form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="convert" type="submit"><i class="fa fa-shopping-cart"></i> Create sales order</button></form>
          <?php elseif ($existingOrder !== null && $canViewOrder): ?>
            <a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($existingOrder->uid->toString())) ?>"><i class="fa fa-shopping-cart"></i> Open <?= $e($existingOrder->number ?? 'sales order') ?></a>
          <?php elseif ($existingOrder !== null): ?><p class="uk-text-meta">A sales order has been created. Order access is required to continue.</p><?php endif; ?>

          <?php if ($canCancel && !$quotation->isClosed()): ?><hr><form method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>"><button class="uk-button uk-button-text uk-text-danger" name="action" value="cancel" type="submit" data-kontor-confirm="Cancel this quotation?">Cancel quotation</button></form><?php endif; ?>
        </section>

        <?php if ($sourceDeal !== null || $existingOrder !== null): ?>
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected work</p><h3 class="uk-card-title uk-margin-small-top">Related records</h3>
            <ul class="uk-list uk-list-divider uk-margin-remove-bottom">
              <?php if ($sourceDeal !== null): ?><li><div class="uk-text-meta">Source CRM deal</div><strong><?= $e($sourceDeal->title) ?></strong><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($sourceDeal->uid->toString())) ?>">Open deal</a></div></li><?php endif; ?>
              <?php if ($existingOrder !== null && $canViewOrder): ?><li><div class="uk-text-meta">Sales order</div><strong><?= $e($existingOrder->number ?? 'Pending order') ?></strong><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($existingOrder->uid->toString())) ?>">Open order</a></div></li><?php endif; ?>
            </ul>
          </section>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($quotation->isOpen() && $canSend && $mailReady && $mailboxes !== []): ?>
      <div id="quotation-send" uk-modal><div class="uk-modal-dialog uk-modal-body">
        <a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customer delivery</p><h2 class="uk-modal-title uk-margin-small-top"><?= $quotation->status === 'sent' ? 'Send quotation again' : 'Send quotation' ?></h2><p class="uk-text-muted">Kontor records the message in Mail and links it to this quotation.</p>
        <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>sales-quotation-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($quotation->uid->toString()) ?>">
          <div class="uk-margin"><label class="uk-form-label" for="quotation-mailbox">From mailbox</label><select class="uk-select uk-margin-small-top" id="quotation-mailbox" name="mailbox_uid" required><?php foreach ($mailboxes as $mailbox): ?><option value="<?= $e($mailbox->uid->toString()) ?>"><?= $e($mailbox->name . ' · ' . $mailbox->emailAddress) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Choose the shared mailbox the customer should reply to.</div></div>
          <div class="uk-margin"><label class="uk-form-label" for="quotation-recipient">Recipient</label><input class="uk-input uk-margin-small-top" id="quotation-recipient" type="email" name="recipient" value="<?= $e($customerEmail) ?>" placeholder="customer@example.com" required><div class="uk-text-meta uk-margin-small-top">Confirm the address before sending customer information.</div></div>
          <label class="uk-display-block uk-margin"><input class="uk-checkbox" type="checkbox" name="dry_run" value="1" checked> <span class="uk-margin-small-left"><strong>Test only</strong></span><span class="uk-text-meta uk-display-block uk-margin-small-left">Record the delivery without sending an external email.</span></label>
          <div class="uk-flex uk-flex-right uk-grid-small" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" name="action" value="send" type="submit"><i class="fa fa-envelope"></i> <?= $quotation->status === 'sent' ? 'Send again' : 'Send quotation' ?></button></div></div>
        </form>
      </div></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
