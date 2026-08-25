<?php

/** @var \Kontor\CRM\Domain\Lead|null $lead */
/** @var array<string, string> $values */
/** @var string $error */
/** @var \Kontor\Contacts\Domain\Contact[] $contacts */
/** @var \Kontor\Contacts\Domain\Company[] $companies */
/** @var bool $canViewContact */
/** @var bool $canViewCompany */
/** @var bool $canCreateContact */
/** @var bool $canCreateCompany */
/** @var bool $canViewDeal */
/** @var bool $canConvertLead */
/** @var bool $canConfigurePipeline */
/** @var bool $conversionNeedsCustomer */
/** @var bool $conversionNeedsPipeline */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static fn (?string $value): string => $value === null || $value === ''
    ? 'Not set'
    : ucwords(str_replace('_', ' ', $value));
$contactMap = [];
foreach ($contacts as $contact) {
    $contactMap[$contact->uid->toString()] = $contact;
}
$companyMap = [];
foreach ($companies as $company) {
    $companyMap[$company->uid->toString()] = $company;
}
$selectedContact = $values['contactUid'] !== '' ? ($contactMap[$values['contactUid']] ?? null) : null;
$selectedCompany = $values['companyUid'] !== '' ? ($companyMap[$values['companyUid']] ?? null) : null;
$statusOrder = ['new' => 1, 'contacted' => 2, 'qualified' => 3, 'converted' => 4];
$currentStep = $statusOrder[$values['status']] ?? 1;
$normalizedEstimate = str_replace(',', '.', $values['estimatedAmount']);
$estimatedValue = $values['estimatedAmount'] === ''
    ? 'Not estimated'
    : (is_numeric($normalizedEstimate)
        ? number_format((float) $normalizedEstimate, 2, '.', ',') . ' ' . $values['currency']
        : 'Needs correction');
$nextAction = 'Not scheduled';
if ($lead?->isConverted()) {
    $nextAction = 'Completed with conversion';
} elseif ($values['nextActionAt'] !== '') {
    try {
        $nextAction = (new \DateTimeImmutable($values['nextActionAt']))->format('M j, Y · H:i');
    } catch (\Throwable) {
        $nextAction = 'Needs correction';
    }
}
?>
<div class="ProcessKontor pw-module-workspace kontor-shell kontor-lead-workspace">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back uk-link-reset" href="<?= $e($adminUrl) ?>crm/"><i class="fa fa-arrow-left"></i> Back to leads</a>
    <p class="kontor-eyebrow">CRM · Lead qualification</p>
    <p><?= $lead === null ? 'Capture a real opportunity, connect customer context and schedule the next meaningful action.' : 'Keep customer context, commercial potential and the next sales action aligned.' ?></p>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert uk-alert-warning kontor-warning" role="alert"><i class="fa fa-exclamation-triangle"></i><div><strong>Lead was not saved</strong><p><?= $e($error) ?></p></div></div><?php endif; ?>

  <?php if ($lead !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-lead-summary">
      <div class="uk-grid-medium uk-flex-middle" uk-grid>
        <div class="uk-width-expand@m">
          <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><span class="kontor-lead-icon"><i class="fa fa-bullseye"></i></span></div><div><strong class="kontor-lead-summary__title">Lead overview</strong><div class="uk-text-meta"><?= $e($lead->source ?: 'Source not recorded') ?><?php if ($selectedCompany !== null): ?> · <?= $e($selectedCompany->legalName) ?><?php elseif ($selectedContact !== null): ?> · <?= $e($selectedContact->displayName) ?><?php endif; ?></div></div><div><span class="uk-label"><?= $e($humanize($values['status'])) ?></span></div></div>
        </div>
        <div class="uk-width-auto@m"><div class="uk-flex uk-flex-wrap uk-grid-medium" uk-grid><div><span class="uk-text-meta uk-display-block">Priority</span><strong><?= $e($humanize($values['priority'])) ?></strong></div><div><span class="uk-text-meta uk-display-block">Potential value</span><strong><?= $e($estimatedValue) ?></strong></div><div><span class="uk-text-meta uk-display-block">Next action</span><strong><?= $e($nextAction) ?></strong></div></div></div>
      </div>
    </section>
  <?php endif; ?>

  <section class="kontor-section-intro uk-margin-top" aria-label="About this section">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <div><strong><?= $lead === null ? 'Create a lead that can move forward' : 'Move the lead toward a clear outcome' ?></strong><p>A lead becomes useful when it explains the opportunity, identifies the customer and records what happens next. Qualification unlocks conversion into the deal pipeline.</p></div>
  </section>

  <ol class="kontor-lead-steps" aria-label="Lead workflow">
    <?php foreach ([1 => ['Capture', 'Opportunity recorded'], 2 => ['Contact', 'Conversation started'], 3 => ['Qualify', 'Demand confirmed'], 4 => ['Deal', 'Pipeline created']] as $step => [$label, $caption]): ?>
      <li class="<?= $currentStep > $step ? 'is-complete' : ($currentStep === $step ? 'is-current' : '') ?>"><span><?= $currentStep > $step ? '<i class="fa fa-check"></i>' : $e((string) $step) ?></span><div><strong><?= $e($label) ?></strong><small><?= $e($caption) ?></small></div></li>
    <?php endforeach; ?>
  </ol>

  <form class="uk-form-stacked" method="post" action="./<?= $lead !== null ? '?id=' . $e(rawurlencode($lead->uid->toString())) : '' ?>">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <?php if ($lead === null): ?><input type="hidden" name="status" value="new"><?php elseif ($lead->isConverted()): ?><input type="hidden" name="status" value="converted"><?php endif; ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
          <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">1 · Opportunity</p><h3>What could happen?</h3><p class="uk-text-meta uk-margin-small-top">Describe the customer outcome clearly enough that another teammate can understand it.</p></div></header>
          <div class="kontor-nativeform kontor-nativeform--embedded">
            <label class="kontor-nativefield kontor-nativefield--wide"><span>Lead title *</span><small class="kontor-field-description">A short outcome or need teammates will recognize in the pipeline.</small><input class="uk-input" name="title" value="<?= $e($values['title']) ?>" placeholder="Modernize the customer onboarding process" maxlength="191" required><small class="kontor-field-note"><strong>Note:</strong> Describe the opportunity, not an internal code.</small></label>
            <label class="kontor-nativefield kontor-nativefield--wide"><span>Description</span><small class="kontor-field-description">The customer need, business context and evidence collected so far.</small><textarea class="uk-textarea" name="description" rows="5" placeholder="What is changing, why now, and what result is the customer seeking?"><?= $e($values['description']) ?></textarea><small class="kontor-field-note"><strong>Note:</strong> Keep assumptions separate from confirmed customer facts.</small></label>
            <label class="kontor-nativefield"><span>Source</span><small class="kontor-field-description">Where this opportunity first came from.</small><input class="uk-input" name="source" value="<?= $e($values['source']) ?>" placeholder="Referral, website, campaign"><small class="kontor-field-note"><strong>Note:</strong> Use consistent source names for useful reporting.</small></label>
            <label class="kontor-nativefield"><span>Priority</span><small class="kontor-field-description">How urgently the team should work this lead.</small><select class="uk-select" name="priority"><?php foreach (['low', 'medium', 'high', 'urgent'] as $priority): ?><option value="<?= $e($priority) ?>"<?= $values['priority'] === $priority ? ' selected' : '' ?>><?= $e($humanize($priority)) ?></option><?php endforeach; ?></select><small class="kontor-field-note"><strong>Note:</strong> Use urgency for real timing or impact, not general importance.</small></label>
          </div>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-top">
          <header class="kontor-sectionhead uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="kontor-eyebrow">2 · Customer</p><h3>Who owns the need?</h3><p class="uk-text-meta uk-margin-small-top">Connect an existing person or company when known. A customer link is required before deal conversion.</p></div><?php if ($lead !== null && ($selectedContact !== null || $selectedCompany !== null)): ?><div class="uk-width-auto@m"><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><?php if ($selectedContact !== null && $canViewContact): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($selectedContact->uid->toString())) ?>"><i class="fa fa-user-o"></i> Open contact</a></div><?php endif; ?><?php if ($selectedCompany !== null && $canViewCompany): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>company/?id=<?= $e(rawurlencode($selectedCompany->uid->toString())) ?>"><i class="fa fa-building-o"></i> Open company</a></div><?php endif; ?></div></div><?php endif; ?></header>
          <?php if (!$canViewContact && !$canViewCompany): ?>
            <div class="uk-alert-primary uk-margin-top" uk-alert><p>The customer directory is not available for this role or installation. You can save the lead now and connect a customer later.</p></div>
            <input type="hidden" name="contact_uid" value="<?= $e($values['contactUid']) ?>"><input type="hidden" name="company_uid" value="<?= $e($values['companyUid']) ?>">
          <?php else: ?>
            <div class="kontor-nativeform kontor-nativeform--embedded">
              <?php if ($canViewContact): ?><label class="kontor-nativefield"><span>Contact</span><small class="kontor-field-description">The person leading or influencing this opportunity.</small><select class="uk-select" name="contact_uid"><option value="">No contact selected</option><?php foreach ($contacts as $contact): ?><option value="<?= $e($contact->uid->toString()) ?>"<?= $values['contactUid'] === $contact->uid->toString() ? ' selected' : '' ?>><?= $e($contact->displayName) ?></option><?php endforeach; ?></select><small class="kontor-field-note"><strong>Note:</strong> Choose a known person; do not create duplicates.</small></label><?php else: ?><input type="hidden" name="contact_uid" value="<?= $e($values['contactUid']) ?>"><?php endif; ?>
              <?php if ($canViewCompany): ?><label class="kontor-nativefield"><span>Company</span><small class="kontor-field-description">The organization that owns the commercial opportunity.</small><select class="uk-select" name="company_uid"><option value="">No company selected</option><?php foreach ($companies as $company): ?><option value="<?= $e($company->uid->toString()) ?>"<?= $values['companyUid'] === $company->uid->toString() ? ' selected' : '' ?>><?= $e($company->legalName) ?></option><?php endforeach; ?></select><small class="kontor-field-note"><strong>Note:</strong> Usually one primary customer link is enough.</small></label><?php else: ?><input type="hidden" name="company_uid" value="<?= $e($values['companyUid']) ?>"><?php endif; ?>
            </div>
            <?php if (($canViewContact && $contacts === []) || ($canViewCompany && $companies === [])): ?><div class="uk-flex uk-flex-wrap uk-grid-small uk-margin-top" uk-grid><?php if ($canViewContact && $contacts === [] && $canCreateContact): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>contact/"><i class="fa fa-user-plus"></i> Create contact</a></div><?php endif; ?><?php if ($canViewCompany && $companies === [] && $canCreateCompany): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>company/"><i class="fa fa-building"></i> Create company</a></div><?php endif; ?></div><?php endif; ?>
          <?php endif; ?>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-top">
          <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">3 · Commercial context</p><h3>What is it worth, and what happens next?</h3><p class="uk-text-meta uk-margin-small-top">Use a realistic estimate and schedule a concrete customer-facing action.</p></div></header>
          <div class="kontor-nativeform kontor-nativeform--embedded">
            <label class="kontor-nativefield"><span>Estimated value</span><small class="kontor-field-description">Expected commercial value before a formal deal forecast exists.</small><input class="uk-input" name="estimated_amount" type="number" min="0" step="0.01" inputmode="decimal" value="<?= $e($values['estimatedAmount']) ?>" placeholder="0.00"><small class="kontor-field-note"><strong>Note:</strong> Enter the amount without a currency symbol.</small></label>
            <label class="kontor-nativefield"><span>Currency</span><small class="kontor-field-description">Currency used to interpret the estimated value.</small><select class="uk-select" name="currency"><?php foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'] as $currency): ?><option value="<?= $e($currency) ?>"<?= $values['currency'] === $currency ? ' selected' : '' ?>><?= $e($currency) ?></option><?php endforeach; ?></select><small class="kontor-field-note"><strong>Note:</strong> Confirm the customer’s commercial currency before conversion.</small></label>
            <label class="kontor-nativefield kontor-nativefield--wide"><span>Next customer action</span><small class="kontor-field-description">The date and time of the next call, meeting, proposal or follow-up.</small><input class="uk-input" name="next_action_at" type="datetime-local" value="<?= $e($values['nextActionAt']) ?>"><small class="kontor-field-note"><strong>Note:</strong> An unscheduled lead is easy to lose; add a real commitment whenever possible.</small></label>
            <?php if ($lead !== null && !$lead->isConverted()): ?><label class="kontor-nativefield kontor-nativefield--wide"><span>Qualification status</span><small class="kontor-field-description">The current outcome of your customer discovery.</small><select class="uk-select" name="status"><?php foreach (['new' => 'New · Not contacted', 'contacted' => 'Contacted · Conversation started', 'qualified' => 'Qualified · Real demand confirmed', 'lost' => 'Lost · Opportunity closed'] as $status => $label): ?><option value="<?= $e($status) ?>"<?= $values['status'] === $status ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><small class="kontor-field-note"><strong>Note:</strong> Converted is assigned automatically when a deal is created.</small></label><?php endif; ?>
          </div>
        </section>
      </div>

      <aside class="uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-lead-savecard">
          <p class="kontor-eyebrow"><?= $lead === null ? 'Before creating' : 'Before saving' ?></p><h3><?= $lead === null ? 'Make it actionable' : 'Keep it trustworthy' ?></h3>
          <ul class="uk-list uk-list-divider kontor-checklist"><li><i class="fa fa-bullseye"></i><span>The title describes a customer outcome.</span></li><li><i class="fa fa-user-o"></i><span>The customer is connected when known.</span></li><li><i class="fa fa-calendar-check-o"></i><span>The next action has a clear owner and time.</span></li></ul>
          <button class="uk-button uk-button-primary uk-width-1-1" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> <?= $lead === null ? 'Create lead' : 'Save changes' ?></button>
          <a class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top uk-link-reset" href="<?= $e($adminUrl) ?>crm/">Cancel</a>
        </section>
      </aside>
    </div>
  </form>

  <?php if ($lead !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-top kontor-lead-conversion">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">4 · Deal pipeline</p><h3><?= $lead->isConverted() ? 'Deal created' : 'Convert qualified demand' ?></h3><p class="uk-text-meta uk-margin-small-top"><?= $lead->isConverted() ? 'This lead has completed qualification and continues as a deal.' : 'Conversion carries the customer, source, value and description into the default deal pipeline.' ?></p></div></header>
      <?php if ($lead->isConverted() && $lead->convertedDealUid !== null): ?>
        <?php if ($canViewDeal): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($lead->convertedDealUid)) ?>"><i class="fa fa-arrow-right"></i> Open deal</a><?php else: ?><p class="uk-text-muted">The connected deal is available to teammates with deal access.</p><?php endif; ?>
      <?php elseif ($canConvertLead): ?>
        <form method="post" action="<?= $e($adminUrl) ?>crm-lead-convert/" data-kontor-confirm="Convert this qualified lead into a deal?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($lead->uid->toString()) ?>"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-exchange"></i> Convert to deal</button></form>
      <?php elseif ($conversionNeedsCustomer): ?>
        <div class="uk-alert-primary" uk-alert><p><strong>Connect a customer first.</strong><br>Select a contact or company above, save the lead, then return here to convert it.</p></div>
      <?php elseif ($conversionNeedsPipeline): ?>
        <div class="uk-alert-primary" uk-alert><p><strong>A deal pipeline is required.</strong><br>Configure a default pipeline with at least one open stage before conversion.</p><?php if ($canConfigurePipeline): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-pipeline/">Configure pipeline</a><?php endif; ?></div>
      <?php else: ?><p class="uk-text-muted">Set the status to Qualified and save the lead when customer demand is confirmed.</p><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
