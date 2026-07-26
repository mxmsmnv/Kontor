<?php

/** @var \Kontor\Portal\Domain\PortalAccount[] $accounts */
/** @var \Kontor\Contacts\Domain\Contact[] $contacts */
/** @var \Kontor\Portal\Domain\PortalAccount|null $selected */
/** @var \Kontor\Contacts\Domain\Contact|null $contact */
/** @var array<int, array{quotation: \Kontor\Sales\Domain\Quotation, files: array<int, array<string, mixed>>}> $quotationRows */
/** @var array<int, array{invoice: \Kontor\Invoices\Domain\Invoice, payments: array<int, array<string, mixed>>, files: array<int, array<string, mixed>>}> $invoiceRows */
/** @var array{ok: bool, message: string}|null $verification */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Advanced capabilities · Customer experience</p>
      <h2>Portal</h2>
      <p>Provision customer accounts and inspect the exact profile, documents, payments, and files exposed by the portal services.</p>
    </div>
  </header>

  <?php if ($verification !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <p><strong><?= $e($verification['ok'] ? 'Login verified' : 'Login rejected') ?></strong> · <?= $e($verification['message']) ?></p>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Identity</p><h3>Create customer account</h3></div></header>
    <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>portal-account/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Contact *</span><select name="contact_uid" required><option value="">Select contact</option><?php foreach ($contacts as $candidate): ?><option value="<?= $e($candidate->uid->toString()) ?>"><?= $e($candidate->displayName) ?><?= $candidate->email !== null ? ' · ' . $e($candidate->email) : '' ?></option><?php endforeach; ?></select></label>
      <label class="kontor-nativefield"><span>Login email *</span><input type="email" name="email" required></label>
      <label class="kontor-nativefield"><span>Temporary password *</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
      <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Create portal account</button></div>
    </form>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Authentication</p><h3>Verify customer login</h3></div></header>
    <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>portal-verify/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Email *</span><input type="email" name="email" required></label>
      <label class="kontor-nativefield"><span>Password *</span><input type="password" name="password" autocomplete="current-password" required></label>
      <div class="kontor-nativeform__actions"><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Verify login</button></div>
    </form>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Access directory</p><h3>Portal accounts</h3></div></header>
    <?php if ($accounts !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Email</th><th>Contact UID</th><th>Status</th><th>Last login</th></tr></thead><tbody>
      <?php foreach ($accounts as $account): ?><tr>
        <td><strong><a href="<?= $e($adminUrl) ?>portal/?id=<?= $e(rawurlencode($account->uid->toString())) ?>"><?= $e($account->email) ?></a></strong></td>
        <td><code><?= $e($account->contactUid) ?></code></td>
        <td><?= $e($account->status) ?></td>
        <td><?= $e($account->lastLoginAt?->format('Y-m-d H:i') ?? 'never') ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No portal accounts.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null && $contact !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow"><?= $e($selected->status) ?> account</p><h3><?= $e($contact->displayName) ?></h3></div></header>
      <div class="kontor-detailgrid">
        <div><span>Login</span><strong><?= $e($selected->email) ?></strong></div>
        <div><span>Contact</span><strong><?= $e($selected->contactUid) ?></strong></div>
        <div><span>Last login</span><strong><?= $e($selected->lastLoginAt?->format('Y-m-d H:i:s') ?? 'never') ?></strong></div>
        <div><span>Language</span><strong><?= $e($contact->preferredLanguage ?: '—') ?></strong></div>
      </div>
      <form method="post" action="<?= $e($adminUrl) ?>portal-status/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="account_uid" value="<?= $e($selected->uid->toString()) ?>">
        <input type="hidden" name="action" value="<?= $e($selected->isActive() ? 'disable' : 'enable') ?>">
        <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit"><?= $e($selected->isActive() ? 'Disable account' : 'Enable account') ?></button>
      </form>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Customer-safe allowlist</p><h3>Profile</h3></div></header>
      <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>portal-profile/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="account_uid" value="<?= $e($selected->uid->toString()) ?>">
        <label class="kontor-nativefield"><span>First name</span><input name="firstName" value="<?= $e($contact->firstName) ?>"></label>
        <label class="kontor-nativefield"><span>Middle name</span><input name="middleName" value="<?= $e($contact->middleName) ?>"></label>
        <label class="kontor-nativefield"><span>Last name</span><input name="lastName" value="<?= $e($contact->lastName) ?>"></label>
        <label class="kontor-nativefield"><span>Phone</span><input name="phone" value="<?= $e($contact->phone) ?>"></label>
        <label class="kontor-nativefield"><span>Mobile</span><input name="mobile" value="<?= $e($contact->mobile) ?>"></label>
        <label class="kontor-nativefield"><span>Preferred language</span><input name="preferredLanguage" value="<?= $e($contact->preferredLanguage) ?>" maxlength="10"></label>
        <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Update portal profile</button></div>
      </form>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Customer scope</p><h3>Quotations</h3></div></header>
      <?php if ($quotationRows !== []): ?>
        <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Number</th><th>Status</th><th>Total</th><th>Validity</th><th>Files</th></tr></thead><tbody>
        <?php foreach ($quotationRows as $row): $quotation = $row['quotation']; ?><tr>
          <td><strong><?= $e($quotation->number ?? 'Draft') ?></strong></td>
          <td><?= $e($quotation->status) ?></td>
          <td><?= $e($money($quotation->total)) ?></td>
          <td><?= $e($quotation->validUntil?->format('Y-m-d') ?? '—') ?></td>
          <td><?php if ($row['files'] === []): ?>—<?php else: foreach ($row['files'] as $file): ?><a href="<?= $e($file['downloadUrl']) ?>"><?= $e($file['original_name']) ?></a> <?php endforeach; endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table>
      <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No customer quotations.</p></div><?php endif; ?>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Customer scope</p><h3>Invoices, payments, and files</h3></div></header>
      <?php if ($invoiceRows !== []): ?>
        <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Number</th><th>Status</th><th>Total</th><th>Due</th><th>Payments</th><th>Files</th></tr></thead><tbody>
        <?php foreach ($invoiceRows as $row): $invoice = $row['invoice']; ?><tr>
          <td><strong><?= $e($invoice->number ?? 'Draft') ?></strong></td>
          <td><?= $e($invoice->status) ?></td>
          <td><?= $e($money($invoice->total)) ?></td>
          <td><?= $e($money($invoice->due)) ?></td>
          <td><?php if ($row['payments'] === []): ?>—<?php else: foreach ($row['payments'] as $paymentRow): ?><?= $e($paymentRow['payment']->number ?? 'Payment') ?> · <?= $e($money($paymentRow['allocatedAmount'])) ?><br><?php endforeach; endif; ?></td>
          <td><?php if ($row['files'] === []): ?>—<?php else: foreach ($row['files'] as $file): ?><a href="<?= $e($file['downloadUrl']) ?>"><?= $e($file['original_name']) ?></a> <?php endforeach; endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table>
      <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No customer invoices.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
