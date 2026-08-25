<?php

/** @var \Kontor\Expenses\Domain\Expense[] $expenses */
/** @var \Kontor\Expenses\Domain\ExpenseCategory[] $categories */
/** @var array<string, string> $categoryLabels */
/** @var string|null $selectedStatus */
/** @var bool $canCreateExpense */
/** @var bool $canManageCategories */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Spend</p><h2>Expenses</h2><p>Capture costs and move them through submission, approval, and reimbursement.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <?php if ($canManageCategories): ?><a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>expense-category/"><i class="fa fa-tags"></i> New category</a><?php endif; ?>
      <?php if ($canCreateExpense): ?><a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>expense/"><i class="fa fa-plus"></i> New expense</a><?php endif; ?>
    </div>
  </header>
  <nav aria-label="Expense statuses">
    <ul class="uk-subnav uk-subnav-pill uk-flex-wrap uk-margin-remove">
      <li<?= $selectedStatus === null ? ' class="uk-active"' : '' ?>><a href="./"<?= $selectedStatus === null ? ' aria-current="page"' : '' ?>>All</a></li>
      <?php foreach (['draft', 'submitted', 'approved', 'rejected', 'reimbursed', 'cancelled'] as $status): ?>
        <li<?= $selectedStatus === $status ? ' class="uk-active"' : '' ?>><a href="./?status=<?= $e($status) ?>"<?= $selectedStatus === $status ? ' aria-current="page"' : '' ?>><?= $e(ucfirst($status)) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <?php if ($expenses !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Date</th><th>Expense</th><th>Category</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($expenses as $expense): ?><tr>
          <td><?= $e($expense->expenseDate->format('Y-m-d')) ?></td>
          <td><strong><a href="<?= $e($adminUrl) ?>expense/?id=<?= $e(rawurlencode($expense->uid->toString())) ?>"><?= $e($expense->description) ?></a></strong></td>
          <td><?= $e($categoryLabels[$expense->categoryUid] ?? $expense->categoryUid) ?></td>
          <td><?= $e($money($expense->amount)) ?></td>
          <td><span class="uk-label kontor-pill<?= in_array($expense->status, ['approved', 'reimbursed'], true) ? '' : ' kontor-pill--inactive' ?>"><?= $e($expense->status) ?></span></td>
        </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-credit-card"></i><h3>No expenses</h3><p>Create a draft or select another status.</p></div><?php endif; ?>
  </section>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Classification</p><h3>Categories</h3></div></header>
    <?php if ($categories !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Code</th><th>Category</th><th>Status</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><strong><?= $e($category->code) ?></strong></td><td><?= $e($category->name) ?></td><td><?= $e($category->status) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>Create the first expense category.</p></div><?php endif; ?>
  </section>
</div>
