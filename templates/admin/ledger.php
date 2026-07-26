<?php

/** @var array<int, array{account: \Kontor\Ledger\Domain\Account, balance: \Kontor\SDK\ValueObjects\Money}> $accountRows */
/** @var \Kontor\Ledger\Domain\Account[] $activeAccounts */
/** @var array<int, array{entry: \Kontor\Ledger\Domain\LedgerEntry, amount: \Kontor\SDK\ValueObjects\Money}> $entryRows */
/** @var \Kontor\Ledger\Domain\LedgerEntry|null $selected */
/** @var \Kontor\Ledger\Domain\LedgerLine[] $selectedLines */
/** @var array<string, string> $accountLabels */
/** @var string $defaultCurrency */
/** @var bool $canManageAccounts */
/** @var bool $canRecordEntries */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Finance · Double entry</p>
      <h2>Ledger</h2>
      <p>Chart of accounts, balanced journal entries, immutable posting history, and live account balances.</p>
    </div>
  </header>

  <?php if ($canManageAccounts): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Chart setup</p><h3>Create account</h3></div></header>
      <form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>ledger-account-create/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <label class="kontor-nativefield"><span>Code *</span><input name="code" maxlength="20" placeholder="1000" required></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Name *</span><input name="name" maxlength="255" placeholder="Cash" required></label>
        <label class="kontor-nativefield"><span>Type *</span>
          <select name="type" required>
            <option value="asset">Asset</option>
            <option value="liability">Liability</option>
            <option value="equity">Equity</option>
            <option value="revenue">Revenue</option>
            <option value="expense">Expense</option>
          </select>
        </label>
        <label class="kontor-nativefield"><span>Currency *</span><input name="currency_code" value="<?= $e($defaultCurrency) ?>" maxlength="3" required></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Parent account</span>
          <select name="parent_uid">
            <option value="">No parent</option>
            <?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>"><?= $e($account->code . ' · ' . $account->name) ?></option><?php endforeach; ?>
          </select>
        </label>
        <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Create account</button></div>
      </form>
    </section>
  <?php endif; ?>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Live balances</p><h3>Chart of accounts</h3></div></header>
    <?php if ($accountRows !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Account</th><th>Type</th><th>Currency</th><th>Normal side</th><th>Balance</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($accountRows as $row): $account = $row['account']; ?><tr>
          <td><strong><?= $e($account->code) ?> · <?= $e($account->name) ?></strong><?php if ($account->parentUid): ?><span class="kontor-secondary">Parent: <?= $e($accountLabels[$account->parentUid] ?? $account->parentUid) ?></span><?php endif; ?></td>
          <td><?= $e($account->type) ?></td>
          <td><?= $e($account->currencyCode) ?></td>
          <td><?= $e($account->isDebitNormal() ? 'debit' : 'credit') ?></td>
          <td><strong><?= $e($money($row['balance'])) ?></strong></td>
          <td><span class="kontor-pill<?= $account->isArchived() ? ' kontor-pill--inactive' : '' ?>"><?= $e($account->isArchived() ? 'archived' : $account->status) ?></span></td>
          <td><?php if ($canManageAccounts): ?><form method="post" action="<?= $e($adminUrl) ?>ledger-account-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="account_uid" value="<?= $e($account->uid->toString()) ?>">
            <input type="hidden" name="action" value="<?= $e($account->isArchived() ? 'restore' : 'archive') ?>">
            <button class="kontor-button kontor-button--ghost" type="submit"><?= $e($account->isArchived() ? 'Restore' : 'Archive') ?></button>
          </form><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?><div class="kontor-empty"><i class="fa fa-balance-scale"></i><h3>No accounts yet</h3><p>Create at least two accounts before recording a journal entry.</p></div><?php endif; ?>
  </section>

  <?php if ($canRecordEntries): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Immutable journal</p><h3>Record balanced entry</h3></div></header>
      <?php if (count($activeAccounts) >= 2): ?>
        <form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>ledger-entry-record/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <label class="kontor-nativefield kontor-nativefield--wide"><span>Description *</span><input name="description" maxlength="500" placeholder="Cash sale" required></label>
          <label class="kontor-nativefield"><span>Entry date *</span><input type="date" name="entry_date" value="<?= $e(date('Y-m-d')) ?>" required></label>
          <label class="kontor-nativefield"><span>Amount *</span><input name="amount" inputmode="decimal" value="1250.00" required></label>
          <label class="kontor-nativefield kontor-nativefield--wide"><span>Debit account *</span>
            <select name="debit_account_uid" required><?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>"><?= $e($account->code . ' · ' . $account->name . ' · ' . $account->currencyCode) ?></option><?php endforeach; ?></select>
          </label>
          <label class="kontor-nativefield kontor-nativefield--wide"><span>Credit account *</span>
            <select name="credit_account_uid" required><?php foreach ($activeAccounts as $account): ?><option value="<?= $e($account->uid->toString()) ?>"><?= $e($account->code . ' · ' . $account->name . ' · ' . $account->currencyCode) ?></option><?php endforeach; ?></select>
          </label>
          <label class="kontor-nativefield"><span>Reference type</span><input name="reference_type" maxlength="50" placeholder="invoice"></label>
          <label class="kontor-nativefield kontor-nativefield--wide"><span>Reference UID</span><input name="reference_uid" maxlength="26" placeholder="Optional ULID"></label>
          <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Record entry</button></div>
        </form>
      <?php else: ?><div class="kontor-empty"><p>Two active accounts in the same currency are required for a posting.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Append-only ledger</p><h3>Journal entries</h3></div></header>
    <?php if ($entryRows !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th>Debits = credits</th><th>Recorded</th></tr></thead>
        <tbody><?php foreach ($entryRows as $row): $entry = $row['entry']; ?><tr>
          <td><?= $e($entry->entryDate->format('Y-m-d')) ?></td>
          <td><strong><a href="<?= $e($adminUrl) ?>ledger/?id=<?= $e(rawurlencode($entry->uid->toString())) ?>"><?= $e($entry->description) ?></a></strong></td>
          <td><?= $e($entry->referenceType && $entry->referenceUid ? $entry->referenceType . ' · ' . $entry->referenceUid : '—') ?></td>
          <td><?= $e($money($row['amount'])) ?></td>
          <td><?= $e($entry->createdAt->format('Y-m-d H:i:s')) ?></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?><div class="kontor-empty"><p>No ledger entries have been recorded.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow"><?= $e($selected->entryDate->format('Y-m-d')) ?> · immutable</p><h3><?= $e($selected->description) ?></h3></div></header>
      <div class="kontor-detailgrid">
        <div><span>Entry UID</span><strong><?= $e($selected->uid->toString()) ?></strong></div>
        <div><span>Recorded by</span><strong><?= $e((string) ($selected->createdBy ?? 'system')) ?></strong></div>
        <div><span>Reference type</span><strong><?= $e($selected->referenceType ?? '—') ?></strong></div>
        <div><span>Reference UID</span><strong><?= $e($selected->referenceUid ?? '—') ?></strong></div>
      </div>
      <table class="kontor-table">
        <thead><tr><th>Account</th><th>Debit</th><th>Credit</th></tr></thead>
        <tbody><?php foreach ($selectedLines as $line): ?><tr>
          <td><strong><?= $e($accountLabels[$line->accountUid] ?? $line->accountUid) ?></strong></td>
          <td><?= $e($line->debit->isZero() ? '—' : $money($line->debit)) ?></td>
          <td><?= $e($line->credit->isZero() ? '—' : $money($line->credit)) ?></td>
        </tr><?php endforeach; ?></tbody>
      </table>
      <p>This posting is append-only. Corrections must be entered as a new reversing journal entry.</p>
    </section>
  <?php endif; ?>
</div>
