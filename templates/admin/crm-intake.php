<?php

/** @var \Kontor\CRMIntake\Domain\IntakeProfile|null $profile */
/** @var bool $settingsReady */
/** @var bool $canManage */
/** @var string $adminUrl */
/** @var callable $e */

$humanize = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$entityLabels = ['contact' => 'Contacts', 'lead' => 'Leads', 'deal' => 'Deals'];
$typeLabels = [
    'text' => 'Short text',
    'textarea' => 'Long text',
    'url' => 'Website address',
    'select' => 'Single choice',
    'multiselect' => 'Multiple choice',
    'datetime' => 'Date and time',
];
$groups = [];
foreach ($profile?->fields ?? [] as $field) {
    $groups[$field['group'] ?: 'Qualification'][] = $field;
}
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">CRM · Qualification settings</p>
      <h2><?= $e($profile?->name ?? 'CRM intake profile') ?></h2>
      <p>See exactly which customer and project information is collected before an opportunity moves into the deal pipeline.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm/"><i class="fa fa-arrow-left"></i> CRM</a>
      <?php if ($settingsReady && $canManage): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>settings-migration/"><i class="fa fa-exchange"></i> Import or export</a><?php endif; ?>
    </div>
  </header>

  <section class="kontor-section-intro uk-margin-bottom" aria-label="About this section">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <div><strong>One profile, connected across the customer workflow</strong><p>Contact fields describe the person. Lead fields qualify the request. Shared Lead and Deal fields follow the opportunity automatically during conversion.</p></div>
  </section>

  <?php if ($profile === null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center"><i class="fa fa-list-alt fa-2x uk-text-muted"></i><h3>No active intake profile</h3><p class="uk-text-muted">Import or configure a profile before organization-specific fields appear in CRM forms.</p></section>
  <?php else: ?>
    <div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-list"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($profile->fields)) ?></strong><span class="kontor-stat__label">Configured fields</span></span></div></div>
      <?php foreach ($entityLabels as $entity => $label): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-<?= $e($entity === 'contact' ? 'user' : ($entity === 'lead' ? 'bullseye' : 'handshake-o')) ?>"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count(array_filter($profile->fields, static fn (array $field): bool => in_array($entity, $field['targets'], true)))) ?></strong><span class="kontor-stat__label"><?= $e($label) ?></span></span></div></div><?php endforeach; ?>
    </div>

    <?php foreach ($groups as $group => $fields): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-bottom">
        <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Profile section</p><h3><?= $e($group) ?></h3><p class="uk-text-meta uk-margin-small-top"><?= $e((string) count($fields)) ?> field<?= count($fields) === 1 ? '' : 's' ?> in this section.</p></div></header>
        <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@l" uk-grid>
          <?php foreach ($fields as $field): ?>
            <div><article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small kontor-intake-fieldhead" uk-grid><div class="uk-width-expand"><h4 class="uk-margin-remove"><?= $e($field['label']) ?><?= $field['required'] ? ' *' : '' ?></h4><p class="uk-text-meta uk-margin-small-top"><?= $e($typeLabels[$field['type']] ?? $humanize($field['type'])) ?></p></div><div><div class="uk-flex uk-flex-wrap uk-flex-right uk-grid-small" uk-grid><?php foreach ($field['targets'] as $target): ?><div><span class="uk-label"><?= $e($entityLabels[$target] ?? $humanize($target)) ?></span></div><?php endforeach; ?></div></div></div>
              <?php if ($field['description'] !== ''): ?><p><?= $e($field['description']) ?></p><?php endif; ?>
              <?php if ($field['options'] !== []): ?><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><?php foreach ($field['options'] as $optionLabel): ?><div><span class="uk-badge"><?= $e($optionLabel) ?></span></div><?php endforeach; ?></div><?php endif; ?>
              <?php if ($field['note'] !== ''): ?><p class="uk-text-meta uk-margin-small-top"><strong>Guidance:</strong> <?= $e($field['note']) ?></p><?php endif; ?>
              <?php if (($field['binding'] ?? null) === 'source'): ?><p class="uk-text-meta uk-margin-small-top"><i class="fa fa-link"></i> Used as the CRM opportunity source.</p><?php endif; ?>
            </article></div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
