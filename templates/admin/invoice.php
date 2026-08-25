<?php

/** @var \Kontor\Invoices\Domain\Invoice $invoice */
/** @var \Kontor\Sales\Domain\DocumentLine[] $lines */
/** @var \Kontor\Sales\Domain\Order|null $sourceOrder */
/** @var \Kontor\Payments\Domain\PaymentAllocation[] $allocations */
/** @var array<string, string> $paymentLabels */
/** @var \Kontor\Documents\Domain\DocumentTemplate|null $invoiceTemplate */
/** @var \Kontor\Documents\Domain\DocumentTemplate|null $creditNoteTemplate */
/** @var array<string, mixed>|null $issuedFile */
/** @var \Kontor\Mail\Domain\Mailbox[] $mailboxes */
/** @var \Kontor\Ledger\Domain\LedgerEntry|null $ledgerPosting */
/** @var \Kontor\Ledger\Domain\LedgerEntry|null $ledgerCancellation */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$humanize = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'paid' => ' uk-label-success',
    'cancelled' => ' uk-label-danger',
    'sent', 'overdue', 'partially_paid' => ' uk-label-warning',
    default => '',
};
$collectible = in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true)
    && $invoice->due->amountMinor() > 0;
$nextStep = match ($invoice->status) {
    'draft' => ['Review and issue', 'Confirm the customer, amounts and billing document before making this invoice final.'],
    'issued' => ['Deliver the invoice', 'Send the issued billing document to the customer, then track collection here.'],
    'sent', 'overdue', 'partially_paid' => ['Collect the balance', 'Record incoming payments until the outstanding balance is settled.'],
    'paid' => ['Balance settled', 'This invoice is fully paid. The billing record and payment history are complete.'],
    'cancelled' => ['Invoice closed', 'This invoice is cancelled and no further action is required.'],
    default => ['Review invoice', 'Review the billing record and continue with the next available action.'],
};
$documentLabel = $invoice->kind === 'credit_note' ? 'Credit note' : 'Invoice';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Billing · <?= $e($documentLabel) ?></p><h2><?= $e($invoice->number ?? 'Draft ' . strtolower($documentLabel)) ?></h2><p>Review the customer document, collection status and accounting outcome in one workspace.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-arrow-left"></i> All invoices</a></div>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@m"><span class="uk-label<?= $statusClass($invoice->status) ?>"><?= $e($humanize($invoice->status)) ?></span><h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($customerLabel) ?></h3><p class="uk-text-muted uk-margin-remove"><?= $sourceOrder !== null ? 'Created from customer order ' . $e($sourceOrder->number ?? 'sales order') . '.' : 'Direct customer billing document.' ?></p></div>
      <div class="uk-width-auto@m uk-text-right@m"><div class="uk-text-meta">Invoice total</div><div class="uk-text-large"><strong><?= $e($money($invoice->total)) ?></strong></div></div>
    </div>
    <hr>
    <div class="uk-grid-small uk-grid-divider uk-child-width-1-2 uk-child-width-1-4@l" uk-grid>
      <div><div class="uk-text-meta">Issue date</div><strong><?= $e($invoice->issueDate?->format('M j, Y') ?? 'Not issued') ?></strong></div>
      <div><div class="uk-text-meta">Due date</div><strong><?= $e($invoice->dueDate?->format('M j, Y') ?? 'Not set') ?></strong></div>
      <div><div class="uk-text-meta">Paid</div><strong><?= $e($money($invoice->paid)) ?></strong></div>
      <div><div class="uk-text-meta">Outstanding</div><strong><?= $e($money($invoice->due)) ?></strong></div>
    </div>
  </section>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Commercial details</p><h3 class="uk-card-title uk-margin-small-top">Billed items</h3>
        <?php if ($lines !== []): ?>
          <div class="uk-overflow-auto uk-visible@m"><table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove-bottom"><thead><tr><th>Item or service</th><th>Quantity</th><th>Unit price</th><th>Tax</th><th class="uk-text-right">Line total</th></tr></thead><tbody><?php foreach ($lines as $line): ?><tr><td><strong><?= $e($line->title) ?></strong></td><td><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></td><td><?= $e($money($line->unitPrice)) ?></td><td><?= $e($line->taxRate) ?>%</td><td class="uk-text-right"><strong><?= $e($money($line->total())) ?></strong></td></tr><?php endforeach; ?></tbody></table></div>
          <ul class="uk-list uk-list-divider uk-hidden@m uk-margin-remove-bottom"><?php foreach ($lines as $line): ?><li><strong><?= $e($line->title) ?></strong><div class="uk-grid-small uk-child-width-1-2 uk-margin-small-top" uk-grid><div><span class="uk-text-meta">Quantity</span><br><?= $e($line->quantity) ?> <?= $e($line->unitCode) ?></div><div><span class="uk-text-meta">Unit price</span><br><?= $e($money($line->unitPrice)) ?></div><div><span class="uk-text-meta">Tax</span><br><?= $e($line->taxRate) ?>%</div><div><span class="uk-text-meta">Line total</span><br><strong><?= $e($money($line->total())) ?></strong></div></div></li><?php endforeach; ?></ul>
        <?php else: ?><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No billed items are available.</p></div><?php endif; ?>
        <hr><div class="uk-grid-small uk-flex-right" uk-grid><div class="uk-width-1-1 uk-width-1-2@s"><dl class="uk-description-list uk-margin-remove">
          <div class="uk-flex uk-flex-between"><dt>Subtotal</dt><dd><?= $e($money($invoice->subtotal)) ?></dd></div>
          <?php if ($invoice->discount->amountMinor() > 0): ?><div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Discount</dt><dd>−<?= $e($money($invoice->discount)) ?></dd></div><?php endif; ?>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Tax</dt><dd><?= $e($money($invoice->tax)) ?></dd></div>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt><strong>Total</strong></dt><dd><strong><?= $e($money($invoice->total)) ?></strong></dd></div>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt>Paid</dt><dd>−<?= $e($money($invoice->paid)) ?></dd></div>
          <div class="uk-flex uk-flex-between uk-margin-small-top"><dt><strong>Outstanding</strong></dt><dd><strong><?= $e($money($invoice->due)) ?></strong></dd></div>
        </dl></div></div>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customer document</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $invoice->isDraft() ? 'Document preparation' : 'Issued billing document' ?></h3></div><?php if ($issuedFile !== null && $showFiles): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $issuedFile['uid'])) ?>"><i class="fa fa-file-pdf-o"></i> Open PDF</a></div><?php endif; ?></div>
        <?php if ($invoiceTemplate !== null): ?><p class="uk-text-muted"><?= $invoice->isDraft() ? 'This published template will be used to create the final PDF.' : 'The customer document was generated from a published template and retained as a private file.' ?></p><div class="uk-grid-small uk-child-width-1-2@s" uk-grid><div><div class="uk-text-meta">Template</div><strong><?= $e($invoiceTemplate->name) ?></strong></div><div><div class="uk-text-meta">Language</div><strong><?= $e(strtoupper($invoiceTemplate->language)) ?></strong></div></div><?php if (!$invoice->isDraft() && $issuedFile === null): ?><div class="uk-alert-warning uk-margin" uk-alert><p>The private PDF is not available. Check Files storage before delivery.</p></div><?php endif; ?>
        <?php elseif (!$documentsReady): ?><p class="uk-text-muted">Enable Documents to prepare a consistent customer PDF.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
        <?php elseif ($invoice->isDraft()): ?><div class="uk-alert-warning" uk-alert><p><strong>A published <?= $e(strtolower($documentLabel)) ?> template is required.</strong> Add one before issuing.</p></div><?php if ($showDocuments): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>documents/">Open Documents</a><?php endif; ?>
        <?php else: ?><p class="uk-text-muted uk-margin-remove-bottom">Template details are unavailable for this issued document.</p><?php endif; ?>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Collection</p><h3 class="uk-card-title uk-margin-small-top">Payment history</h3>
        <?php if ($allocations !== [] && $canViewPayments): ?>
          <div class="uk-overflow-auto uk-visible@m"><table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove-bottom"><thead><tr><th>Payment</th><th>Allocated</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($allocations as $allocation): ?><tr><td><strong><?= $e($paymentLabels[$allocation->paymentUid] ?? 'Payment') ?></strong></td><td><?= $e($money($allocation->amount)) ?></td><td><?= $e($allocation->allocatedAt->format('M j, Y')) ?></td><td><span class="uk-label<?= $allocation->isReversed() ? ' uk-label-danger' : ' uk-label-success' ?>"><?= $e($allocation->isReversed() ? 'Reversed' : 'Applied') ?></span></td><td class="uk-text-right"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($allocation->paymentUid)) ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div>
          <ul class="uk-list uk-list-divider uk-hidden@m uk-margin-remove-bottom"><?php foreach ($allocations as $allocation): ?><li><div class="uk-flex uk-flex-between uk-flex-middle"><strong><?= $e($paymentLabels[$allocation->paymentUid] ?? 'Payment') ?></strong><span class="uk-label<?= $allocation->isReversed() ? ' uk-label-danger' : ' uk-label-success' ?>"><?= $e($allocation->isReversed() ? 'Reversed' : 'Applied') ?></span></div><div class="uk-text-muted uk-margin-small-top"><?= $e($money($allocation->amount)) ?> · <?= $e($allocation->allocatedAt->format('M j, Y')) ?></div><a class="uk-button uk-button-default uk-button-small uk-width-1-1 uk-margin-small-top uk-link-reset" href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($allocation->paymentUid)) ?>">Open payment</a></li><?php endforeach; ?></ul>
        <?php elseif ($allocations !== []): ?><p class="uk-text-muted uk-margin-remove-bottom">Payments have been applied. Payment access is required to view details.</p>
        <?php elseif (!$paymentsReady): ?><p class="uk-text-muted">Enable Payments to record receipts and maintain the outstanding balance.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
        <?php else: ?><div class="uk-placeholder uk-text-center"><span class="fa fa-credit-card fa-2x uk-text-muted"></span><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $invoice->due->amountMinor() > 0 ? 'No payments have been applied yet.' : 'No separate payment records are attached.' ?></p></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next step</p><h3 class="uk-card-title uk-margin-small-top"><?= $e($nextStep[0]) ?></h3><p class="uk-text-muted"><?= $e($nextStep[1]) ?></p>
        <?php if ($invoice->isDraft()): ?>
          <?php if ($canIssue && $invoiceTemplate !== null && $filesReady): ?><form method="post" action="<?= $e($adminUrl) ?>invoice-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="issue" type="submit" data-kontor-confirm="Issue this invoice and create its final PDF?"><i class="fa fa-file-text"></i> Issue invoice</button></form>
          <?php elseif (!$filesReady): ?><div class="uk-alert-warning" uk-alert><p>Files is required to retain the final PDF.</p></div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
          <?php elseif ($invoiceTemplate === null): ?><p class="uk-text-meta">Prepare a published document template before issuing.</p>
          <?php else: ?><p class="uk-text-meta">Invoice issuance access is required.</p><?php endif; ?>
        <?php elseif ($invoice->status === 'issued'): ?>
          <?php if ($canSend && $mailReady && $mailboxes !== []): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset" href="#invoice-send" uk-toggle><i class="fa fa-envelope"></i> Send invoice</a>
          <?php elseif (!$mailReady): ?><p class="uk-text-muted">Enable Mail to deliver this invoice from a shared mailbox.</p><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
          <?php elseif ($mailboxes === []): ?><p class="uk-text-muted">Create an active mailbox before delivery.</p><?php if ($canManageMailboxes): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>mail/">Open Mail</a><?php endif; ?>
          <?php else: ?><p class="uk-text-meta">Invoice delivery access is required.</p><?php endif; ?>
        <?php elseif ($collectible): ?>
          <?php if ($canRecordPayment): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset" href="#invoice-payment" uk-toggle><i class="fa fa-credit-card"></i> Record payment</a>
          <?php elseif (!$paymentsReady): ?><p class="uk-text-muted">Enable Payments to collect and allocate the outstanding balance.</p><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open components</a>
          <?php else: ?><p class="uk-text-meta">Payment creation and allocation access are required.</p><?php endif; ?>
        <?php else: ?><div class="uk-alert-success" uk-alert><p class="uk-margin-remove"><i class="fa fa-check-circle"></i> No collection action is needed.</p></div><?php endif; ?>
        <?php if ($invoice->isCancellable() && $canCancel): ?><hr><form method="post" action="<?= $e($adminUrl) ?>invoice-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>"><button class="uk-button uk-button-text uk-text-danger" name="action" value="cancel" type="submit" data-kontor-confirm="Cancel this invoice?">Cancel invoice</button></form><?php endif; ?>
        <?php if ($invoice->kind === 'invoice' && $invoice->isCreditable() && $canCredit): ?><hr><p class="uk-text-meta">Need to reverse this invoice?</p><?php if ($creditNoteTemplate !== null && $filesReady): ?><form method="post" action="<?= $e($adminUrl) ?>invoice-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>"><button class="uk-button uk-button-default uk-width-1-1" name="action" value="credit" type="submit" data-kontor-confirm="Create and issue a credit note?"><i class="fa fa-undo"></i> Create credit note</button></form><?php elseif ($showDocuments): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>documents/">Prepare credit note template</a><?php endif; ?><?php endif; ?>
      </section>

      <?php if ($showLedger): ?><section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Accounting</p><h3 class="uk-card-title uk-margin-small-top">Financial posting</h3>
        <?php if ($ledgerPosting !== null): ?><p class="uk-text-muted">The invoice has been recorded in receivables, revenue and sales tax.</p><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($ledgerPosting->uid->toString())) ?>">Open accounting entry</a><?php if ($ledgerCancellation !== null): ?><a class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($ledgerCancellation->uid->toString())) ?>">Open reversal entry</a><?php endif; ?>
        <?php elseif (!$invoice->isDraft() && $invoice->status !== 'cancelled'): ?><p class="uk-text-muted uk-margin-remove-bottom">No accounting entry is available for this invoice.</p>
        <?php elseif ($invoice->isDraft()): ?><p class="uk-text-muted uk-margin-remove-bottom">Issuing will record the financial posting automatically.</p>
        <?php else: ?><p class="uk-text-muted uk-margin-remove-bottom">No accounting entry was required.</p><?php endif; ?>
      </section><?php endif; ?>

      <?php if ($sourceOrder !== null): ?><section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected work</p><h3 class="uk-card-title uk-margin-small-top">Related records</h3><ul class="uk-list uk-list-divider uk-margin-remove-bottom"><li><div class="uk-text-meta">Source sales order</div><strong><?= $e($sourceOrder->number ?? 'Sales order') ?></strong><div class="uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>sales-order/?id=<?= $e(rawurlencode($sourceOrder->uid->toString())) ?>">Open order</a></div></li></ul></section><?php endif; ?>
    </div>
  </div>

  <?php if ($invoice->status === 'issued' && $canSend && $mailReady && $mailboxes !== []): ?>
    <div id="invoice-send" uk-modal><div class="uk-modal-dialog uk-modal-body"><a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customer delivery</p><h2 class="uk-modal-title uk-margin-small-top">Send invoice</h2><p class="uk-text-muted">Kontor records the message in Mail and links it to this invoice.</p>
      <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>invoice-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($invoice->uid->toString()) ?>">
        <div class="uk-margin"><label class="uk-form-label" for="invoice-mailbox">From mailbox</label><select class="uk-select uk-margin-small-top" id="invoice-mailbox" name="mailbox_uid" required><?php foreach ($mailboxes as $mailbox): ?><option value="<?= $e($mailbox->uid->toString()) ?>"><?= $e($mailbox->name . ' · ' . $mailbox->emailAddress) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Choose the shared mailbox the customer should reply to.</div></div>
        <div class="uk-margin"><label class="uk-form-label" for="invoice-recipient">Recipient</label><input class="uk-input uk-margin-small-top" id="invoice-recipient" type="email" name="recipient" value="<?= $e($customerEmail) ?>" placeholder="customer@example.com" required><div class="uk-text-meta uk-margin-small-top">Confirm the address before sending financial information.</div></div>
        <label class="uk-display-block uk-margin"><input class="uk-checkbox" type="checkbox" name="dry_run" value="1" checked> <span class="uk-margin-small-left"><strong>Test only</strong></span><span class="uk-text-meta uk-display-block uk-margin-small-left">Record delivery without sending an external email.</span></label>
        <div class="uk-flex uk-flex-right uk-grid-small" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" name="action" value="send" type="submit"><i class="fa fa-envelope"></i> Send invoice</button></div></div>
      </form>
    </div></div>
  <?php endif; ?>

  <?php if ($collectible && $canRecordPayment): ?>
    <div id="invoice-payment" uk-modal><div class="uk-modal-dialog uk-modal-body"><a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Collection</p><h2 class="uk-modal-title uk-margin-small-top">Record payment</h2><p class="uk-text-muted">Create a payment and apply it to the outstanding balance.</p>
      <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>payment-from-invoice/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="invoice_uid" value="<?= $e($invoice->uid->toString()) ?>">
        <div class="uk-margin"><label class="uk-form-label" for="invoice-payment-amount">Amount</label><input class="uk-input uk-margin-small-top" id="invoice-payment-amount" name="amount" type="number" min="0.01" max="<?= $e(number_format($invoice->due->amountMinor() / 100, 2, '.', '')) ?>" step="0.01" value="<?= $e(number_format($invoice->due->amountMinor() / 100, 2, '.', '')) ?>" required><div class="uk-text-meta uk-margin-small-top">Enter the amount received, up to the outstanding balance.</div></div>
        <div class="uk-margin"><label class="uk-form-label" for="invoice-payment-method">Payment method</label><select class="uk-select uk-margin-small-top" id="invoice-payment-method" name="method"><option value="bank_transfer">Bank transfer</option><option value="card">Card payment</option><option value="cash">Cash</option><option value="other">Other</option></select><div class="uk-text-meta uk-margin-small-top">Choose the channel used by the customer.</div></div>
        <div class="uk-margin"><label class="uk-form-label" for="invoice-payment-reference">Reference <span class="uk-text-meta">Optional</span></label><input class="uk-input uk-margin-small-top" id="invoice-payment-reference" name="transaction_reference" placeholder="Bank or processor reference"><div class="uk-text-meta uk-margin-small-top">Add a traceable reference when available.</div></div>
        <div class="uk-flex uk-flex-right uk-grid-small" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-check"></i> Record payment</button></div></div>
      </form>
    </div></div>
  <?php endif; ?>
</div>
