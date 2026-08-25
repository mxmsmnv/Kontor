<?php

/** @var array<string, \Kontor\SDK\Contracts\ReportProviderInterface> $providers */
/** @var string $providerKey */
/** @var \Kontor\SDK\Contracts\ReportProviderInterface|null $provider */
/** @var array<string, string> $filters */
/** @var string[] $groupBy */
/** @var \Kontor\SDK\DTO\ReportResult|null $result */
/** @var array<string, array<string, string>> $fieldOptions */
/** @var array<string, string> $fieldLabels */
/** @var string $currencyCode */
/** @var \Kontor\Reports\Domain\ScheduledReport[] $schedules */
/** @var bool $canManageSchedules */
/** @var bool $canExport */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$schema = $provider?->schema();
$labelFor = static function (string $field) use ($fieldLabels): string {
    if (isset($fieldLabels[$field])) {
        return $fieldLabels[$field];
    }
    $label = preg_replace('/_(uid|minor)$/', '', $field) ?? $field;

    return ucwords(str_replace('_', ' ', $label));
};
$descriptionFor = static fn (string $field): string => match ($field) {
    'pipeline_uid' => 'Limit the report to one sales process, or leave it open to include every pipeline.',
    'status' => 'Focus the result on open, won or lost opportunities.',
    default => 'Limit the report using this business value. Leave it open to include every match.',
};
$allLabel = static fn (string $field) => match ($field) {
    'pipeline_uid' => 'All sales pipelines',
    'status' => 'All deal statuses',
    default => 'All ' . strtolower($labelFor($field)),
};
$formatValue = static function (mixed $value, ?string $type, string $field) use ($fieldOptions, $currencyCode): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if (isset($fieldOptions[$field][(string) $value])) {
        return $fieldOptions[$field][(string) $value];
    }
    if ($type === 'money' && is_numeric($value)) {
        return number_format(((int) $value) / 100, 2, '.', ',') . ' ' . $currencyCode;
    }

    return (string) $value;
};
$providerTitle = static fn (string $key): string => isset($providers[$key])
    ? $providers[$key]->title()
    : ucwords(str_replace(['_', '-'], ' ', $key));
$filtersActive = $filters !== [] || $groupBy !== [];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Insights · Operational reporting</p><h2>Reports</h2><p>Turn live business data into a focused view, export or recurring delivery.</p></div>
  </header>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle uk-margin-small-right"></i>About this workspace</h3><p>Choose a report, narrow the business question and review the result before exporting or scheduling it. Reports include only data available to your organization and installed components.</p></div>

  <?php if ($providers === []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><i class="fa fa-bar-chart fa-2x uk-text-muted"></i><h3>No reports available</h3><p class="uk-text-muted">Enable a component that contributes reporting views, then return here.</p></div></section>
  <?php else: ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="get" action="./">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Report builder</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Ask a business question</h3><p class="uk-text-muted uk-margin-small-top">Start broad, then add only the filters needed to make the result actionable.</p></div><div><span class="uk-label"><?= $e((string) count($providers)) ?> report<?= count($providers) === 1 ? '' : 's' ?></span></div></div>

          <fieldset class="uk-fieldset uk-margin-medium-top">
            <legend class="uk-legend">1. Choose the report</legend>
            <p class="uk-text-muted uk-margin-small-top">Installed components contribute reports for the work they understand.</p>
            <label class="kontor-nativefield"><span>Report *</span><select name="provider" aria-label="Report" required><?php foreach ($providers as $key => $candidate): ?><option value="<?= $e($key) ?>"<?= $key === $providerKey ? ' selected' : '' ?>><?= $e($candidate->title()) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The business view you want to analyze.</span><span class="kontor-field-note"><strong>Note:</strong> Changing the report also changes the available filters and columns.</span></span></label>
          </fieldset>

          <?php if ($schema !== null): ?>
            <hr class="uk-margin-medium">
            <fieldset class="uk-fieldset">
              <legend class="uk-legend">2. Focus the result</legend>
              <p class="uk-text-muted uk-margin-small-top">All filters are optional. Leave them open for the complete organization view.</p>
              <?php if ($schema->filterableFields !== []): ?><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" uk-grid><?php foreach ($schema->filterableFields as $field): ?><label class="kontor-nativefield"><span><?= $e($labelFor($field)) ?></span><?php if (isset($fieldOptions[$field])): ?><select name="filter_<?= $e($field) ?>"><option value=""><?= $e($allLabel($field)) ?></option><?php foreach ($fieldOptions[$field] as $value => $label): ?><option value="<?= $e($value) ?>"<?= ($filters[$field] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><?php else: ?><input name="filter_<?= $e($field) ?>" value="<?= $e($filters[$field] ?? '') ?>" placeholder="All"><?php endif; ?><span class="kontor-field-guidance"><span class="kontor-field-description"><?= $e($descriptionFor($field)) ?></span><span class="kontor-field-note"><strong>Note:</strong> Leave open when this distinction is not needed.</span></span></label><?php endforeach; ?></div><?php else: ?><div class="uk-alert-primary" uk-alert><p>This report already has a focused scope and needs no filters.</p></div><?php endif; ?>
            </fieldset>

            <?php if ($schema->groupableFields !== []): ?><hr class="uk-margin-medium"><fieldset class="uk-fieldset"><legend class="uk-legend">3. Organize the answer</legend><p class="uk-text-muted uk-margin-small-top">Choose how rows should be brought together for comparison.</p><label class="kontor-nativefield"><span>Group results by</span><select name="group_by" aria-label="Group results by"><option value="">Report default</option><?php foreach ($schema->groupableFields as $field): ?><option value="<?= $e($field) ?>"<?= in_array($field, $groupBy, true) ? ' selected' : '' ?>><?= $e($labelFor($field)) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The business dimension used to separate the result into comparable rows.</span><span class="kontor-field-note"><strong>Note:</strong> Keep the default when you only need the overall result.</span></span></label></fieldset><?php endif; ?>
          <?php endif; ?>

          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" uk-grid><div><?php if ($filtersActive): ?><a class="uk-button uk-button-default uk-link-reset" href="./?provider=<?= $e(rawurlencode($providerKey)) ?>">Reset</a><?php endif; ?></div><div><button class="uk-button uk-button-primary" type="submit" name="run" value="1"><i class="fa fa-play"></i> Run report</button></div></div>
        </form>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <aside class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Working method</p><h3 class="uk-card-title uk-margin-small-top">Build, review, share</h3><ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Choose the question</strong><br><span class="uk-text-meta">Select the report closest to the decision you need to make.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Review the result</strong><br><span class="uk-text-meta">Check the scope and totals before sharing or scheduling.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Deliver when useful</strong><br><span class="uk-text-meta">Export once or create a recurring private delivery.</span></span></div></li></ol></aside>
        <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Current selection</p><h3 class="uk-card-title uk-margin-small-top"><?= $e($provider?->title() ?? 'Report') ?></h3><ul class="uk-list uk-list-divider"><li><strong><?= $e((string) count($filters)) ?></strong><div class="uk-text-meta uk-margin-small-top">active filter<?= count($filters) === 1 ? '' : 's' ?></div></li><li><strong><?= $groupBy !== [] ? $e($labelFor($groupBy[0])) : 'Default' ?></strong><div class="uk-text-meta uk-margin-small-top">result grouping</div></li><li><strong><?= $result !== null ? $e((string) count($result->rows)) : 'Not run' ?></strong><div class="uk-text-meta uk-margin-small-top">rows in the current result</div></li></ul></aside>
      </div>
    </div>

    <?php if ($result !== null && $schema !== null): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Report result</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($provider?->title() ?? $providerKey) ?></h3><p class="uk-text-muted uk-margin-small-top"><?= $e((string) count($result->rows)) ?> matching row<?= count($result->rows) === 1 ? '' : 's' ?>. Review the scope before exporting.</p></div><?php if ($canExport && $result->rows !== []): ?><div><form method="post" action="<?= $e($adminUrl) ?>reports-export/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="provider" value="<?= $e($providerKey) ?>"><input type="hidden" name="filters_json" value="<?= $e(json_encode($filters, JSON_THROW_ON_ERROR)) ?>"><input type="hidden" name="group_by_json" value="<?= $e(json_encode($groupBy, JSON_THROW_ON_ERROR)) ?>"><button class="uk-button uk-button-default" type="submit"><i class="fa fa-download"></i> Export CSV</button></form></div><?php endif; ?></div>
        <?php if ($result->rows !== []): ?><div class="uk-overflow-auto uk-margin-medium-top"><table class="uk-table uk-table-divider uk-table-middle uk-table-small"><thead><tr><?php foreach ($schema->fields as $field => $type): ?><th><?= $e($labelFor($field)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($result->rows as $row): ?><tr><?php foreach ($schema->fields as $field => $type): ?><td><?= $e($formatValue($row[$field] ?? null, $type, $field)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody><?php if ($result->totals !== []): ?><tfoot><tr><?php foreach ($schema->fields as $field => $type): ?><td><?php if (array_key_exists($field, $result->totals)): ?><strong><?= $e($formatValue($result->totals[$field], $type, $field)) ?></strong><?php elseif ($field === array_key_first($schema->fields)): ?><strong>Total</strong><?php else: ?>—<?php endif; ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?></table></div><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching data</h4><p class="uk-text-muted">The report ran successfully. Broaden the filters to include more records.</p></div><?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Recurring delivery</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Scheduled reports</h3><p class="uk-text-muted uk-margin-small-top">Completed reports are delivered privately to Files at the chosen interval.</p></div><div><span class="uk-label"><?= $e((string) count($schedules)) ?> schedule<?= count($schedules) === 1 ? '' : 's' ?></span></div></div>
      <?php if ($schedules !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($schedules as $schedule): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><i class="fa fa-clock-o uk-margin-small-right"></i><?= $e($schedule->name) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($providerTitle($schedule->providerKey)) ?> · <?= $e(ucfirst($schedule->recurrenceRule)) ?> · <?= $e(strtoupper($schedule->format)) ?></div></div><div class="uk-text-right@m"><strong>Next: <?= $e($schedule->nextRunAt->format('M j, Y · H:i')) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $schedule->lastRunAt !== null ? 'Last delivered ' . $e($schedule->lastRunAt->format('M j, Y · H:i')) : 'Awaiting first delivery' ?></div></div><?php if ($canManageSchedules): ?><div><form method="post" action="<?= $e($adminUrl) ?>reports-schedule-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="uid" value="<?= $e($schedule->uid->toString()) ?>"><button class="uk-button uk-button-default uk-button-small" type="submit">Archive</button></form></div><?php endif; ?></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-clock-o fa-2x uk-text-muted"></i><h4>No scheduled reports</h4><p class="uk-text-muted">Create a schedule only when this report is useful on a recurring basis.</p></div><?php endif; ?>

      <?php if ($canManageSchedules && $provider !== null): ?><ul class="uk-margin-medium-top" uk-accordion><li><a class="uk-accordion-title uk-link-reset" href>Automate the current report</a><div class="uk-accordion-content"><p class="uk-text-muted">The current filters and grouping will be saved with the schedule.</p><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>reports-schedule/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="provider" value="<?= $e($providerKey) ?>"><input type="hidden" name="filters_json" value="<?= $e(json_encode($filters, JSON_THROW_ON_ERROR)) ?>"><input type="hidden" name="group_by_json" value="<?= $e(json_encode($groupBy, JSON_THROW_ON_ERROR)) ?>"><div class="uk-grid-small" uk-grid><label class="kontor-nativefield uk-width-1-1 uk-width-1-2@m"><span>Schedule name *</span><input name="name" required maxlength="191" value="<?= $e(($provider?->title() ?? 'Report') . ' schedule') ?>"><span class="kontor-field-guidance"><span class="kontor-field-description">A clear name teammates can recognize in the delivery list.</span><span class="kontor-field-note"><strong>Note:</strong> Include the audience or purpose when several schedules use this report.</span></span></label><label class="kontor-nativefield uk-width-1-1 uk-width-1-4@m"><span>Frequency *</span><select name="recurrence" aria-label="Frequency"><?php foreach (['daily', 'weekly', 'monthly', 'yearly'] as $recurrence): ?><option value="<?= $e($recurrence) ?>"><?= $e(ucfirst($recurrence)) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">How often a fresh report should be delivered.</span><span class="kontor-field-note"><strong>Note:</strong> Choose the slowest frequency that still supports the decision.</span></span></label><label class="kontor-nativefield uk-width-1-1 uk-width-1-4@m"><span>Format *</span><select name="format" aria-label="Format"><?php foreach (['csv', 'xlsx', 'pdf', 'json'] as $format): ?><option value="<?= $e($format) ?>"><?= $e(strtoupper($format)) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The file format stored for each delivery.</span><span class="kontor-field-note"><strong>Note:</strong> CSV and XLSX are best for analysis; PDF is best for review.</span></span></label><label class="kontor-nativefield uk-width-1-1 uk-width-1-2@m"><span>First delivery *</span><input type="datetime-local" name="first_run_at" required value="<?= $e((new DateTimeImmutable('+5 minutes'))->format('Y-m-d\TH:i')) ?>"><span class="kontor-field-guidance"><span class="kontor-field-description">The local date and time when recurring delivery begins.</span><span class="kontor-field-note"><strong>Note:</strong> Later deliveries follow the selected frequency from this point.</span></span></label></div><div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-clock-o"></i> Create schedule</button></div></form></div></li></ul><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
