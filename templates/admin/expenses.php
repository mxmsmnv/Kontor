<?php

/** @var \Kontor\Expenses\Domain\Expense[] $expenses */
/** @var \Kontor\Expenses\Domain\Expense[] $allExpenses */
/** @var \Kontor\Expenses\Domain\ExpenseCategory[] $categories */
/** @var array<string, string> $categoryLabels */
/** @var string|null $selectedStatus */
/** @var string $selectedCategory */
/** @var string $query */
/** @var bool $canCreateExpense */
/** @var bool $canViewCategories */
/** @var bool $canManageCategories */
/** @var string $adminUrl */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$sumByCurrency = static function (array $items): string {
    $totals = [];
    foreach ($items as $expense) {
        $currency = $expense->amount->currencyCode();
        $totals[$currency] = ($totals[$currency] ?? 0) + $expense->amount->amountMinor();
    }
    if ($totals === []) {
        return '0';
    }

    return implode(' · ', array_map(
        static fn (string $currency, int $amount): string => number_format($amount / 100, 2, '.', ',') . ' ' . $currency,
        array_keys($totals),
        array_values($totals),
    ));
};
$statuses = [
    'draft' => 'Draft',
    'submitted' => 'Submitted',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'reimbursed' => 'Reimbursed',
    'cancelled' => 'Cancelled',
];
$statusCounts = array_fill_keys(array_keys($statuses), 0);
$categoryCounts = array_fill_keys(array_keys($categoryLabels), 0);
foreach ($allExpenses as $item) {
    if (isset($statusCounts[$item->status])) {
        $statusCounts[$item->status]++;
    }
    if (isset($categoryCounts[$item->categoryUid])) {
        $categoryCounts[$item->categoryUid]++;
    }
}
$activeCategories = array_values(array_filter(
    $categories,
    static fn ($category): bool => $category->isActive(),
));
$submitted = array_values(array_filter($allExpenses, static fn ($item): bool => $item->status === 'submitted'));
$approved = array_values(array_filter($allExpenses, static fn ($item): bool => $item->status === 'approved'));
$reimbursed = array_values(array_filter($allExpenses, static fn ($item): bool => $item->status === 'reimbursed'));
$hasFilters = $selectedStatus !== null || $selectedCategory !== '' || $query !== '';
$filterUrl = static function (?string $status) use ($selectedCategory, $query): string {
    $parameters = [];
    if ($status !== null) {
        $parameters['status'] = $status;
    }
    if ($selectedCategory !== '') {
        $parameters['category'] = $selectedCategory;
    }
    if ($query !== '') {
        $parameters['q'] = $query;
    }

    return './' . ($parameters !== [] ? '?' . http_build_query($parameters) : '');
};
$statusClass = static fn (string $status): string => match ($status) {
    'approved', 'reimbursed' => ' uk-label-success',
    'rejected', 'cancelled' => ' uk-label-warning',
    default => '',
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Operations · Spend</p>
      <h2>Expenses</h2>
      <p>Capture business costs, review what needs attention and move approved expenses through reimbursement.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <?php if ($activeCategories !== []): ?>
        <?php if ($canManageCategories): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>expense-category/"><i class="fa fa-tags"></i> New category</a><?php endif; ?>
        <?php if ($canCreateExpense): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>expense/"><i class="fa fa-plus"></i> Record expense</a><?php endif; ?>
      <?php elseif ($allExpenses !== [] && $canManageCategories): ?>
        <a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>expense-category/"><i class="fa fa-plus"></i> Create first category</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($canViewCategories && $allExpenses === [] && $activeCategories === []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body">
      <div class="uk-grid-medium uk-flex-middle" uk-grid>
        <div class="uk-width-1-1 uk-width-2-5@m">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Expense setup</p>
          <h3 class="uk-card-title uk-margin-small-top">Build a consistent approval flow</h3>
          <p class="uk-text-muted">Start with the categories your team uses for reporting. Once one category is active, people can record draft expenses here.</p>
          <?php if ($canManageCategories): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>expense-category/"><i class="fa fa-plus"></i> Create first category</a><?php else: ?><p class="uk-text-meta">Ask an expense administrator to create the first category.</p><?php endif; ?>
        </div>
        <div class="uk-width-1-1 uk-width-3-5@m">
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s" uk-grid>
            <div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">1 · Classify</p><h4 class="uk-margin-small-top">Create categories</h4><p class="uk-text-muted uk-margin-remove-bottom">Use business labels such as Travel, Software or Office supplies.</p></div></div>
            <div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">2 · Capture</p><h4 class="uk-margin-small-top">Record drafts</h4><p class="uk-text-muted uk-margin-remove-bottom">Add the purpose, amount, date and receipt before submission.</p></div></div>
            <div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">3 · Review</p><h4 class="uk-margin-small-top">Approve and pay</h4><p class="uk-text-muted uk-margin-remove-bottom">Track decisions and reimburse approved business costs.</p></div></div>
          </div>
        </div>
      </div>
    </section>
  <?php else: ?>
    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-pencil"></i></span><span><strong class="kontor-stat__value"><?= $e((string) ($statusCounts['draft'] ?? 0)) ?></strong><span class="kontor-stat__label">Drafts</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($submitted)) ?></strong><span class="kontor-stat__label">Awaiting approval</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-check"></i></span><span><strong class="kontor-stat__value"><?= $e($sumByCurrency($approved)) ?></strong><span class="kontor-stat__label">Ready to reimburse</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($sumByCurrency($reimbursed)) ?></strong><span class="kontor-stat__label">Reimbursed</span></span></div></div>
    </div>

    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1<?= $canViewCategories ? ' uk-width-3-4@l' : '' ?>">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Expense queue</p>
          <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Find the work that needs attention</h3>
          <ul class="uk-subnav uk-subnav-pill uk-flex-wrap uk-margin">
            <li<?= $selectedStatus === null ? ' class="uk-active"' : '' ?>><a href="<?= $e($filterUrl(null)) ?>"<?= $selectedStatus === null ? ' aria-current="page"' : '' ?>>All <span class="uk-badge"><?= $e((string) count($allExpenses)) ?></span></a></li>
            <?php foreach ($statuses as $status => $label): ?><li<?= $selectedStatus === $status ? ' class="uk-active"' : '' ?>><a href="<?= $e($filterUrl($status)) ?>"<?= $selectedStatus === $status ? ' aria-current="page"' : '' ?>><?= $e($label) ?> <span class="uk-badge"><?= $e((string) ($statusCounts[$status] ?? 0)) ?></span></a></li><?php endforeach; ?>
          </ul>
          <form class="uk-form-stacked" method="get" action="./">
            <?php if ($selectedStatus !== null): ?><input type="hidden" name="status" value="<?= $e($selectedStatus) ?>"><?php endif; ?>
            <div class="uk-grid-small uk-flex-bottom" uk-grid>
              <div class="uk-width-1-1 uk-width-expand@m">
                <label class="uk-form-label" for="expense-search">Search expenses</label>
                <div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon" uk-icon="icon: search"></span><input class="uk-input" id="expense-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="<?= $canViewCategories ? 'Search purpose or category' : 'Search purpose' ?>"></div>
                <div class="uk-text-meta uk-margin-small-top">Search narrows the current queue without changing expense data.</div>
              </div>
              <?php if ($categories !== []): ?><div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="expense-category-filter">Category</label><select class="uk-select uk-margin-small-top" id="expense-category-filter" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= $e($category->uid->toString()) ?>"<?= $selectedCategory === $category->uid->toString() ? ' selected' : '' ?>><?= $e($category->name) ?></option><?php endforeach; ?></select></div><?php endif; ?>
              <div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-default uk-width-1-1" type="submit">Apply</button></div>
              <?php if ($hasFilters): ?><div class="uk-width-1-1 uk-width-auto@m"><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="./">Reset</a></div><?php endif; ?>
            </div>
          </form>
        </section>

        <?php if ($expenses !== []): ?>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
            <?php foreach ($expenses as $expense): ?>
              <div><article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
                <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><p class="uk-text-meta uk-margin-remove-bottom"><?= $e($expense->expenseDate->format('M j, Y')) ?></p><h3 class="uk-h4 uk-margin-small-top uk-margin-remove-bottom"><?= $e($expense->description) ?></h3></div><div><span class="uk-label<?= $statusClass($expense->status) ?>"><?= $e($statuses[$expense->status] ?? ucfirst($expense->status)) ?></span></div></div>
                <div class="uk-grid-small uk-child-width-1-2 uk-margin" uk-grid><div><div class="uk-text-meta">Category</div><strong><?= $e($categoryLabels[$expense->categoryUid] ?? ($canViewCategories ? 'Category unavailable' : 'Restricted category')) ?></strong></div><div><div class="uk-text-meta">Amount</div><strong><?= $e($money($expense->amount)) ?></strong></div></div>
                <a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>expense/?id=<?= $e(rawurlencode($expense->uid->toString())) ?>">Open expense</a>
              </article></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center">
            <i class="fa fa-search fa-2x uk-text-muted"></i>
            <h3><?= $hasFilters ? 'No expenses match these filters' : 'No expenses recorded yet' ?></h3>
            <p><?= $hasFilters ? 'Adjust the search, category or status to see more results.' : 'Record the first draft when a business cost is ready to review.' ?></p>
            <?php if ($hasFilters): ?><a class="uk-button uk-button-default uk-link-reset" href="./">Reset filters</a><?php elseif ($canCreateExpense && $activeCategories !== []): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>expense/"><i class="fa fa-plus"></i> Record first expense</a><?php endif; ?>
          </section>
        <?php endif; ?>
      </div>

      <?php if ($canViewCategories): ?><aside class="uk-width-1-1 uk-width-1-4@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Classification</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Categories</h3></div><?php if ($canManageCategories): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>expense-category/" aria-label="Create expense category"><i class="fa fa-plus"></i></a></div><?php endif; ?></div>
          <?php if ($categories !== []): ?><ul class="uk-list uk-list-divider uk-margin"><?php foreach ($categories as $category): ?><li><div class="uk-flex uk-flex-between uk-flex-middle"><span><strong><?= $e($category->name) ?></strong><?php if (!$category->isActive()): ?><span class="uk-label uk-margin-small-left">Inactive</span><?php endif; ?></span><span class="uk-badge"><?= $e((string) ($categoryCounts[$category->uid->toString()] ?? 0)) ?></span></div></li><?php endforeach; ?></ul><p class="uk-text-meta uk-margin-remove-bottom">Counts include expenses in every workflow status.</p><?php else: ?><div class="uk-text-center uk-padding-small"><i class="fa fa-tags fa-2x uk-text-muted"></i><p class="uk-text-muted">Create the first category before recording expenses.</p></div><?php endif; ?>
        </section>
      </aside><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
