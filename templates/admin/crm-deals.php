<?php

/** @var \Kontor\CRM\Domain\Pipeline[] $pipelines */
/** @var \Kontor\CRM\Domain\Pipeline|null $pipeline */
/** @var array<int, array{stage: \Kontor\CRM\Domain\Stage, deals: \Kontor\CRM\Domain\Deal[]}> $columns */
/** @var string $query */
/** @var array<string, string> $customerLabels */
/** @var bool $canViewLeads */
/** @var bool $canCreateDeal */
/** @var bool $canConfigurePipeline */
/** @var bool $canMoveDeals */
/** @var bool $canViewContact */
/** @var bool $canViewCompany */
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
$customerContext = static function ($deal) use ($customerLabels, $adminUrl, $canViewContact, $canViewCompany): array {
    $type = $deal->companyUid !== null ? 'company' : ($deal->contactUid !== null ? 'contact' : '');
    $uid = $type === 'company' ? $deal->companyUid : ($type === 'contact' ? $deal->contactUid : null);
    $label = $type !== '' && $uid !== null ? ($customerLabels[$type . ':' . $uid] ?? '') : '';
    if (str_contains($label, ' · ')) {
        $label = explode(' · ', $label, 2)[1];
    }
    $canOpen = ($type === 'company' && $canViewCompany) || ($type === 'contact' && $canViewContact);

    return [
        'label' => $label !== '' ? $label : 'No customer connected',
        'url' => $canOpen && $uid !== null
            ? $adminUrl . $type . '/?id=' . rawurlencode($uid)
            : null,
    ];
};
$allDeals = [];
foreach ($columns as $column) {
    foreach ($column['deals'] as $deal) {
        $allDeals[] = $deal;
    }
}
$activeDeals = array_values(array_filter($allDeals, static fn ($deal): bool => $deal->isOpen()));
$wonDeals = array_values(array_filter($allDeals, static fn ($deal): bool => $deal->status === 'won'));
$sumByCurrency = static function (array $deals, bool $weighted = false): string {
    $totals = [];
    foreach ($deals as $deal) {
        if ($deal->value === null) {
            continue;
        }
        $currency = $deal->value->currencyCode();
        $amount = $deal->value->amountMinor();
        if ($weighted) {
            $amount = (int) round($amount * (($deal->probability ?? 0) / 100));
        }
        $totals[$currency] = ($totals[$currency] ?? 0) + $amount;
    }
    if ($totals === []) {
        return '0';
    }

    return implode(' · ', array_map(
        static fn (string $currency, int $amount): string => number_format($amount / 100, 2, '.', ',') . ' ' . $currency,
        array_keys($totals),
        array_values($totals),
    ));
};
$matches = static function ($deal) use ($query, $customerContext): bool {
    if ($query === '') {
        return true;
    }
    $customer = $customerContext($deal);
    $haystack = $deal->title . ' ' . $customer['label'] . ' ' . ($deal->description ?? '');

    return mb_stripos($haystack, $query) !== false;
};
$visibleCount = count(array_filter($allDeals, $matches));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Revenue · Opportunity pipeline</p><h2>CRM deals</h2><p>See where revenue stands, focus the right opportunities and open a deal to complete the next sales action.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <?php if ($canViewLeads): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm/"><i class="fa fa-list"></i> Leads</a><?php endif; ?>
      <?php if ($canConfigurePipeline): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-pipeline/"><i class="fa fa-cog"></i> Pipeline settings</a><?php endif; ?>
      <?php if ($pipeline !== null && $canCreateDeal): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?pipeline=<?= $e(rawurlencode($pipeline->uid->toString())) ?>"><i class="fa fa-plus"></i> New deal</a><?php endif; ?>
    </div>
  </header>

  <?php if ($pipelines !== []): ?><nav class="uk-flex uk-flex-wrap uk-grid-small uk-margin-medium-bottom" uk-grid aria-label="Deal pipelines"><?php foreach ($pipelines as $item): ?><div><a class="uk-button uk-button-small <?= $pipeline?->uid->toString() === $item->uid->toString() ? 'uk-button-primary' : 'uk-button-default' ?> uk-link-reset" href="./?pipeline=<?= $e(rawurlencode($item->uid->toString())) ?>"<?= $pipeline?->uid->toString() === $item->uid->toString() ? ' aria-current="page"' : '' ?>><?= $e($item->name) ?><?= $item->isDefault ? ' · Default' : '' ?></a></div><?php endforeach; ?></nav><?php endif; ?>

  <?php if ($pipeline === null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-columns"></i><h3>No pipeline yet</h3><p>Create a pipeline to define the stages that move opportunities from qualification to a clear outcome.</p><?php if ($canConfigurePipeline): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>crm-pipeline/">Create pipeline</a><?php endif; ?></section>
  <?php else: ?>
    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-briefcase"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($activeDeals)) ?></strong><span class="kontor-stat__label">Active opportunities</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($sumByCurrency($activeDeals)) ?></strong><span class="kontor-stat__label">Open pipeline value</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-line-chart"></i></span><span><strong class="kontor-stat__value"><?= $e($sumByCurrency($activeDeals, true)) ?></strong><span class="kontor-stat__label">Weighted forecast</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-trophy"></i></span><span><strong class="kontor-stat__value"><?= $e($sumByCurrency($wonDeals)) ?></strong><span class="kontor-stat__label">Won · <?= $e((string) count($wonDeals)) ?> deal<?= count($wonDeals) === 1 ? '' : 's' ?></span></span></div></div>
    </div>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Pipeline board</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($pipeline->name) ?></h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e((string) $visibleCount) ?> of <?= $e((string) count($allDeals)) ?> opportunities shown</p></div></div>
      <form class="uk-form-stacked uk-margin" method="get" action="./"><input type="hidden" name="pipeline" value="<?= $e($pipeline->uid->toString()) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-1-1 uk-width-expand@m"><label class="uk-form-label" for="deal-search">Search deals</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon" uk-icon="icon: search"></span><input class="uk-input" id="deal-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Search opportunity, customer or description"></div><div class="uk-text-meta uk-margin-small-top">Use search to focus this board without changing pipeline data.</div></div><div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-default uk-width-1-1" type="submit">Search</button></div><?php if ($query !== ''): ?><div class="uk-width-1-1 uk-width-auto@m"><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="./?pipeline=<?= $e(rawurlencode($pipeline->uid->toString())) ?>">Reset</a></div><?php endif; ?></div></form>
    </section>

    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@xl" uk-grid>
      <?php foreach ($columns as $column): $stage = $column['stage']; $visibleDeals = array_values(array_filter($column['deals'], $matches)); ?>
        <div><section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
          <div class="uk-flex uk-flex-between uk-flex-middle"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $stage->stateType === 'open' ? $e((string) $stage->probability) . '% stage probability' : $e($humanize($stage->stateType)) . ' outcome' ?></p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($stage->displayNameIn('en') ?? $stage->nameKey) ?></h3></div><span class="uk-badge"><?= $e((string) count($visibleDeals)) ?></span></div>
          <?php if ($visibleDeals !== []): ?><ul class="uk-list uk-list-divider uk-margin"><?php foreach ($visibleDeals as $deal): $customer = $customerContext($deal); $effectiveProbability = match ($deal->status) { 'won' => 100, 'lost' => 0, default => $deal->probability ?? $stage->probability }; ?><li>
            <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><a class="uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($deal->uid->toString())) ?>"><strong><?= $e($deal->title) ?></strong></a><div class="uk-text-meta uk-margin-small-top"><?php if ($customer['url'] !== null): ?><a class="uk-link-reset" href="<?= $e($customer['url']) ?>"><i class="fa fa-building-o"></i> <?= $e($customer['label']) ?></a><?php else: ?><i class="fa fa-building-o"></i> <?= $e($customer['label']) ?><?php endif; ?></div></div><?php if (!$deal->isOpen()): ?><div><span class="uk-label<?= $statusClass($deal->status) ?>"><?= $e($humanize($deal->status)) ?></span></div><?php endif; ?></div>
            <div class="uk-grid-small uk-child-width-1-2 uk-margin-small-top" uk-grid><div><div class="uk-text-meta">Value</div><strong><?= $e($money($deal->value)) ?></strong></div><div><div class="uk-text-meta">Probability</div><strong><?= $e((string) $effectiveProbability) ?>%</strong></div><div><div class="uk-text-meta">Expected close</div><strong><?= $e($deal->expectedCloseDate?->format('M j, Y') ?? 'Not scheduled') ?></strong></div><div class="uk-flex uk-flex-bottom uk-flex-right"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($deal->uid->toString())) ?>">Open</a></div></div>
            <?php if ($deal->isOpen() && $canMoveDeals): ?><form class="uk-form-stacked uk-margin-small-top" method="post" action="<?= $e($adminUrl) ?>crm-deal-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>"><input type="hidden" name="action" value="move"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-expand"><label class="uk-form-label uk-text-meta" for="deal-stage-<?= $e($deal->uid->toString()) ?>">Move to stage</label><select class="uk-select uk-form-small uk-margin-small-top" id="deal-stage-<?= $e($deal->uid->toString()) ?>" name="stage_uid"><?php foreach ($columns as $target): ?><?php if ($target['stage']->stateType !== 'open') { continue; } ?><option value="<?= $e($target['stage']->uid->toString()) ?>"<?= $target['stage']->uid->toString() === $deal->stageUid ? ' selected' : '' ?>><?= $e($target['stage']->displayNameIn('en') ?? $target['stage']->nameKey) ?></option><?php endforeach; ?></select></div><div><button class="uk-button uk-button-default uk-button-small" type="submit">Move</button></div></div></form><?php endif; ?>
          </li><?php endforeach; ?></ul><?php else: ?><div class="uk-text-center uk-padding-small"><span class="fa fa-inbox fa-2x uk-text-muted"></span><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $query !== '' ? 'No matching deals in this stage.' : 'No deals in this stage yet.' ?></p></div><?php endif; ?>
        </section></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
