<?php

/** @var array{code: string, legalName: string, email: string, phone: string, currencyCode: string, paymentTermsDays: string} $values */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Back to purchasing</a>
    <p class="kontor-eyebrow">Purchasing · Vendor</p>
    <h2>Create supplier</h2>
    <p>Add commercial identity, contact details, currency, and payment terms.</p>
  </header>
  <?php if ($error !== ''): ?><div class="uk-alert uk-alert-warning kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <form class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-form-stacked kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="kontor-nativefield"><span>Code *</span><input name="code" value="<?= $e($values['code']) ?>" placeholder="SUP-001" required></label>
    <label class="kontor-nativefield"><span>Legal name *</span><input name="legal_name" value="<?= $e($values['legalName']) ?>" required></label>
    <label class="kontor-nativefield"><span>Email</span><input name="email" type="email" value="<?= $e($values['email']) ?>"></label>
    <label class="kontor-nativefield"><span>Phone</span><input name="phone" value="<?= $e($values['phone']) ?>"></label>
    <label class="kontor-nativefield"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" required></label>
    <label class="kontor-nativefield"><span>Payment terms (days)</span><input name="payment_terms_days" type="number" min="0" max="365" value="<?= $e($values['paymentTermsDays']) ?>"></label>
    <div class="kontor-nativeform__actions">
      <button class="uk-button uk-button-primary kontor-button" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Create supplier</button>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>purchasing/">Cancel</a>
    </div>
  </form>
</div>
