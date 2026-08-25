<?php

/** @var \Kontor\Payments\Domain\Payment $payment */
/** @var \Kontor\Payments\Domain\PaymentAllocation[] $allocations */
/** @var array<string, string> $invoiceLabels */
/** @var array<string, array{label: string, status: string, total: \Kontor\SDK\ValueObjects\Money, due: \Kontor\SDK\ValueObjects\Money, route: ?string}> $invoiceViews */
/** @var bool $ledgerReady */
/** @var array<string, array{posting: \Kontor\Ledger\Domain\LedgerEntry|null, reversal: \Kontor\Ledger\Domain\LedgerEntry|null}> $ledgerEntries */
/** @var string $payerLabel */
/** @var string|null $payerRoute */
/** @var bool $canReverse */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$methodLabels = ['bank_transfer' => 'Bank transfer', 'card' => 'Card', 'cash' => 'Cash', 'other' => 'Other'];
$statusLabels = ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'reversed' => 'Reversed'];
$statusClasses = ['draft' => ' uk-label-warning', 'confirmed' => ' uk-label-success', 'reversed' => ' uk-label-danger'];
$activeAllocations = array_values(array_filter($allocations, static fn ($allocation): bool => !$allocation->isReversed()));
$reversedAllocations = count($allocations) - count($activeAllocations);
$allocationTotalMinor = array_sum(array_map(
    static fn ($allocation): int => $allocation->isReversed() ? 0 : $allocation->amount->amountMinor(),
    $allocations,
));
$allocationCurrency = $activeAllocations !== []
    ? $activeAllocations[0]->amount->currencyCode()
    : $payment->amount->currencyCode();
$allocationTotal = number_format($allocationTotalMinor / 100, 2, '.', ',') . ' ' . $allocationCurrency;
$isFullyAllocated = $allocationTotalMinor === $payment->amount->amountMinor();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Payments · Cash receipt</p><h2><?= $e($payment->number ?? 'Draft payment') ?></h2><p>Review the receipt, customer allocation and accounting trail as one connected financial record.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>payments/"><i class="fa fa-arrow-left"></i> Back to Payments</a></div>
  </header>

  <div class="<?= $payment->isReversed() ? 'uk-alert-warning' : ($payment->isConfirmed() ? 'uk-alert-success' : 'uk-alert-primary') ?> uk-margin-medium-bottom" uk-alert>
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
      <div class="uk-width-expand@m"><h3 class="uk-h4"><i class="fa fa-<?= $payment->isReversed() ? 'undo' : ($payment->isConfirmed() ? 'check-circle' : 'clock-o') ?>"></i> <?= $payment->isReversed() ? 'Payment reversed' : ($payment->isConfirmed() ? 'Payment confirmed' : 'Payment awaiting confirmation') ?></h3><p><?= $payment->isReversed() ? 'The receipt and its allocations were reversed. The original record remains visible for audit.' : ($payment->isConfirmed() ? 'The receipt is confirmed and its active allocations are reflected in the connected invoice and ledger.' : 'This payment has not yet become a confirmed cash receipt.') ?></p></div>
      <div><span class="uk-label<?= $statusClasses[$payment->status] ?? '' ?>"><?= $e($statusLabels[$payment->status] ?? ucfirst($payment->status)) ?></span></div>
    </div>
  </div>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($money($payment->amount)) ?></strong><span class="kontor-stat__label">Receipt amount</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-calendar"></i></span><span><strong class="kontor-stat__value"><?= $e($payment->paymentDate?->format('M j, Y') ?? '—') ?></strong><span class="kontor-stat__label">Payment date</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-credit-card"></i></span><span><strong class="kontor-stat__value"><?= $e($methodLabels[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method))) ?></strong><span class="kontor-stat__label">Payment method</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $isFullyAllocated && !$payment->isReversed() ? ' kontor-stat__icon--success' : ' kontor-stat__icon--warning' ?>"><i class="fa fa-link"></i></span><span><strong class="kontor-stat__value"><?= $e($allocationTotal) ?></strong><span class="kontor-stat__label"><?= $isFullyAllocated ? 'Fully allocated' : 'Active allocation' ?></span></span></div></div>
  </div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Document settlement</p><h3 class="uk-card-title uk-margin-small-top">Invoice allocations</h3><p class="uk-text-muted uk-margin-small-top">Allocations explain which receivable this payment settled and where its accounting entry was posted.</p></div><div><span class="uk-label<?= $isFullyAllocated && !$payment->isReversed() ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e((string) count($activeAllocations)) ?> active<?= $reversedAllocations > 0 ? ' · ' . $e((string) $reversedAllocations) . ' reversed' : '' ?></span></div></div>

        <?php if ($allocations !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top">
          <?php foreach ($allocations as $allocation): ?>
            <?php
            $allocationUid = $allocation->uid->toString();
            $allocationEntries = $ledgerEntries[$allocationUid] ?? ['posting' => null, 'reversal' => null];
            $invoice = $invoiceViews[$allocation->documentUid] ?? null;
            ?>
            <li>
              <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
                <div class="uk-width-expand@m"><span class="uk-text-meta"><?= $allocation->documentType === 'invoice' ? 'Invoice' : ucfirst(str_replace('_', ' ', $allocation->documentType)) ?></span><h4 class="uk-margin-small-top uk-margin-remove-bottom"><?= $e($invoice['label'] ?? $invoiceLabels[$allocation->documentUid] ?? 'Connected document') ?></h4><div class="uk-text-meta uk-margin-small-top">Allocated <?= $e($allocation->allocatedAt->format('M j, Y · H:i')) ?><?php if ($allocation->isReversed()): ?> · Reversed <?= $e($allocation->reversedAt?->format('M j, Y · H:i') ?? '') ?><?php endif; ?></div></div>
                <div class="uk-text-right@m"><strong><?= $e($money($allocation->amount)) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $allocation->isReversed() ? ' uk-label-danger' : ' uk-label-success' ?>"><?= $allocation->isReversed() ? 'Reversed' : 'Active' ?></span></div></div>
              </div>
              <?php if ($invoice !== null): ?><div class="uk-grid-small uk-grid-divider uk-child-width-1-2@s uk-margin-top" uk-grid><div><span class="uk-text-meta">Invoice status</span><div class="uk-margin-small-top"><?= $e(ucfirst(str_replace('_', ' ', $invoice['status']))) ?></div></div><div><span class="uk-text-meta">Invoice balance</span><div class="uk-margin-small-top"><?= $e($money($invoice['due'])) ?> due of <?= $e($money($invoice['total'])) ?></div></div></div><?php endif; ?>
              <div class="uk-flex uk-flex-right uk-flex-wrap uk-grid-small uk-margin-top" uk-grid>
                <?php if ($invoice !== null && $invoice['route'] !== null): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl . $invoice['route']) ?>"><i class="fa fa-file-text"></i> Open invoice</a></div><?php endif; ?>
                <?php if ($ledgerReady && $allocationEntries['posting'] !== null): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($allocationEntries['posting']->uid->toString())) ?>"><i class="fa fa-book"></i> Ledger posting</a></div><?php endif; ?>
                <?php if ($ledgerReady && $allocationEntries['reversal'] !== null): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($allocationEntries['reversal']->uid->toString())) ?>"><i class="fa fa-undo"></i> Reversal posting</a></div><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-unlink fa-2x uk-text-muted"></i><h4>No document allocation</h4><p class="uk-text-muted">This payment is not currently connected to an invoice or another receivable.</p></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Receipt context</p><h3 class="uk-card-title uk-margin-small-top">Payment details</h3>
        <ul class="uk-list uk-list-divider uk-margin-medium-top">
          <li><span class="uk-text-meta">Payer</span><div class="uk-margin-small-top"><?php if ($payerRoute !== null): ?><a class="uk-link-reset" href="<?= $e($adminUrl . $payerRoute) ?>"><strong><?= $e($payerLabel) ?></strong></a><?php else: ?><strong><?= $e($payerLabel) ?></strong><?php endif; ?></div></li>
          <li><span class="uk-text-meta">Transaction reference</span><div class="uk-margin-small-top"><?= $e($payment->transactionReference ?? 'No reference provided') ?></div></li>
          <li><span class="uk-text-meta">Method</span><div class="uk-margin-small-top"><?= $e($methodLabels[$payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method))) ?></div></li>
          <li><span class="uk-text-meta">Payment date</span><div class="uk-margin-small-top"><?= $e($payment->paymentDate?->format('M j, Y') ?? 'Not set') ?></div></li>
        </ul>
        <details><summary>Technical reference</summary><div class="uk-text-meta uk-margin-small-top">Payment <?= $e($payment->uid->toString()) ?><?php if ($payment->externalId !== null): ?><br>External reference <?= $e($payment->externalId) ?><?php endif; ?></div></details>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Correction policy</p><h3 class="uk-card-title uk-margin-small-top">Audit trail</h3>
        <?php if ($payment->isReversed()): ?><p class="uk-text-muted">This payment has already been reversed. Its original amount, allocation and correction remain available for audit.</p>
        <?php elseif ($payment->isConfirmed()): ?><p class="uk-text-muted">Confirmed receipts cannot be silently edited or deleted. Reverse the payment only when the recorded receipt is genuinely incorrect.</p><?php if ($canReverse): ?><details class="uk-margin-top"><summary>Reverse this payment</summary><div class="uk-alert-danger uk-margin-top" uk-alert><p><i class="fa fa-exclamation-triangle"></i> This reverses every active allocation and its connected ledger posting. The action is recorded in the audit trail.</p></div><form method="post" action="<?= $e($adminUrl) ?>payment-action/" data-kontor-confirm="Reverse this confirmed payment and all active invoice and ledger allocations?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($payment->uid->toString()) ?>"><button class="uk-button uk-button-danger uk-width-1-1" name="action" value="reverse" type="submit"><i class="fa fa-undo"></i> Reverse payment</button></form></details><?php else: ?><div class="uk-alert-primary uk-margin-top" uk-alert><p><i class="fa fa-lock"></i> Your role can review this receipt but cannot reverse it.</p></div><?php endif; ?>
        <?php else: ?><p class="uk-text-muted">The payment is still a draft and has no confirmed financial effect.</p><?php endif; ?>
      </section>
    </div>
  </div>
</div>
