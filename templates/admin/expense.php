<?php

/** @var \Kontor\Expenses\Domain\Expense|null $expense */
/** @var array{categoryUid: string, supplierUid: string, description: string, amount: string, currencyCode: string, expenseDate: string, receiptFileUid: string} $values */
/** @var string $error */
/** @var \Kontor\Expenses\Domain\ExpenseCategory[] $categories */
/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var array<int, array<string, mixed>> $receiptFiles */
/** @var bool $canUseFiles */
/** @var bool $canManageCategories */
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
  <?php if ($expense === null): ?>
    <header class="pw-module-head kontor-pagehead">
      <div>
        <p class="kontor-eyebrow">Spend · Draft entry</p>
        <h2>Record expense</h2>
        <p>Capture what was purchased, when it happened and how much it cost. The draft can be reviewed before it enters approval.</p>
      </div>
      <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>expenses/"><i class="fa fa-arrow-left"></i> Back to expenses</a></div>
    </header>
  <?php else: ?>
    <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>expenses/"><i class="fa fa-arrow-left"></i> Back to expenses</a><p class="kontor-eyebrow">Expenses · Approval</p><h2><?= $e($expense->description) ?></h2></header>
  <?php endif; ?>
  <?php if ($error !== ''): ?><div class="uk-alert uk-alert-warning kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <?php if ($expense === null): ?>
    <?php if ($categories === []): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center">
        <i class="fa fa-tags fa-2x uk-text-muted"></i>
        <h3>Add an expense category first</h3>
        <p>Categories keep approvals and reporting consistent. Create at least one category, such as Travel, Software or Office supplies, before recording an expense.</p>
        <?php if ($canManageCategories): ?>
          <a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>expense-category/"><i class="fa fa-plus"></i> Create expense category</a>
        <?php else: ?>
          <p class="uk-text-meta">Ask an expense administrator to create the first category.</p>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <div class="uk-grid-medium" uk-grid>
        <div class="uk-width-1-1 uk-width-2-3@l">
          <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="./">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <?php if ($suppliers === []): ?><input type="hidden" name="supplier_uid" value=""><?php endif; ?>
            <?php if (!$canUseFiles): ?><input type="hidden" name="receipt_file_uid" value=""><?php endif; ?>

            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Expense details</p>
            <h3 class="uk-card-title uk-margin-small-top">What was purchased?</h3>
            <p class="uk-text-muted">Use enough detail for a reviewer to understand the business purpose without opening the receipt.</p>

            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="expense-category">Category <span aria-hidden="true">*</span></label>
                <select class="uk-select uk-margin-small-top" id="expense-category" name="category_uid" aria-describedby="expense-category-help" required>
                  <option value="">Choose category</option>
                  <?php foreach ($categories as $category): ?><option value="<?= $e($category->uid->toString()) ?>"<?= $values['categoryUid'] === $category->uid->toString() ? ' selected' : '' ?>><?= $e($category->name) ?></option><?php endforeach; ?>
                </select>
                <div class="uk-text-meta uk-margin-small-top" id="expense-category-help">Controls how this cost is reviewed and reported.</div>
              </div>
              <?php if ($suppliers !== []): ?>
                <div class="uk-width-1-1 uk-width-1-2@m">
                  <label class="uk-form-label" for="expense-supplier">Supplier</label>
                  <select class="uk-select uk-margin-small-top" id="expense-supplier" name="supplier_uid" aria-describedby="expense-supplier-help">
                    <option value="">No supplier selected</option>
                    <?php foreach ($suppliers as $supplier): ?><option value="<?= $e($supplier->uid->toString()) ?>"<?= $values['supplierUid'] === $supplier->uid->toString() ? ' selected' : '' ?>><?= $e($supplier->legalName) ?></option><?php endforeach; ?>
                  </select>
                  <div class="uk-text-meta uk-margin-small-top" id="expense-supplier-help">Optional. Connects this expense to an existing purchasing supplier.</div>
                </div>
              <?php endif; ?>
              <div class="uk-width-1-1">
                <label class="uk-form-label" for="expense-description">Business purpose <span aria-hidden="true">*</span></label>
                <input class="uk-input uk-margin-small-top" id="expense-description" name="description" value="<?= $e($values['description']) ?>" maxlength="500" placeholder="Team travel to customer workshop" aria-describedby="expense-description-help" required autofocus>
                <div class="uk-text-meta uk-margin-small-top" id="expense-description-help">Describe the purchase and why it was needed. Avoid card numbers or other sensitive payment data.</div>
              </div>
            </div>

            <hr>

            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Cost and evidence</p>
            <h3 class="uk-card-title uk-margin-small-top">When and how much?</h3>
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="expense-amount">Amount <span aria-hidden="true">*</span></label>
                <input class="uk-input uk-margin-small-top" id="expense-amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= $e($values['amount']) ?>" placeholder="0.00" aria-describedby="expense-amount-help" required>
                <div class="uk-text-meta uk-margin-small-top" id="expense-amount-help">Enter the total shown on the receipt, including applicable tax.</div>
              </div>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="expense-currency">Currency <span aria-hidden="true">*</span></label>
                <input class="uk-input uk-margin-small-top" id="expense-currency" name="currency_code" value="<?= $e($values['currencyCode']) ?>" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" autocomplete="off" aria-describedby="expense-currency-help" required>
                <div class="uk-text-meta uk-margin-small-top" id="expense-currency-help">Use the three-letter currency code, such as EUR, USD or GBP.</div>
              </div>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="expense-date">Purchase date <span aria-hidden="true">*</span></label>
                <input class="uk-input uk-margin-small-top" id="expense-date" name="expense_date" type="date" value="<?= $e($values['expenseDate']) ?>" aria-describedby="expense-date-help" required>
                <div class="uk-text-meta uk-margin-small-top" id="expense-date-help">Use the date printed on the receipt or invoice.</div>
              </div>
              <?php if ($canUseFiles): ?>
                <div class="uk-width-1-1 uk-width-1-2@m">
                  <label class="uk-form-label" for="expense-receipt">Receipt</label>
                  <select class="uk-select uk-margin-small-top" id="expense-receipt" name="receipt_file_uid" aria-describedby="expense-receipt-help">
                    <option value="">No receipt attached</option>
                    <?php foreach ($receiptFiles as $file): ?><option value="<?= $e((string) $file['uid']) ?>"<?= $values['receiptFileUid'] === (string) $file['uid'] ? ' selected' : '' ?>><?= $e((string) $file['original_name']) ?></option><?php endforeach; ?>
                  </select>
                  <div class="uk-text-meta uk-margin-small-top" id="expense-receipt-help"><?php if ($receiptFiles !== []): ?>Optional. Select an active file already stored in Kontor.<?php else: ?>No files are available yet. Add the receipt in Files, then return here to select it.<?php endif; ?></div>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($canUseFiles && $receiptFiles === []): ?><p class="uk-margin"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>files/"><i class="fa fa-paperclip"></i> Open Files</a></p><?php endif; ?>

            <hr>
            <div class="uk-grid-small uk-child-width-1-1 uk-child-width-auto@s uk-flex-right" uk-grid>
              <div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>expenses/">Cancel</a></div>
              <div><button class="uk-button uk-button-primary uk-width-1-1" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Save draft</button></div>
            </div>
          </form>
        </div>

        <aside class="uk-width-1-1 uk-width-1-3@l">
          <section class="uk-card uk-card-default uk-card-small uk-card-body">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Approval flow</p>
            <h3 class="uk-card-title uk-margin-small-top">A draft is safe to review</h3>
            <ul class="uk-list uk-list-divider">
              <li><strong>1. Save draft</strong><div class="uk-text-meta uk-margin-small-top">Capture the cost without sending it for approval.</div></li>
              <li><strong>2. Submit</strong><div class="uk-text-meta uk-margin-small-top">Send the completed expense to an approver.</div></li>
              <li><strong>3. Approve</strong><div class="uk-text-meta uk-margin-small-top">Confirm that the expense follows policy.</div></li>
              <li><strong>4. Reimburse</strong><div class="uk-text-meta uk-margin-small-top">Record payment and create the ledger posting when Ledger is installed.</div></li>
            </ul>
          </section>
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Before submitting</p>
            <h3 class="uk-h4 uk-margin-small-top">Make it easy to approve</h3>
            <p class="uk-text-muted uk-margin-remove-bottom">Check the purchase date, total and business purpose. Attach a receipt when one is available.</p>
          </section>
        </aside>
      </div>
    <?php endif; ?>
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
