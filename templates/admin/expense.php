<?php

/** @var \Kontor\Expenses\Domain\Expense|null $expense */
/** @var array{categoryUid: string, supplierUid: string, description: string, amount: string, currencyCode: string, expenseDate: string, receiptFileUid: string} $values */
/** @var string $error */
/** @var \Kontor\Expenses\Domain\ExpenseCategory[] $categories */
/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var string|null $configuredWorkflowState */
/** @var \Kontor\Workflow\Domain\HistoryEntry[] $configuredWorkflowHistory */
/** @var \Kontor\Ledger\Domain\LedgerEntry|null $ledgerEntry */
/** @var bool $canSubmit */
/** @var bool $canApprove */
/** @var bool $canReimburse */
/** @var bool $canCancel */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>expenses/"><i class="fa fa-arrow-left"></i> Back to expenses</a><p class="kontor-eyebrow">Expenses · Approval</p><h2><?= $e($expense?->description ?? 'Create expense') ?></h2></header>
  <?php if ($error !== ''): ?><div class="uk-alert uk-alert-warning kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <?php if ($expense === null): ?>
    <form class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-form-stacked kontor-nativeform" method="post" action="./">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Category *</span><select name="category_uid" aria-label="Category" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= $e($category->uid->toString()) ?>"<?= $values['categoryUid'] === $category->uid->toString() ? ' selected' : '' ?>><?= $e($category->code . ' · ' . $category->name) ?></option><?php endforeach; ?></select></label>
      <?php if ($suppliers !== []): ?><label class="kontor-nativefield"><span>Supplier</span><select name="supplier_uid" aria-label="Supplier"><option value="">No supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= $e($supplier->uid->toString()) ?>"><?= $e($supplier->code . ' · ' . $supplier->legalName) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="supplier_uid" value=""><?php endif; ?>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Description *</span><input name="description" value="<?= $e($values['description']) ?>" required></label>
      <label class="kontor-nativefield"><span>Amount *</span><input name="amount" type="number" min="0.01" step="0.01" value="<?= $e($values['amount']) ?>" required></label>
      <label class="kontor-nativefield"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" required></label>
      <label class="kontor-nativefield"><span>Expense date *</span><input name="expense_date" type="date" value="<?= $e($values['expenseDate']) ?>" required></label>
      <label class="kontor-nativefield"><span>Receipt file UID</span><input name="receipt_file_uid" value="<?= $e($values['receiptFileUid']) ?>" placeholder="Optional Files reference"></label>
      <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit" name="submit_save" value="1">Create draft</button><a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>expenses/">Cancel</a></div>
    </form>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <div class="kontor-detailgrid">
        <div><span>Status</span><strong><?= $e($expense->status) ?></strong></div>
        <div><span>Workflow state</span><strong><?= $e($configuredWorkflowState ?? 'Safe default') ?></strong></div>
        <div><span>Amount</span><strong><?= $e(number_format($expense->amount->amountMinor() / 100, 2, '.', '') . ' ' . $expense->amount->currencyCode()) ?></strong></div>
        <div><span>Date</span><strong><?= $e($expense->expenseDate->format('Y-m-d')) ?></strong></div>
        <div><span>Receipt</span><strong><?= $e($expense->receiptFileUid ?? '—') ?></strong></div>
        <?php if ($ledgerEntry !== null): ?><div><span>Ledger</span><strong><a href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($ledgerEntry->uid->toString())) ?>">Reimbursement posting</a></strong></div><?php endif; ?>
      </div>
      <?php if ($configuredWorkflowHistory !== []): ?>
        <details>
          <summary>Workflow history · <?= $e((string) count($configuredWorkflowHistory)) ?> transition(s)</summary>
          <ol>
            <?php foreach ($configuredWorkflowHistory as $entry): ?>
              <li><strong><?= $e($entry->actionKey) ?></strong> · <?= $e($entry->fromState) ?> → <?= $e($entry->toState) ?> · <?= $e($entry->occurredAt->format('Y-m-d H:i:s')) ?></li>
            <?php endforeach; ?>
          </ol>
        </details>
      <?php endif; ?>
      <?php if ($expense->rejectionReason !== null): ?><div class="uk-alert uk-alert-warning kontor-warning"><strong>Rejected:</strong> <?= $e($expense->rejectionReason) ?></div><?php endif; ?>
      <div class="pw-module-actions kontor-pagehead__actions">
        <?php if ($canSubmit): ?><form method="post" action="<?= $e($adminUrl) ?>expense-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($expense->uid->toString()) ?>"><input type="hidden" name="action" value="submit"><button class="uk-button uk-button-primary kontor-button" type="submit">Submit</button></form><?php endif; ?>
        <?php if ($canApprove): ?><form method="post" action="<?= $e($adminUrl) ?>expense-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($expense->uid->toString()) ?>"><input type="hidden" name="action" value="approve"><button class="uk-button uk-button-primary kontor-button" type="submit">Approve</button></form><form method="post" action="<?= $e($adminUrl) ?>expense-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($expense->uid->toString()) ?>"><input type="hidden" name="action" value="reject"><input name="reason" aria-label="Rejection reason" placeholder="Rejection reason" required><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Reject</button></form><?php endif; ?>
        <?php if ($canReimburse): ?><form method="post" action="<?= $e($adminUrl) ?>expense-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($expense->uid->toString()) ?>"><input type="hidden" name="action" value="reimburse"><button class="uk-button uk-button-primary kontor-button" type="submit">Mark reimbursed</button></form><?php endif; ?>
        <?php if ($canCancel): ?><form method="post" action="<?= $e($adminUrl) ?>expense-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($expense->uid->toString()) ?>"><input type="hidden" name="action" value="cancel"><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Cancel expense</button></form><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
