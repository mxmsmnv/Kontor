<?php

/** @var \Kontor\Payments\Domain\Payment[] $payments */
/** @var \Kontor\Payments\Domain\Payment[] $summaryPayments */
/** @var array<string, string> $payerLabels */
/** @var array<string, string> $payerRoutes */
/** @var array<string, array{allocationCount: int, invoiceLabel: ?string, invoiceRoute: ?string}> $paymentContexts */
/** @var array{all: int, confirmed: int, reversed: int} $counts */
/** @var string $query */
/** @var string $selectedStatus */
/** @var bool $canViewInvoices */
/** @var bool $canViewLedger */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$methodLabels = [
    'bank_transfer' => 'Bank transfer',
    'card' => 'Card',
    'cash' => 'Cash',
    'other' => 'Other',
];
$statusLabels = ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'reversed' => 'Reversed'];
$statusClasses = ['draft' => ' uk-label-warning', 'confirmed' => ' uk-label-success', 'reversed' => ' uk-label-danger'];
$receivedByCurrency = [];
$latestPaymentDate = null;
foreach ($summaryPayments as $summaryPayment) {
    if (!$summaryPayment->isConfirmed()) {
        continue;
    }
    $currency = $summaryPayment->amount->currencyCode();
    $receivedByCurrency[$currency] = ($receivedByCurrency[$currency] ?? 0) + $summaryPayment->amount->amountMinor();
    if ($summaryPayment->paymentDate !== null
        && ($latestPaymentDate === null || $summaryPayment->paymentDate > $latestPaymentDate)) {
        $latestPaymentDate = $summaryPayment->paymentDate;
    }
}
ksort($receivedByCurrency);
$receivedSummary = '—';
if ($receivedByCurrency !== []) {
    $firstCurrency = array_key_first($receivedByCurrency);
    $receivedSummary = number_format($receivedByCurrency[$firstCurrency] / 100, 2, '.', ',') . ' ' . $firstCurrency;
    if (count($receivedByCurrency) > 1) {
        $receivedSummary .= ' +' . (count($receivedByCurrency) - 1);
    }
}
$isFiltered = $query !== '' || $selectedStatus !== '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Finance · Cash receipts</p><h2>Payments</h2><p>Review confirmed receipts, follow invoice allocations and keep reversals visible in the audit trail.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><?php if ($canViewLedger): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-book"></i> Ledger</a><?php endif; ?><?php if ($canViewInvoices): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-file-text"></i> Open invoices</a><?php endif; ?></div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-check"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $counts['confirmed']) ?></strong><span class="kontor-stat__label">Confirmed receipts</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($receivedSummary) ?></strong><span class="kontor-stat__label"><?= count($receivedByCurrency) > 1 ? count($receivedByCurrency) . ' currencies received' : 'Confirmed amount' ?></span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $counts['reversed'] > 0 ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-undo"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $counts['reversed']) ?></strong><span class="kontor-stat__label">Reversed payments</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-calendar"></i></span><span><strong class="kontor-stat__value"><?= $latestPaymentDate !== null ? $e($latestPaymentDate->format('M j, Y')) : '—' ?></strong><span class="kontor-stat__label"><?= $latestPaymentDate !== null ? 'Latest receipt date' : 'No receipts yet' ?></span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3><p>Payments are recorded from eligible invoices, confirmed and allocated in one transaction. When Ledger is installed, the allocation also creates the accounting posting. Corrections use a reversal so the original receipt remains auditable.</p></div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Receipt history</p><h3 class="uk-card-title uk-margin-small-top">Payments</h3><p class="uk-text-muted uk-margin-small-top">Open a payment to review its invoice allocation and accounting trail.</p></div><div><span class="uk-label"><?= $e((string) $counts['all']) ?> total</span></div></div>

        <form class="uk-form-stacked uk-margin-medium-top" method="get" action="<?= $e($adminUrl) ?>payments/">
          <div class="uk-grid-small uk-flex-bottom" uk-grid>
            <div class="uk-width-1-1 uk-width-expand@s"><label class="uk-form-label" for="payments-search">Find a payment</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon"><i class="fa fa-search"></i></span><input class="uk-input" id="payments-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Payment number, reference or payer" aria-describedby="payments-search-help"></div><div class="uk-text-meta uk-margin-small-top" id="payments-search-help">Searches payment numbers, transaction references and customer records.</div></div>
            <div class="uk-width-1-1 uk-width-1-3@s"><label class="uk-form-label" for="payments-status">Status</label><select class="uk-select uk-margin-small-top" id="payments-status" name="status"><option value="">All statuses</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $e($value) ?>"<?= $selectedStatus === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Use Reversed to audit corrected receipts.</div></div>
            <div class="uk-width-1-1 uk-width-auto@s"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Apply</button></div>
            <?php if ($isFiltered): ?><div class="uk-width-1-1 uk-width-auto@s"><a class="uk-button uk-button-default uk-link-reset uk-width-1-1" href="<?= $e($adminUrl) ?>payments/">Clear</a></div><?php endif; ?>
          </div>
        </form>

        <?php if ($payments !== []): ?>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($payments as $payment): ?>
              <?php
              $paymentUid = $payment->uid->toString();
              $payerKey = $payment->payerType . ':' . $payment->payerUid;
              $payerLabel = $payerLabels[$payerKey] ?? ucfirst($payment->payerType) . ' record';
              $context = $paymentContexts[$paymentUid] ?? ['allocationCount' => 0, 'invoiceLabel' => null, 'invoiceRoute' => null];
              ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
                  <div class="uk-width-expand@m"><a class="uk-link-reset" href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($paymentUid)) ?>"><strong><i class="fa fa-credit-card uk-margin-small-right"></i><?= $e($payment->number ?? 'Draft payment') ?></strong></a><div class="uk-text-meta uk-margin-small-top"><?= $e($payment->paymentDate?->format('M j, Y') ?? 'Date not set') ?> · <?= $e($methodLabels[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method))) ?><?= $payment->transactionReference !== null ? ' · Reference ' . $e($payment->transactionReference) : '' ?></div></div>
                  <div class="uk-text-right@m"><strong><?= $e($money($payment->amount)) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $statusClasses[$payment->status] ?? '' ?>"><?= $e($statusLabels[$payment->status] ?? ucfirst($payment->status)) ?></span></div></div>
                </div>
                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-small-top" uk-grid>
                  <div class="uk-width-expand@m"><span class="uk-text-meta">Payer</span><div class="uk-margin-small-top"><?php if (isset($payerRoutes[$payerKey])): ?><a class="uk-link-reset" href="<?= $e($adminUrl . $payerRoutes[$payerKey]) ?>"><strong><?= $e($payerLabel) ?></strong></a><?php else: ?><strong><?= $e($payerLabel) ?></strong><?php endif; ?></div></div>
                  <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><?php if ($context['invoiceRoute'] !== null): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl . $context['invoiceRoute']) ?>"><i class="fa fa-file-text"></i> <?= $e($context['invoiceLabel'] ?? 'Invoice') ?></a></div><?php elseif ($context['allocationCount'] > 0): ?><div><span class="uk-text-meta"><?= $e((string) $context['allocationCount']) ?> allocation(s)</span></div><?php endif; ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>payment/?id=<?= $e(rawurlencode($paymentUid)) ?>"><i class="fa fa-eye"></i> Review</a></div></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if (count($payments) === 50): ?><p class="uk-text-meta uk-margin-medium-top">Showing the 50 most recent matching payments.</p><?php endif; ?>
        <?php elseif ($isFiltered): ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching payments</h4><p class="uk-text-muted">Try another number, reference, payer or status.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>payments/">Clear filters</a></div>
        <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-money fa-2x uk-text-muted"></i><h4>No payments yet</h4><p class="uk-text-muted">Open an eligible invoice and record its first receipt. Allocation and accounting happen as part of the same workflow.</p><?php if ($canViewInvoices): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-file-text"></i> Open invoices</a><?php endif; ?></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Guided workflow</p><h3 class="uk-card-title uk-margin-small-top">Record a receipt</h3><p class="uk-text-muted">Payments begin from the invoice they settle, keeping customer, currency and outstanding balance consistent.</p>
        <ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-middle"><span class="uk-label uk-margin-small-right">1</span><span>Open an issued, sent, overdue or partially paid invoice.</span></div></li><li><div class="uk-flex uk-flex-middle"><span class="uk-label uk-margin-small-right">2</span><span>Enter the received amount, method and bank or processor reference.</span></div></li><li><div class="uk-flex uk-flex-middle"><span class="uk-label uk-margin-small-right">3</span><span>Kontor confirms the receipt, allocates it and updates connected finance records.</span></div></li></ol>
        <?php if ($canViewInvoices): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset uk-margin-top" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-arrow-right"></i> Choose an invoice</a><?php else: ?><div class="uk-alert-primary uk-margin-top" uk-alert><p><i class="fa fa-lock"></i> Invoice access is required to start a payment workflow.</p></div><?php endif; ?>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Controls</p><h3 class="uk-card-title uk-margin-small-top">Audit-safe corrections</h3><p class="uk-text-muted">Confirmed payments are not silently edited or deleted. Reverse an incorrect receipt from its detail page so invoice allocations and ledger postings are corrected together.</p>
        <div class="uk-alert-warning uk-margin-top" uk-alert><p><i class="fa fa-undo"></i> A reversal preserves the original payment and creates a visible correction trail.</p></div>
        <?php if ($canViewLedger): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-book"></i> Review ledger</a><?php endif; ?>
      </section>
    </div>
  </div>
</div>
