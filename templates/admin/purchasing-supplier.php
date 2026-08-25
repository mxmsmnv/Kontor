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
    <a class="kontor-formhead__back uk-link-reset" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Back to purchasing</a>
    <p class="kontor-eyebrow">Purchasing · Supplier setup</p>
    <h2>Create supplier</h2>
    <p>Establish the vendor identity and commercial defaults used by future purchase orders.</p>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-warning" uk-alert><p><i class="fa fa-exclamation-triangle uk-margin-small-right"></i><strong><?= $e($error) ?></strong></p></div><?php endif; ?>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="./">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Supplier foundation</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Add an approved buying partner</h3><p class="uk-text-muted uk-margin-small-top">Enter the details your purchasing team needs to identify the supplier and prepare accurate orders.</p></div>

        <fieldset class="uk-fieldset uk-margin-medium-top">
          <legend class="uk-legend">1. Identify the supplier</legend>
          <p class="uk-text-muted uk-margin-small-top">Use the registered business name and a stable internal reference.</p>
          <div class="uk-grid-small" uk-grid>
            <label class="kontor-nativefield uk-width-1-1 uk-width-2-3@m"><span>Legal name *</span><input name="legal_name" value="<?= $e($values['legalName']) ?>" maxlength="191" placeholder="Northwind Components GmbH" autocomplete="organization" required><span class="kontor-field-guidance"><span class="kontor-field-description">The registered supplier name shown on purchase orders and receiving records.</span><span class="kontor-field-note"><strong>Note:</strong> Use the legal entity name, not a contact person or informal nickname.</span></span></label>
            <label class="kontor-nativefield uk-width-1-1 uk-width-1-3@m"><span>Supplier code *</span><input name="code" value="<?= $e($values['code']) ?>" maxlength="50" pattern="[A-Za-z0-9_-]{1,50}" placeholder="NORTHWIND" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">A short reference used in purchasing lists, exports and integrations.</span><span class="kontor-field-note"><strong>Note:</strong> Letters, numbers, hyphens and underscores only.</span></span></label>
          </div>
        </fieldset>

        <hr class="uk-margin-medium">
        <fieldset class="uk-fieldset">
          <legend class="uk-legend">2. Add ordering contacts</legend>
          <p class="uk-text-muted uk-margin-small-top">These details help buyers reach the supplier when an order or delivery needs attention.</p>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
            <label class="kontor-nativefield"><span>Ordering email</span><div class="uk-inline uk-width-1-1"><span class="uk-form-icon"><i class="fa fa-envelope-o"></i></span><input name="email" type="email" maxlength="191" value="<?= $e($values['email']) ?>" placeholder="orders@example.com" autocomplete="email"></div><span class="kontor-field-guidance"><span class="kontor-field-description">The shared mailbox or person responsible for purchase-order communication.</span><span class="kontor-field-note"><strong>Note:</strong> Prefer a monitored ordering address over a personal inbox.</span></span></label>
            <label class="kontor-nativefield"><span>Ordering phone</span><div class="uk-inline uk-width-1-1"><span class="uk-form-icon"><i class="fa fa-phone"></i></span><input name="phone" type="tel" maxlength="50" value="<?= $e($values['phone']) ?>" placeholder="+49 30 123456" autocomplete="tel"></div><span class="kontor-field-guidance"><span class="kontor-field-description">The number buyers can use for urgent order or delivery questions.</span><span class="kontor-field-note"><strong>Note:</strong> Include the international country code when teams work across regions.</span></span></label>
          </div>
        </fieldset>

        <hr class="uk-margin-medium">
        <fieldset class="uk-fieldset">
          <legend class="uk-legend">3. Set commercial defaults</legend>
          <p class="uk-text-muted uk-margin-small-top">New purchase orders start with these values, but buyers can review the agreement before issue.</p>
          <div class="uk-grid-small" uk-grid>
            <label class="kontor-nativefield uk-width-1-1 uk-width-1-3@m"><span>Order currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" pattern="[A-Za-z]{3}" placeholder="EUR" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">The currency normally used for this supplier's prices and purchase orders.</span><span class="kontor-field-note"><strong>Note:</strong> Use a three-letter ISO code such as EUR, USD or GBP.</span></span></label>
            <label class="kontor-nativefield uk-width-1-1 uk-width-2-3@m"><span>Payment terms in days</span><input name="payment_terms_days" type="number" min="0" max="365" step="1" inputmode="numeric" value="<?= $e($values['paymentTermsDays']) ?>" placeholder="30"><span class="kontor-field-guidance"><span class="kontor-field-description">The usual number of days allowed between the supplier invoice date and payment.</span><span class="kontor-field-note"><strong>Note:</strong> Enter 0 for immediate payment; confirm unusual terms before placing an order.</span></span></label>
          </div>
        </fieldset>

        <div class="uk-alert-primary uk-margin-medium-top" uk-alert><p><i class="fa fa-info-circle uk-margin-small-right"></i>The supplier will be created as <strong>Active</strong>. No purchase order is created or sent automatically.</p></div>
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>purchasing/"><i class="fa fa-arrow-left"></i> Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1">Create supplier <i class="fa fa-angle-right"></i></button></div></div>
      </form>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <aside class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">What happens next</p><h3 class="uk-card-title uk-margin-small-top">From supplier to receipt</h3><p class="uk-text-muted">Creation adds an approved vendor to Purchasing. The commercial commitment begins only when a purchase order is issued.</p>
        <ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Create a draft order</strong><br><span class="uk-text-meta">Choose this supplier, add items, prices and a destination.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Review and issue</strong><br><span class="uk-text-meta">Confirm quantities, dates and commercial terms before commitment.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Record the receipt</strong><br><span class="uk-text-meta">Capture what actually arrived so stock and open quantities remain accurate.</span></span></div></li></ol>
      </aside>

      <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Before you save</p><h3 class="uk-card-title uk-margin-small-top">Quick check</h3><ul class="uk-list uk-list-divider"><li><i class="fa fa-check-circle uk-text-success uk-margin-small-right"></i>Legal entity is unambiguous</li><li><i class="fa fa-check-circle uk-text-success uk-margin-small-right"></i>Supplier code is stable and unique</li><li><i class="fa fa-check-circle uk-text-success uk-margin-small-right"></i>Currency and payment terms match the agreement</li></ul>
      </aside>
    </div>
  </div>
</div>
