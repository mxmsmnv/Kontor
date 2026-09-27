<?php

/** @var \Kontor\Projects\Domain\Project[] $projects */
/** @var \Kontor\Projects\Domain\Project[] $allProjects */
/** @var array<string, array<string, mixed>> $projectContexts */
/** @var string $query */
/** @var string $selectedStatus */
/** @var string[] $statuses */
/** @var bool $canCreate */
/** @var bool $canViewTasks */
/** @var bool $canViewInvoices */
/** @var string $adminUrl */
/** @var callable $e */

$today = new DateTimeImmutable('today');
$activeCount = count(array_filter($allProjects, static fn ($project): bool => $project->isActive()));
$openMilestones = $overdueMilestones = $trackedMinutes = $unbilledMinutes = 0;
$unbilledByCurrency = [];
foreach ($projectContexts as $context) {
    $openMilestones += $context['milestoneCount'] - $context['completedMilestones'];
    $overdueMilestones += $context['overdueMilestones'];
    $trackedMinutes += $context['trackedMinutes'];
    $unbilledMinutes += $context['unbilledMinutes'];
    foreach ($context['unbilledByCurrency'] as $currency => $amountMinor) {
        $unbilledByCurrency[$currency] = ($unbilledByCurrency[$currency] ?? 0) + $amountMinor;
    }
}
ksort($unbilledByCurrency);
$hours = static fn (int $minutes): string => $minutes === 0
    ? '0 h'
    : number_format($minutes / 60, $minutes % 60 === 0 ? 0 : 1, '.', ',') . ' h';
$moneySummary = static function (array $amounts): string {
    if ($amounts === []) {
        return '—';
    }
    $currency = array_key_first($amounts);
    $summary = number_format($amounts[$currency] / 100, 2, '.', ',') . ' ' . $currency;

    return count($amounts) > 1 ? $summary . ' +' . (count($amounts) - 1) : $summary;
};
$statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = static fn (string $status): string => match ($status) {
    'active' => ' uk-label-success',
    'cancelled', 'on_hold' => ' uk-label-warning',
    default => '',
};
$filtersActive = $query !== '' || $selectedStatus !== '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Delivery</p><h2>Projects</h2><p>Plan delivery, capture time and billable work, then turn completed work into a reviewable invoice.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><?php if ($canViewTasks): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-check-square-o"></i> Tasks</a><?php endif; ?><?php if ($canViewInvoices): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-file-text-o"></i> Invoices</a><?php endif; ?><?php if ($canCreate): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>project/"><i class="fa fa-plus"></i> New project</a><?php endif; ?></div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-folder-open"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $activeCount) ?></strong><span class="kontor-stat__label">Active projects</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $overdueMilestones > 0 ? ' kontor-stat__icon--danger' : '' ?>"><i class="fa fa-flag-checkered"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $openMilestones) ?></strong><span class="kontor-stat__label"><?= $overdueMilestones > 0 ? $e((string) $overdueMilestones) . ' overdue milestone' . ($overdueMilestones === 1 ? '' : 's') : 'Open milestones' ?></span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e($hours($trackedMinutes)) ?></strong><span class="kontor-stat__label"><?= $unbilledMinutes > 0 ? $e($hours($unbilledMinutes)) . ' ready to bill' : 'Tracked delivery time' ?></span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $unbilledByCurrency !== [] ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($moneySummary($unbilledByCurrency)) ?></strong><span class="kontor-stat__label"><?= $unbilledByCurrency !== [] ? 'Unbilled project value' : 'Nothing waiting to bill' ?></span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3><p>Projects connect a customer to delivery milestones, time entries and billable items. Open a project to plan the work, record delivery and create a draft invoice when the customer is ready to be billed.</p></div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Delivery portfolio</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">All projects</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Find the work that needs attention and open it to manage delivery.</p></div><div><span class="uk-label"><?= $e((string) count($allProjects)) ?> total</span></div></div>
        <form class="uk-form-stacked uk-margin-medium-top" method="get" action="<?= $e($adminUrl) ?>projects/">
          <div class="uk-grid-small uk-flex-bottom" uk-grid>
            <div class="uk-width-1-1 uk-width-expand@s"><label class="uk-form-label" for="projects-search">Find a project</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon"><i class="fa fa-search"></i></span><input class="uk-input" id="projects-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Project, code or customer" aria-describedby="projects-search-help"></div><div class="uk-text-meta uk-margin-small-top" id="projects-search-help">Search by a customer-facing name, project code or connected customer.</div></div>
            <div class="uk-width-1-1 uk-width-1-3@s"><label class="uk-form-label" for="projects-status">Status</label><select class="uk-select uk-margin-small-top" id="projects-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e($statusLabel($status)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Narrow the portfolio without hiding billing signals.</div></div>
            <div class="uk-width-1-1 uk-width-auto@s"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Apply</button></div>
            <?php if ($filtersActive): ?><div class="uk-width-1-1 uk-width-auto@s"><a class="uk-button uk-button-default uk-link-reset uk-width-1-1" href="<?= $e($adminUrl) ?>projects/">Clear</a></div><?php endif; ?>
          </div>
        </form>

        <?php if ($projects !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top">
          <?php foreach ($projects as $project): ?><?php
          $projectUid = $project->uid->toString();
          $context = $projectContexts[$projectUid];
          $projectUrl = $adminUrl . 'project/?id=' . rawurlencode($projectUid);
          $progress = $context['milestoneCount'] > 0
              ? (int) round(($context['completedMilestones'] / $context['milestoneCount']) * 100)
              : null;
          $dateLabel = $project->startDate !== null || $project->endDate !== null
              ? ($project->startDate?->format('M j, Y') ?? 'Start not set') . ' – ' . ($project->endDate?->format('M j, Y') ?? 'Ongoing')
              : 'Dates not planned';
          $isOverdue = $project->isActive() && $project->endDate !== null && $project->endDate < $today;
          ?><li>
            <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><a class="uk-link-reset" href="<?= $e($projectUrl) ?>"><strong><i class="fa fa-folder-open-o uk-margin-small-right"></i><?= $e($project->name) ?></strong></a><div class="uk-text-meta uk-margin-small-top"><?= $e($project->code) ?> · <span class="<?= $isOverdue ? 'uk-text-danger' : '' ?>"><?= $e($dateLabel) ?><?= $isOverdue ? ' · Overdue' : '' ?></span></div></div><div><span class="uk-label<?= $statusClass($project->status) ?>"><?= $e($statusLabel($project->status)) ?></span></div></div>
            <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s uk-margin-small-top" uk-grid>
              <div><span class="uk-text-meta">Customer</span><div class="uk-margin-small-top"><?php if ($context['customerLabel'] !== null && $context['customerRoute'] !== null): ?><a class="uk-link-reset" href="<?= $e($adminUrl . $context['customerRoute']) ?>"><strong><?= $e($context['customerLabel']) ?></strong></a><?php elseif ($context['customerLabel'] !== null): ?><strong><?= $e($context['customerLabel']) ?></strong><?php else: ?><span class="uk-text-muted">Not connected</span><?php endif; ?></div></div>
              <div><span class="uk-text-meta">Milestones</span><div class="uk-margin-small-top"><strong><?= $progress !== null ? $e((string) $progress) . '% complete' : 'Not planned' ?></strong><?php if ($context['overdueMilestones'] > 0): ?><span class="uk-text-danger"> · <?= $e((string) $context['overdueMilestones']) ?> overdue</span><?php endif; ?></div><?php if ($progress !== null): ?><progress class="uk-progress uk-margin-small-top uk-margin-remove-bottom" value="<?= $e((string) $progress) ?>" max="100"></progress><?php endif; ?></div>
              <div><span class="uk-text-meta">Delivery & billing</span><div class="uk-margin-small-top"><strong><?= $e($hours($context['trackedMinutes'])) ?></strong> tracked<?php if ($context['runningTimers'] > 0): ?><span class="uk-text-success"> · timer running</span><?php endif; ?></div><div class="uk-text-meta uk-margin-small-top"><?= $context['unbilledByCurrency'] !== [] ? $e($moneySummary($context['unbilledByCurrency'])) . ' unbilled' : 'No unbilled value' ?></div></div>
            </div>
            <div class="uk-flex uk-flex-right uk-margin-small-top"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($projectUrl) ?>">Open project <i class="fa fa-angle-right"></i></a></div>
          </li><?php endforeach; ?>
        </ul>
        <?php elseif ($filtersActive): ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching projects</h4><p class="uk-text-muted">Try another project name, code, customer or status.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>projects/">Clear filters</a></div>
        <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-folder-open-o fa-2x uk-text-muted"></i><h4>No projects yet</h4><p class="uk-text-muted">Create the first project, connect its customer and add milestones before delivery begins.</p><?php if ($canCreate): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>project/"><i class="fa fa-plus"></i> New project</a><?php endif; ?></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Guided workflow</p><h3 class="uk-card-title uk-margin-small-top">From plan to invoice</h3><p class="uk-text-muted">Keep delivery and billing together so completed work does not get lost between tools.</p>
        <ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Plan delivery</strong><br><span class="uk-text-meta">Connect the customer, dates and milestones.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Capture the work</strong><br><span class="uk-text-meta">Log time and fixed billable items as the project moves.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Prepare billing</strong><br><span class="uk-text-meta">Generate a draft invoice and review it before issue.</span></span></div></li></ol>
        <?php if ($canCreate): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset uk-margin-top" href="<?= $e($adminUrl) ?>project/"><i class="fa fa-plus"></i> Start a project</a><?php endif; ?>
      </section>
    </div>
  </div>
</div>
