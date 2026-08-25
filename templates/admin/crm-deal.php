<?php

/** @var \Kontor\CRM\Domain\Deal|null $deal */
/** @var array<string, string> $values */
/** @var \Kontor\CRM\Domain\Pipeline[] $pipelines */
/** @var \Kontor\CRM\Domain\Stage[] $stages */
/** @var \Kontor\Contacts\Domain\Contact[] $contacts */
/** @var \Kontor\Contacts\Domain\Company[] $companies */
/** @var \Kontor\CRM\Domain\Pipeline|null $selectedPipeline */
/** @var \Kontor\CRM\Domain\Stage|null $selectedStage */
/** @var \Kontor\Contacts\Domain\Contact|null $selectedContact */
/** @var \Kontor\Contacts\Domain\Company|null $selectedCompany */
/** @var \Kontor\Sales\Domain\Quotation[] $dealQuotations */
/** @var bool $canCreateQuotation */
/** @var bool $canEdit */
/** @var bool $canMove */
/** @var bool $canCloseWon */
/** @var bool $canCloseLost */
/** @var bool $canViewContact */
/** @var bool $canViewCompany */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static fn (?\Kontor\SDK\ValueObjects\Money $value): string => $value === null
    ? 'Not set'
    : number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$humanize = static fn (?string $value): string => $value === null || $value === ''
    ? 'Not set'
    : ucwords(str_replace('_', ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'won' => ' uk-label-success',
    'lost' => ' uk-label-warning',
    default => '',
};
$stageLabel = $selectedStage?->displayNameIn('en') ?? $selectedStage?->nameKey ?? 'Not set';
$probability = $deal === null
    ? ($values['probability'] !== '' ? (int) $values['probability'] : ($selectedStage?->probability ?? 0))
    : match ($deal->status) {
        'won' => 100,
        'lost' => 0,
        default => $deal->probability ?? $selectedStage?->probability ?? 0,
    };
$closeLabel = $deal?->expectedCloseDate?->format('M j, Y') ?? 'Not scheduled';
$showInlineForm = $deal === null || $error !== '';

$renderForm = static function () use ($deal, $values, $pipelines, $stages, $contacts, $companies, $adminUrl, $csrfName, $csrfValue, $e): void { ?>
  <form class="uk-form-stacked" method="post" action="./<?= $deal !== null ? '?id=' . $e(rawurlencode($deal->uid->toString())) : '' ?>">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Opportunity</p><h3 class="uk-card-title uk-margin-small-top">Describe the deal</h3>
          <p class="uk-text-muted">Make the customer outcome clear enough that another teammate can continue the conversation.</p>
          <div class="uk-margin"><label class="uk-form-label" for="deal-title">Deal title</label><input class="uk-input uk-margin-small-top" id="deal-title" name="title" value="<?= $e($values['title']) ?>" placeholder="Northstar process modernization" required><div class="uk-text-meta uk-margin-small-top">Name the opportunity, not an internal code.</div></div>
          <div><label class="uk-form-label" for="deal-description">Description <span class="uk-text-meta">(optional)</span></label><textarea class="uk-textarea uk-margin-small-top" id="deal-description" name="description" rows="7" placeholder="Customer need, proposed outcome and important constraints"><?= $e($values['description']) ?></textarea><div class="uk-text-meta uk-margin-small-top">Include the context needed to qualify the opportunity and prepare an offer.</div></div>
        </section>
      </div>
      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Pipeline</p><h3 class="uk-card-title uk-margin-small-top">Plan the opportunity</h3>
          <div class="uk-margin"><label class="uk-form-label" for="deal-pipeline">Pipeline</label><?php if ($deal !== null): ?><input type="hidden" name="pipeline_uid" value="<?= $e($values['pipelineUid']) ?>"><?php endif; ?><select class="uk-select uk-margin-small-top" id="deal-pipeline"<?= $deal === null ? ' name="pipeline_uid"' : ' disabled' ?>><?php foreach ($pipelines as $pipeline): ?><option value="<?= $e($pipeline->uid->toString()) ?>"<?= $values['pipelineUid'] === $pipeline->uid->toString() ? ' selected' : '' ?>><?= $e($pipeline->name) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top"><?= $deal === null ? 'Choose where the opportunity should be tracked.' : 'Existing deals remain in their original pipeline.' ?></div></div>
          <div class="uk-margin"><label class="uk-form-label" for="deal-stage">Stage</label><?php if ($deal !== null && !$deal->isOpen()): ?><input type="hidden" name="stage_uid" value="<?= $e($values['stageUid']) ?>"><?php endif; ?><select class="uk-select uk-margin-small-top" id="deal-stage"<?= $deal === null || $deal->isOpen() ? ' name="stage_uid"' : ' disabled' ?>><?php foreach ($stages as $stage): ?><?php if (($deal === null || $deal->isOpen()) && $stage->stateType !== 'open') { continue; } ?><?php if ($deal !== null && !$deal->isOpen() && $stage->uid->toString() !== $deal->stageUid) { continue; } ?><option value="<?= $e($stage->uid->toString()) ?>"<?= $values['stageUid'] === $stage->uid->toString() ? ' selected' : '' ?>><?= $e($stage->displayNameIn('en') ?? $stage->nameKey) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top"><?= $deal !== null && !$deal->isOpen() ? 'Closed deals keep their final stage.' : 'Use the stage that matches the customer commitment today.' ?></div></div>
          <div class="uk-grid-small" uk-grid><div class="uk-width-2-3"><label class="uk-form-label" for="deal-value">Value</label><input class="uk-input uk-margin-small-top" id="deal-value" name="value_amount" inputmode="decimal" value="<?= $e($values['valueAmount']) ?>" placeholder="0.00"><div class="uk-text-meta uk-margin-small-top">Expected commercial value before tax.</div></div><div class="uk-width-1-3"><label class="uk-form-label" for="deal-currency">Currency</label><select class="uk-select uk-margin-small-top" id="deal-currency" name="currency"><?php foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'] as $currency): ?><option value="<?= $e($currency) ?>"<?= $values['currency'] === $currency ? ' selected' : '' ?>><?= $e($currency) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Currency for the deal value.</div></div></div>
          <div class="uk-grid-small uk-margin" uk-grid><div class="uk-width-1-2"><label class="uk-form-label" for="deal-probability">Probability</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><input class="uk-input" id="deal-probability" name="probability" type="number" min="0" max="100" value="<?= $e($values['probability']) ?>" placeholder="Default"><span class="uk-form-icon uk-form-icon-flip">%</span></div><div class="uk-text-meta uk-margin-small-top">Leave blank to use the stage default.</div></div><div class="uk-width-1-2"><label class="uk-form-label" for="deal-close">Expected close</label><input class="uk-input uk-margin-small-top" id="deal-close" name="expected_close_date" type="date" value="<?= $e($values['expectedCloseDate']) ?>"><div class="uk-text-meta uk-margin-small-top">Set the next realistic decision date.</div></div></div>
        </section>
      </div>
      <div class="uk-width-1-1"><section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Relationship</p><h3 class="uk-card-title uk-margin-small-top">Connect the customer</h3><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>
        <div><label class="uk-form-label" for="deal-company">Company</label><select class="uk-select uk-margin-small-top" id="deal-company" name="company_uid"><option value="">No company</option><?php foreach ($companies as $company): ?><option value="<?= $e($company->uid->toString()) ?>"<?= $values['companyUid'] === $company->uid->toString() ? ' selected' : '' ?>><?= $e($company->legalName) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">The organization buying the product or service.</div></div>
        <div><label class="uk-form-label" for="deal-contact">Primary contact</label><select class="uk-select uk-margin-small-top" id="deal-contact" name="contact_uid"><option value="">No contact</option><?php foreach ($contacts as $contact): ?><option value="<?= $e($contact->uid->toString()) ?>"<?= $values['contactUid'] === $contact->uid->toString() ? ' selected' : '' ?>><?= $e($contact->displayName) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">The person leading the customer conversation.</div></div>
        <div><label class="uk-form-label" for="deal-source">Source <span class="uk-text-meta">(optional)</span></label><input class="uk-input uk-margin-small-top" id="deal-source" name="source" value="<?= $e($values['source']) ?>" placeholder="Referral, website or campaign"><div class="uk-text-meta uk-margin-small-top">How this opportunity entered the pipeline.</div></div>
      </div></section></div>
    </div>
    <div class="uk-flex uk-flex-right uk-flex-wrap uk-grid-small uk-margin" uk-grid><div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>crm-deals/?pipeline=<?= $e(rawurlencode($values['pipelineUid'])) ?>">Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"><i class="fa fa-check"></i> <?= $deal === null ? 'Create deal' : 'Save changes' ?></button></div></div>
  </form>
<?php }; ?>

<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow"><?= $deal === null ? 'CRM · New opportunity' : 'CRM · Deal workspace' ?></p><h2><?= $e($deal?->title ?? 'Create deal') ?></h2><p><?= $deal === null ? 'Connect a customer, estimate the value and place the opportunity in the right pipeline stage.' : 'Keep the customer, commercial value, pipeline progress and next sales action in one place.' ?></p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-deals/?pipeline=<?= $e(rawurlencode($values['pipelineUid'])) ?>"><i class="fa fa-arrow-left"></i> All deals</a></div>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Deal could not be saved.</strong> <?= $e($error) ?></p></div><?php endif; ?>
  <?php if ($pipelines === []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-columns"></i><h3>A pipeline is required</h3><p>Create a pipeline before adding the first opportunity.</p><a class="uk-button uk-button-primary" href="<?= $e($adminUrl) ?>crm-pipeline/">Create pipeline</a></section>
  <?php elseif ($showInlineForm): ?>
    <?= $renderForm() ?>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom"><div class="uk-grid-medium uk-flex-middle" uk-grid><div class="uk-width-expand@m"><div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($deal->status) ?>"><?= $e($humanize($deal->status)) ?></span></div><div><span class="uk-text-meta"><?= $e($selectedPipeline?->name ?? 'Pipeline unavailable') ?> · <?= $e($stageLabel) ?></span></div></div><h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($deal->title) ?></h3><p class="uk-text-muted uk-margin-remove"><?= $e($deal->description ?: 'Add a concise description so teammates understand the customer outcome and scope.') ?></p></div><div class="uk-width-auto@m uk-text-right@m"><div class="uk-text-meta">Deal value</div><div class="uk-text-large"><strong><?= $e($money($deal->value)) ?></strong></div></div></div></section>

    <?php if ($canEdit): ?><details id="kontor-deal-editor" class="uk-margin-medium-bottom"><summary class="uk-button uk-button-default"><i class="fa fa-pencil"></i> Edit deal</summary><div class="uk-margin-small-top"><?= $renderForm() ?></div></details><?php endif; ?>

    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-line-chart"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $probability) ?>%</strong><span class="kontor-stat__label">Win probability</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-flag-checkered"></i></span><span><strong class="kontor-stat__value"><?= $e($stageLabel) ?></strong><span class="kontor-stat__label">Current stage</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-calendar"></i></span><span><strong class="kontor-stat__value"><?= $e($closeLabel) ?></strong><span class="kontor-stat__label">Expected close</span></span></div></div>
    </div>

    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l"><section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Business context</p><h3 class="uk-card-title uk-margin-small-top">Customer and opportunity</h3><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s" uk-grid>
        <div><div class="uk-text-meta">Company</div><?php if ($selectedCompany !== null && $canViewCompany): ?><a class="uk-link-reset" href="<?= $e($adminUrl) ?>company/?id=<?= $e(rawurlencode($selectedCompany->uid->toString())) ?>"><strong><i class="fa fa-building"></i> <?= $e($selectedCompany->legalName) ?></strong></a><?php else: ?><strong><i class="fa fa-building-o"></i> <?= $e($selectedCompany?->legalName ?? 'Not connected') ?></strong><?php endif; ?></div>
        <div><div class="uk-text-meta">Primary contact</div><?php if ($selectedContact !== null && $canViewContact): ?><a class="uk-link-reset" href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($selectedContact->uid->toString())) ?>"><strong><i class="fa fa-user"></i> <?= $e($selectedContact->displayName) ?></strong></a><?php else: ?><strong><i class="fa fa-user-o"></i> <?= $e($selectedContact?->displayName ?? 'Not connected') ?></strong><?php endif; ?></div>
        <div><div class="uk-text-meta">Pipeline</div><strong><i class="fa fa-columns"></i> <?= $e($selectedPipeline?->name ?? 'Unavailable') ?></strong></div><div><div class="uk-text-meta">Opportunity source</div><strong><i class="fa fa-compass"></i> <?= $e($humanize($deal->source)) ?></strong></div>
      </div><?php if (($selectedCompany !== null && $canViewCompany) || ($selectedContact !== null && $canViewContact)): ?><hr><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl . ($selectedCompany !== null && $canViewCompany ? 'company/?id=' . rawurlencode($selectedCompany->uid->toString()) : 'contact/?id=' . rawurlencode($selectedContact->uid->toString()))) ?>">Open customer workspace</a><?php endif; ?></section></div>

      <div class="uk-width-1-1 uk-width-1-3@l"><section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next step</p><h3 class="uk-card-title uk-margin-small-top"><?= $deal->status === 'won' ? 'Turn the win into an offer' : ($deal->status === 'lost' ? 'Opportunity closed' : 'Move the deal forward') ?></h3>
        <?php if ($deal->status === 'won'): ?><p class="uk-text-muted">Continue with the customer-facing quotation and sales workflow.</p><?php if ($dealQuotations !== []): ?><ul class="uk-list uk-list-divider"><?php foreach ($dealQuotations as $quotation): ?><li><a class="uk-link-reset uk-flex uk-flex-between uk-flex-middle" href="<?= $e($adminUrl) ?>sales-quotation/?id=<?= $e(rawurlencode($quotation->uid->toString())) ?>"><span><strong><?= $e($quotation->number ?? 'Draft quotation') ?></strong><span class="uk-text-meta uk-display-block uk-margin-small-top"><?= $e($humanize($quotation->status)) ?></span></span><span class="uk-button uk-button-text">Open <i class="fa fa-angle-right"></i></span></a></li><?php endforeach; ?></ul><?php endif; ?><?php if ($canCreateQuotation): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>sales-quotation/?deal=<?= $e(rawurlencode($deal->uid->toString())) ?>"><i class="fa fa-file-text-o"></i> Create quotation</a><?php endif; ?>
        <?php elseif ($deal->status === 'lost'): ?><p class="uk-text-muted"><?= $e($deal->lostReason ?: 'No loss reason was recorded.') ?></p><div class="uk-alert-warning" uk-alert><p class="uk-margin-remove">This deal is closed and no longer contributes to the active pipeline.</p></div>
        <?php else: ?><p class="uk-text-muted">Update the stage as the customer commits, then close the opportunity with a clear outcome.</p><?php if ($canMove): ?><form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>crm-deal-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="return_to" value="deal"><label class="uk-form-label" for="deal-move-stage">Move to stage</label><select class="uk-select uk-margin-small-top" id="deal-move-stage" name="stage_uid"><?php foreach ($stages as $stage): ?><?php if ($stage->stateType !== 'open') { continue; } ?><option value="<?= $e($stage->uid->toString()) ?>"<?= $stage->uid->toString() === $deal->stageUid ? ' selected' : '' ?>><?= $e($stage->displayNameIn('en') ?? $stage->nameKey) ?></option><?php endforeach; ?></select><button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top" type="submit">Update stage</button></form><?php endif; ?><div class="uk-grid-small uk-child-width-1-1<?= $canCloseWon && $canCloseLost ? ' uk-child-width-1-2@s' : '' ?>" uk-grid><?php if ($canCloseWon): ?><div><form method="post" action="<?= $e($adminUrl) ?>crm-deal-action/" data-kontor-confirm="Mark this deal as won?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>"><input type="hidden" name="return_to" value="deal"><button class="uk-button uk-button-primary uk-width-1-1" name="action" value="won" type="submit"><i class="fa fa-trophy"></i> Mark won</button></form></div><?php endif; ?><?php if ($canCloseLost): ?><div><button class="uk-button uk-button-default uk-width-1-1" type="button" uk-toggle="target: #kontor-deal-lost"><i class="fa fa-times-circle-o"></i> Mark lost</button></div><?php endif; ?></div>
        <?php endif; ?>
      </section></div>
    </div>

    <?php if ($canEdit): ?><details class="uk-margin-medium-top"><summary class="uk-button uk-button-default uk-button-small">Archive options</summary><div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-top"><h3 class="uk-card-title">Archive deal</h3><p class="uk-text-muted">Remove this deal from the active workspace while preserving its history.</p><form method="post" action="<?= $e($adminUrl) ?>crm-deal-action/" data-kontor-confirm="Archive this deal?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>"><button class="uk-button uk-button-danger" name="action" value="archive" type="submit"><i class="fa fa-archive"></i> Archive deal</button></form></div></details><?php endif; ?>
    <?php if ($canCloseLost): ?><div id="kontor-deal-lost" uk-modal><div class="uk-modal-dialog uk-modal-body"><a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Close opportunity</p><h2 class="uk-modal-title uk-margin-small-top">Mark deal as lost</h2><p class="uk-text-muted">Record a concise reason so the team can learn from the outcome.</p><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>crm-deal-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>"><input type="hidden" name="action" value="lost"><input type="hidden" name="return_to" value="deal"><label class="uk-form-label" for="deal-lost-reason">Loss reason <span class="uk-text-meta">(optional)</span></label><textarea class="uk-textarea uk-margin-small-top" id="deal-lost-reason" name="lost_reason" rows="4" placeholder="Budget postponed until next year"></textarea><div class="uk-text-meta uk-margin-small-top">Use a business reason, not internal blame.</div><div class="uk-flex uk-flex-right uk-grid-small uk-margin" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" type="submit">Mark as lost</button></div></div></form></div></div><?php endif; ?>
  <?php endif; ?>
</div>
