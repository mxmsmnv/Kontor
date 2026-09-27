<?php

/** @var array<int, array{account: \Kontor\Ledger\Domain\Account, balance: \Kontor\SDK\ValueObjects\Money}> $accountRows */
/** @var \Kontor\Ledger\Domain\Account[] $activeAccounts */
/** @var array<int, array{entry: \Kontor\Ledger\Domain\LedgerEntry, amount: \Kontor\SDK\ValueObjects\Money}> $entryRows */
/** @var \Kontor\Ledger\Domain\LedgerEntry|null $selected */
/** @var \Kontor\Ledger\Domain\LedgerLine[] $selectedLines */
/** @var array<string, string> $accountLabels */
/** @var array<string, array{label: string, url: string}> $referenceLinks */
/** @var array<string, string> $recordedByLabels */
/** @var array<int, array{label: string, url: string, icon: string}> $financeLinks */
/** @var string $defaultCurrency */
/** @var bool $canManageAccounts */
/** @var bool $canRecordEntries */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$typeLabels = [
    'asset' => 'Asset',
    'liability' => 'Liability',
    'equity' => 'Equity',
    'revenue' => 'Revenue',
    'expense' => 'Expense',
];
$activeCount = count($activeAccounts);
$archivedCount = count($accountRows) - $activeCount;
$nonZeroCount = count(array_filter(
    $accountRows,
    static fn (array $row): bool => !$row['balance']->isZero(),
));
$currencies = array_values(array_unique(array_map(
    static fn (array $row): string => $row['account']->currencyCode,
    $accountRows,
)));
$latestEntry = null;
foreach ($entryRows as $row) {
    if ($latestEntry === null || $row['entry']->createdAt > $latestEntry->createdAt) {
        $latestEntry = $row['entry'];
    }
}
$selectedUid = $selected?->uid->toString();
$selectedAmount = null;
foreach ($entryRows as $row) {
    if ($selectedUid !== null && $row['entry']->uid->toString() === $selectedUid) {
        $selectedAmount = $row['amount'];
        break;
    }
}
$selectedSourceLabel = $selected?->referenceType !== null
    ? ucfirst(str_replace('_', ' ', $selected->referenceType))
    : 'Manual posting';
$nearbyEntries = $selectedUid === null ? [] : array_slice(array_values(array_filter(
    $entryRows,
    static fn (array $row): bool => $row['entry']->uid->toString() !== $selectedUid,
)), 0, 4);
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow"><?= $selected !== null ? 'Ledger · Posted entry' : 'Finance · Accounting' ?></p>
      <h2><?= $selected !== null ? 'Journal entry' : 'Ledger' ?></h2>
      <p><?= $selected !== null ? $e($selected->description) : 'Review account balances and the permanent history created by invoices, payments, expenses and manual postings.' ?></p>
    </div>
    <?php if ($selected !== null): ?>
      <div class="pw-module-actions kontor-pagehead__actions">
        <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-arrow-left"></i> Back to Ledger</a>
        <?php if (isset($referenceLinks[$selectedUid])): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl . $referenceLinks[$selectedUid]['url']) ?>"><i class="fa fa-external-link"></i> <?= $e($referenceLinks[$selectedUid]['label']) ?></a><?php endif; ?>
      </div>
    <?php elseif ($financeLinks !== []): ?>
      <div class="pw-module-actions kontor-pagehead__actions">
        <?php foreach ($financeLinks as $link): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl . $link['url']) ?>"><i class="fa fa-<?= $e($link['icon']) ?>"></i> <?= $e($link['label']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </header>

  <?php if ($selected === null): ?>
  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $activeCount >= 2 ? ' kontor-stat__icon--success' : ' kontor-stat__icon--warning' ?>"><i class="fa fa-book"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $activeCount) ?></strong><span class="kontor-stat__label">Active accounts<?= $archivedCount > 0 ? ' · ' . $e((string) $archivedCount) . ' archived' : '' ?></span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $nonZeroCount) ?></strong><span class="kontor-stat__label">Accounts with a balance</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-exchange"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($entryRows)) ?></strong><span class="kontor-stat__label">Posted journal entries</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $latestEntry !== null ? $e($latestEntry->entryDate->format('M j')) : '—' ?></strong><span class="kontor-stat__label"><?= $latestEntry !== null ? 'Latest accounting date' : 'No postings yet' ?></span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
    <h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3>
    <p>Every posting moves the same amount between a debit and a credit account. Posted entries are permanent: correct a mistake with a reversing entry so the audit trail remains complete.</p>
  </div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-3-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
          <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Live balances</p><h3 class="uk-card-title uk-margin-small-top">Chart of accounts</h3><p class="uk-text-muted uk-margin-small-top">Accounts organize financial activity by purpose. Balances update automatically whenever a journal entry is posted.</p></div>
          <div><?php foreach ($currencies as $currency): ?><span class="uk-label uk-margin-small-left"><?= $e($currency) ?></span><?php endforeach; ?></div>
        </div>

        <?php if ($canManageAccounts): ?>
          <details<?= $accountRows === [] ? ' open' : '' ?> class="uk-margin-medium-top">
            <summary class="uk-button uk-button-primary"><i class="fa fa-plus"></i> Add account</summary>
            <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>ledger-account-create/">
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
              <div class="uk-grid-small" uk-grid>
                <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="ledger-account-code">Account code</label><input class="uk-input uk-margin-small-top" id="ledger-account-code" name="code" maxlength="20" pattern="[A-Za-z0-9._-]{1,20}" placeholder="1000" aria-describedby="ledger-account-code-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-account-code-help">Use your accounting plan's stable code.</div></div>
                <div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="ledger-account-name">Account name</label><input class="uk-input uk-margin-small-top" id="ledger-account-name" name="name" maxlength="255" placeholder="Cash" aria-describedby="ledger-account-name-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-account-name-help">Describe what the account holds or measures.</div></div>
                <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="ledger-account-type">Account type</label><select class="uk-select uk-margin-small-top" id="ledger-account-type" name="type" aria-describedby="ledger-account-type-help" required><?php foreach ($typeLabels as $type => $label): ?><option value="<?= $e($type) ?>"><?= $e($label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top" id="ledger-account-type-help">The type determines whether debit or credit normally increases the balance.</div></div>
                <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="ledger-account-currency">Currency</label><input class="uk-input uk-margin-small-top" id="ledger-account-currency" name="currency_code" value="<?= $e($defaultCurrency) ?>" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" aria-describedby="ledger-account-currency-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-account-currency-help">Use a three-letter currency code such as EUR or USD.</div></div>
                <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="ledger-account-parent">Parent account</label><select class="uk-select uk-margin-small-top" id="ledger-account-parent" name="parent_uid" aria-describedby="ledger-account-parent-help"><option value="">No parent account</option><?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>"><?= $e($account->code . ' · ' . $account->name) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top" id="ledger-account-parent-help">Optional. Use a parent to group related accounts in the chart.</div></div>
              </div>
              <div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Create account</button></div>
            </form>
          </details>
        <?php endif; ?>

        <?php if ($accountRows !== []): ?>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($accountRows as $row): ?>
              <?php $account = $row['account']; ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
                  <div class="uk-width-expand@m"><strong><?= $e($account->code) ?> · <?= $e($account->name) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($typeLabels[$account->type] ?? ucfirst($account->type)) ?> · <?= $e($account->currencyCode) ?><?php if ($account->parentUid !== null): ?> · Under <?= $e($accountLabels[$account->parentUid] ?? 'parent account') ?><?php endif; ?></div></div>
                  <div class="uk-text-right@m"><strong><?= $e($money($row['balance'])) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $account->isActive() ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $account->isActive() ? 'Active' : 'Archived' ?></span></div></div>
                </div>
                <details class="uk-margin-small-top"><summary>Account details</summary><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-small-top" uk-grid><div class="uk-text-meta">A <?= $e(strtolower($typeLabels[$account->type] ?? $account->type)) ?> account normally increases with a <?= $account->isDebitNormal() ? 'debit' : 'credit' ?>.</div><?php if ($canManageAccounts): ?><div><form method="post" action="<?= $e($adminUrl) ?>ledger-account-action/" data-kontor-confirm="<?= $account->isArchived() ? 'Restore this account for new postings?' : 'Archive this account? Existing journal history and balances will remain.' ?>"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="account_uid" value="<?= $e($account->uid->toString()) ?>"><input type="hidden" name="action" value="<?= $account->isArchived() ? 'restore' : 'archive' ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><i class="fa fa-<?= $account->isArchived() ? 'undo' : 'archive' ?>"></i> <?= $account->isArchived() ? 'Restore account' : 'Archive account' ?></button></form></div><?php endif; ?></div></details>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-book fa-2x uk-text-muted"></i><h4>No accounts yet</h4><p class="uk-text-muted">Create at least two accounts in the same currency before recording a journal entry.</p></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-2-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Manual posting</p>
        <h3 class="uk-card-title uk-margin-small-top">Record a journal entry</h3>
        <p class="uk-text-muted">Use this for adjustments and activity that did not originate in another Kontor component.</p>
        <?php if ($canRecordEntries && count($activeAccounts) >= 2): ?>
          <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>ledger-entry-record/" data-kontor-ledger-entry-form>
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <div class="uk-margin"><label class="uk-form-label" for="ledger-entry-description">Description</label><input class="uk-input uk-margin-small-top" id="ledger-entry-description" name="description" maxlength="500" placeholder="Monthly bank fee" aria-describedby="ledger-entry-description-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-entry-description-help">Explain the business reason so another reviewer can understand the posting later.</div></div>
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1 uk-width-1-2@s"><label class="uk-form-label" for="ledger-entry-date">Accounting date</label><input class="uk-input uk-margin-small-top" id="ledger-entry-date" type="date" name="entry_date" value="<?= $e(date('Y-m-d')) ?>" aria-describedby="ledger-entry-date-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-entry-date-help">The date this activity belongs in the books.</div></div>
              <div class="uk-width-1-1 uk-width-1-2@s"><label class="uk-form-label" for="ledger-entry-amount">Amount</label><input class="uk-input uk-margin-small-top" id="ledger-entry-amount" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="125.00" aria-describedby="ledger-entry-amount-help" required><div class="uk-text-meta uk-margin-small-top" id="ledger-entry-amount-help">The selected accounts must use the same currency.</div></div>
            </div>
            <div class="uk-margin"><label class="uk-form-label" for="ledger-entry-debit">Debit account</label><select class="uk-select uk-margin-small-top" id="ledger-entry-debit" name="debit_account_uid" data-kontor-ledger-debit aria-describedby="ledger-entry-debit-help" required><option value="" selected disabled>Choose the account receiving the debit</option><?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>" data-currency="<?= $e($account->currencyCode) ?>"><?= $e($account->code . ' · ' . $account->name . ' · ' . $account->currencyCode) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top" id="ledger-entry-debit-help">Assets and expenses commonly increase on the debit side.</div></div>
            <div class="uk-margin"><label class="uk-form-label" for="ledger-entry-credit">Credit account</label><select class="uk-select uk-margin-small-top" id="ledger-entry-credit" name="credit_account_uid" data-kontor-ledger-credit aria-describedby="ledger-entry-credit-help" required><option value="" selected disabled>Choose the account receiving the credit</option><?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>" data-currency="<?= $e($account->currencyCode) ?>"><?= $e($account->code . ' · ' . $account->name . ' · ' . $account->currencyCode) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top" id="ledger-entry-credit-help">Liabilities, equity and revenue commonly increase on the credit side.</div></div>

            <details class="uk-margin-medium-top"><summary>Connect a source record</summary><div class="uk-grid-small uk-margin-top" uk-grid><div class="uk-width-1-1 uk-width-1-2@s"><label class="uk-form-label" for="ledger-reference-type">Source type</label><input class="uk-input uk-margin-small-top" id="ledger-reference-type" name="reference_type" maxlength="50" placeholder="adjustment"><div class="uk-text-meta uk-margin-small-top">Optional technical category used to match this entry to another record.</div></div><div class="uk-width-1-1 uk-width-1-2@s"><label class="uk-form-label" for="ledger-reference-uid">Source identifier</label><input class="uk-input uk-margin-small-top" id="ledger-reference-uid" name="reference_uid" minlength="26" maxlength="26" pattern="[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}" placeholder="26-character ID"><div class="uk-text-meta uk-margin-small-top">Provide both source fields together, or leave both blank.</div></div></div></details>

            <div class="uk-alert-warning uk-margin-medium-top" uk-alert><p><i class="fa fa-lock"></i> Review both sides carefully. The entry becomes permanent after posting.</p></div>
            <div class="uk-flex uk-flex-right"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-check"></i> Post balanced entry</button></div>
          </form>
        <?php elseif (!$canRecordEntries): ?><div class="uk-alert-primary uk-margin-top" uk-alert><p><i class="fa fa-lock"></i> Your role can review the ledger but cannot post manual entries.</p></div><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-top"><i class="fa fa-exchange fa-2x uk-text-muted"></i><h4>Two active accounts required</h4><p class="uk-text-muted">Add two accounts in the same currency before recording a posting.</p></div><?php endif; ?>
      </section>
    </div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Permanent history</p><h3 class="uk-card-title uk-margin-small-top">Journal</h3><p class="uk-text-muted uk-margin-small-top">Open an entry to review both sides and follow it back to the source record when that component is available.</p></div><div><span class="uk-label"><?= $e((string) count($entryRows)) ?> entries</span></div></div>
    <?php if ($entryRows !== []): ?>
      <ul class="uk-list uk-list-divider uk-margin-medium-top">
        <?php foreach ($entryRows as $row): ?>
          <?php $entry = $row['entry']; $entryUid = $entry->uid->toString(); ?>
          <li>
            <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
              <div class="uk-width-expand@m"><a class="uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($entryUid)) ?>"><strong><?= $e($entry->description) ?></strong></a><div class="uk-text-meta uk-margin-small-top"><?= $e($entry->entryDate->format('M j, Y')) ?> · Recorded by <?= $e($recordedByLabels[$entryUid] ?? 'System') ?></div></div>
              <div class="uk-text-right@m"><strong><?= $e($money($row['amount'])) ?></strong><div class="uk-margin-small-top"><span class="uk-label uk-label-success">Balanced</span></div></div>
            </div>
            <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-small-top" uk-grid><div class="uk-text-meta"><?= $entry->referenceType !== null ? $e(ucfirst(str_replace('_', ' ', $entry->referenceType))) : 'Manual entry' ?></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($entryUid)) ?>"><i class="fa fa-eye"></i> Review entry</a><?php if (isset($referenceLinks[$entryUid])): ?> <a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl . $referenceLinks[$entryUid]['url']) ?>"><?= $e($referenceLinks[$entryUid]['label']) ?></a><?php endif; ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-list-alt fa-2x uk-text-muted"></i><h4>No journal entries</h4><p class="uk-text-muted">Entries will appear when connected finance workflows or an authorized teammate posts activity.</p></div><?php endif; ?>
  </section>
  <?php else: ?>
    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $selectedAmount !== null ? $e($money($selectedAmount)) : '—' ?></strong><span class="kontor-stat__label">Posted amount</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-calendar"></i></span><span><strong class="kontor-stat__value"><?= $e($selected->entryDate->format('M j, Y')) ?></strong><span class="kontor-stat__label">Accounting date</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-user"></i></span><span><strong class="kontor-stat__value"><?= $e($recordedByLabels[$selectedUid] ?? 'System') ?></strong><span class="kontor-stat__label">Recorded by</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-check"></i></span><span><strong class="kontor-stat__value">Balanced</strong><span class="kontor-stat__label">Debits equal credits</span></span></div></div>
    </div>

    <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
      <h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this posting</h3>
      <p>This is the permanent accounting record for <strong><?= $e($selected->description) ?></strong>. The accounting date determines the reporting period; the recorded time shows when the entry was added to Kontor.</p>
    </div>

    <div class="uk-grid-medium" uk-grid data-testid="ledger-entry-detail">
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
            <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Double-entry posting</p><h3 class="uk-card-title uk-margin-small-top">Debit and credit lines</h3><p class="uk-text-muted uk-margin-small-top">The same amount is recorded on both sides, keeping the journal balanced.</p></div>
            <div><span class="uk-label uk-label-success">Verified balance</span></div>
          </div>

          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($selectedLines as $line): ?>
              <?php $isDebit = !$line->debit->isZero(); ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
                  <div class="uk-width-expand@m"><span class="uk-label<?= $isDebit ? '' : ' uk-label-warning' ?>"><?= $isDebit ? 'Debit' : 'Credit' ?></span><h4 class="uk-margin-small-top uk-margin-remove-bottom"><?= $e($accountLabels[$line->accountUid] ?? 'Unavailable account') ?></h4></div>
                  <div class="uk-text-right@m"><span class="uk-text-meta"><?= $isDebit ? 'Debit amount' : 'Credit amount' ?></span><br><strong><?= $e($money($isDebit ? $line->debit : $line->credit)) ?></strong></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="uk-grid-small uk-grid-divider uk-child-width-1-2@s uk-margin-medium-top" uk-grid>
            <div><span class="uk-text-meta">Total debits</span><div class="uk-h4 uk-margin-small-top"><?= $selectedAmount !== null ? $e($money($selectedAmount)) : '—' ?></div></div>
            <div><span class="uk-text-meta">Total credits</span><div class="uk-h4 uk-margin-small-top"><?= $selectedAmount !== null ? $e($money($selectedAmount)) : '—' ?></div></div>
          </div>
        </section>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Entry context</p>
          <h3 class="uk-card-title uk-margin-small-top">Posting details</h3>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <li><span class="uk-text-meta">Accounting date</span><div class="uk-margin-small-top"><?= $e($selected->entryDate->format('M j, Y')) ?></div></li>
            <li><span class="uk-text-meta">Recorded</span><div class="uk-margin-small-top"><?= $e($selected->createdAt->format('M j, Y · H:i')) ?></div></li>
            <li><span class="uk-text-meta">Recorded by</span><div class="uk-margin-small-top"><?= $e($recordedByLabels[$selectedUid] ?? 'System') ?></div></li>
            <li><span class="uk-text-meta">Source</span><div class="uk-margin-small-top"><?= $e($selectedSourceLabel) ?></div></li>
          </ul>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Corrections</p>
          <h3 class="uk-card-title uk-margin-small-top">Permanent record</h3>
          <p class="uk-text-muted">Posted entries cannot be edited or deleted. If the accounting treatment is wrong, create an equal reversing entry and then post the correction.</p>
          <div class="uk-alert-warning uk-margin-top" uk-alert><p><i class="fa fa-lock"></i> Never alter the source record to hide an accounting correction.</p></div>
          <details><summary>Technical reference</summary><div class="uk-text-meta uk-margin-small-top">Entry <?= $e($selectedUid) ?><?php if ($selected->referenceType !== null && $selected->referenceUid !== null): ?><br>Source <?= $e($selected->referenceType) ?> · <?= $e($selected->referenceUid) ?><?php endif; ?></div></details>
        </section>
      </div>
    </div>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Journal context</p><h3 class="uk-card-title uk-margin-small-top">Nearby entries</h3><p class="uk-text-muted uk-margin-small-top">Review adjacent postings without losing your place in the journal.</p></div><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-list"></i> Full journal</a></div></div>
      <?php if ($nearbyEntries !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($nearbyEntries as $row): ?><?php $entry = $row['entry']; $entryUid = $entry->uid->toString(); ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($entry->description) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($entry->entryDate->format('M j, Y')) ?> · <?= $e($recordedByLabels[$entryUid] ?? 'System') ?></div></div><div class="uk-flex uk-flex-middle uk-grid-small" uk-grid><div><strong><?= $e($money($row['amount'])) ?></strong></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($entryUid)) ?>"><i class="fa fa-eye"></i> Review</a></div></div></div></li><?php endforeach; ?></ul><?php else: ?><p class="uk-text-muted uk-margin-top">This is the only journal entry currently available.</p><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
