<?php

/** @var \Kontor\CRM\Domain\Lead|null $lead */
/** @var array<string, string> $values */
/** @var string $error */
/** @var \Kontor\Contacts\Domain\Contact[] $contacts */
/** @var \Kontor\Contacts\Domain\Company[] $companies */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>crm/">
      <i class="fa fa-arrow-left"></i> Back to leads
    </a>
    <p class="kontor-eyebrow">CRM</p>
    <h2><?= $e($lead?->title ?? 'Create lead') ?></h2>
    <p>Opportunity ownership, qualification, value, and next action.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <form class="kontor-card kontor-nativeform" method="post" action="./<?= $lead !== null ? '?id=' . $e(rawurlencode($lead->uid->toString())) : '' ?>">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="kontor-nativefield kontor-nativefield--wide">
      <span>Title *</span>
      <input name="title" value="<?= $e($values['title']) ?>" required>
    </label>
    <label class="kontor-nativefield">
      <span>Status</span>
      <select name="status">
        <?php foreach (['new', 'contacted', 'qualified', 'converted', 'lost'] as $status): ?>
          <option value="<?= $e($status) ?>"<?= $values['status'] === $status ? ' selected' : '' ?>><?= $e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Priority</span>
      <select name="priority">
        <?php foreach (['low', 'medium', 'high', 'urgent'] as $priority): ?>
          <option value="<?= $e($priority) ?>"<?= $values['priority'] === $priority ? ' selected' : '' ?>><?= $e(ucfirst($priority)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Contact</span>
      <select name="contact_uid">
        <option value="">No contact</option>
        <?php foreach ($contacts as $contact): ?>
          <option value="<?= $e($contact->uid->toString()) ?>"<?= $values['contactUid'] === $contact->uid->toString() ? ' selected' : '' ?>><?= $e($contact->displayName) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Company</span>
      <select name="company_uid">
        <option value="">No company</option>
        <?php foreach ($companies as $company): ?>
          <option value="<?= $e($company->uid->toString()) ?>"<?= $values['companyUid'] === $company->uid->toString() ? ' selected' : '' ?>><?= $e($company->legalName) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield">
      <span>Source</span>
      <input name="source" value="<?= $e($values['source']) ?>" placeholder="Referral, website, campaign">
    </label>
    <label class="kontor-nativefield">
      <span>Next action</span>
      <input name="next_action_at" type="datetime-local" value="<?= $e($values['nextActionAt']) ?>">
    </label>
    <label class="kontor-nativefield">
      <span>Estimated value</span>
      <input name="estimated_amount" inputmode="decimal" value="<?= $e($values['estimatedAmount']) ?>" placeholder="0.00">
    </label>
    <label class="kontor-nativefield">
      <span>Currency</span>
      <select name="currency">
        <?php foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'] as $currency): ?>
          <option value="<?= $e($currency) ?>"<?= $values['currency'] === $currency ? ' selected' : '' ?>><?= $e($currency) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="kontor-nativefield kontor-nativefield--wide">
      <span>Description</span>
      <textarea name="description" rows="5"><?= $e($values['description']) ?></textarea>
    </label>
    <div class="kontor-nativeform__actions">
      <button class="kontor-button" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Save lead</button>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>crm/">Cancel</a>
    </div>
  </form>
</div>
