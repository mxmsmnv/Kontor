<?php

/** @var \Kontor\CRM\Domain\Deal|null $deal */
/** @var array<string, string> $values */
/** @var \Kontor\CRM\Domain\Pipeline[] $pipelines */
/** @var \Kontor\CRM\Domain\Stage[] $stages */
/** @var \Kontor\Contacts\Domain\Contact[] $contacts */
/** @var \Kontor\Contacts\Domain\Company[] $companies */
/** @var \Kontor\Sales\Domain\Quotation[] $dealQuotations */
/** @var bool $canCreateQuotation */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>crm-deals/?pipeline=<?= $e(rawurlencode($values['pipelineUid'])) ?>">
      <i class="fa fa-arrow-left"></i> Back to pipeline
    </a>
    <p class="kontor-eyebrow">CRM · Deal</p>
    <h2><?= $e($deal?->title ?? 'Create deal') ?></h2>
    <p>Value, relationship, stage, and expected close date.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="uk-alert uk-alert-warning kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <?php if ($deal !== null && ($dealQuotations !== [] || $canCreateQuotation)): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <p class="kontor-eyebrow">Sales · Quotations</p>
      <h3>Commercial follow-through</h3>
      <?php if ($dealQuotations !== []): ?>
        <div class="kontor-actions">
          <?php foreach ($dealQuotations as $quotation): ?>
            <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>sales-quotation/?id=<?= $e(rawurlencode($quotation->uid->toString())) ?>">
              Open <?= $e($quotation->number ?? 'draft quotation') ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($canCreateQuotation): ?>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>sales-quotation/?deal=<?= $e(rawurlencode($deal->uid->toString())) ?>">
          <i class="fa fa-file-text-o"></i> Create quotation from deal
        </a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($pipelines === []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
      <h3>A pipeline is required</h3>
      <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>crm-pipeline/">Create pipeline</a>
    </section>
  <?php else: ?>
    <form class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-form-stacked kontor-nativeform" method="post" action="./<?= $deal !== null ? '?id=' . $e(rawurlencode($deal->uid->toString())) : '' ?>">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield kontor-nativefield--wide">
        <span>Title *</span>
        <input name="title" value="<?= $e($values['title']) ?>" required>
      </label>
      <label class="kontor-nativefield">
        <span>Pipeline</span>
        <?php if ($deal !== null): ?>
          <input type="hidden" name="pipeline_uid" value="<?= $e($values['pipelineUid']) ?>">
        <?php endif; ?>
        <select<?= $deal === null ? ' name="pipeline_uid"' : ' disabled' ?>>
          <?php foreach ($pipelines as $pipeline): ?>
            <option value="<?= $e($pipeline->uid->toString()) ?>"<?= $values['pipelineUid'] === $pipeline->uid->toString() ? ' selected' : '' ?>><?= $e($pipeline->name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="kontor-nativefield">
        <span>Stage</span>
        <select name="stage_uid">
          <?php foreach ($stages as $stage): ?>
            <?php if (($deal === null || $deal->isOpen()) && $stage->stateType !== 'open') { continue; } ?>
            <?php if ($deal !== null && !$deal->isOpen() && $stage->uid->toString() !== $deal->stageUid) { continue; } ?>
            <option value="<?= $e($stage->uid->toString()) ?>"<?= $values['stageUid'] === $stage->uid->toString() ? ' selected' : '' ?>><?= $e($stage->displayNameIn('en') ?? $stage->nameKey) ?></option>
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
        <span>Value</span>
        <input name="value_amount" inputmode="decimal" value="<?= $e($values['valueAmount']) ?>" placeholder="0.00">
      </label>
      <label class="kontor-nativefield">
        <span>Currency</span>
        <select name="currency">
          <?php foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'] as $currency): ?>
            <option value="<?= $e($currency) ?>"<?= $values['currency'] === $currency ? ' selected' : '' ?>><?= $e($currency) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="kontor-nativefield">
        <span>Probability</span>
        <input name="probability" type="number" min="0" max="100" value="<?= $e($values['probability']) ?>" placeholder="Stage default">
      </label>
      <label class="kontor-nativefield">
        <span>Expected close</span>
        <input name="expected_close_date" type="date" value="<?= $e($values['expectedCloseDate']) ?>">
      </label>
      <label class="kontor-nativefield kontor-nativefield--wide">
        <span>Source</span>
        <input name="source" value="<?= $e($values['source']) ?>">
      </label>
      <label class="kontor-nativefield kontor-nativefield--wide">
        <span>Description</span>
        <textarea name="description" rows="5"><?= $e($values['description']) ?></textarea>
      </label>
      <div class="kontor-nativeform__actions">
        <button class="uk-button uk-button-primary kontor-button" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Save deal</button>
        <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>crm-deals/?pipeline=<?= $e(rawurlencode($values['pipelineUid'])) ?>">Cancel</a>
      </div>
    </form>
  <?php endif; ?>
</div>
