<?php

/** @var array<int, array<string, mixed>> $jobs */
/** @var array<string, int> $counts */
/** @var string[] $queues */
/** @var string|null $selectedQueue */
/** @var string|null $selectedStatus */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalJobs */
/** @var bool $canCancelJobs */
/** @var bool $canRetryJobs */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$total = array_sum($counts);
$active = ($counts['pending'] ?? 0) + ($counts['reserved'] ?? 0);
$attention = $counts['dead'] ?? 0;
$filtersActive = $selectedQueue !== null || $selectedStatus !== null;
$allSelected = !$filtersActive;
$activeSelected = $selectedQueue === null && $selectedStatus === 'active';
$completedSelected = $selectedQueue === null && $selectedStatus === 'completed';
$attentionSelected = $selectedQueue === null && $selectedStatus === 'dead';
$pageUrl = static function (int $targetPage) use ($selectedQueue, $selectedStatus): string {
    $query = http_build_query(array_filter([
        'queue' => $selectedQueue,
        'status' => $selectedStatus,
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $query === '' ? './' : './?' . $query;
};
$filterUrl = static function (?string $queue, ?string $status): string {
    $query = http_build_query(array_filter([
        'queue' => $queue,
        'status' => $status,
    ], static fn (?string $value): bool => $value !== null && $value !== ''));

    return $query === '' ? './' : './?' . $query;
};
$refreshUrl = $pageUrl($page);
$humanize = static function (string $value): string {
    $segments = preg_split('/[\\\\.\/]+/', $value) ?: [$value];
    $value = (string) end($segments);

    return ucwords(trim((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|[_-]+/', ' ', $value)));
};
$statusLabel = static fn (string $status): string => match ($status) {
    'pending' => 'Waiting',
    'reserved' => 'Running',
    'completed' => 'Completed',
    'dead' => 'Needs attention',
    'cancelled' => 'Cancelled',
    default => ucwords(str_replace('_', ' ', $status)),
};
$statusClass = static fn (string $status): string => match ($status) {
    'pending', 'reserved' => ' uk-label-warning',
    'completed' => ' uk-label-success',
    'dead' => ' uk-label-danger',
    default => '',
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Background work</p><h2>Queue</h2><p>Follow work handled in the background and resolve only the jobs that need attention.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($refreshUrl) ?>"><i class="fa fa-refresh"></i> Refresh status</a></div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= $allSelected ? ' uk-box-shadow-medium' : '' ?>" href="./"<?= $allSelected ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon"><i class="fa fa-list"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $total) ?></strong><span class="kontor-stat__label">All background jobs</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= $activeSelected ? ' uk-box-shadow-medium' : '' ?>" href="./?status=active"<?= $activeSelected ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon<?= $active > 0 ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $active) ?></strong><span class="kontor-stat__label">Waiting or running</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= $completedSelected ? ' uk-box-shadow-medium' : '' ?>" href="./?status=completed"<?= $completedSelected ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon<?= ($counts['completed'] ?? 0) > 0 ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-check"></i></span><span><strong class="kontor-stat__value"><?= $e((string) ($counts['completed'] ?? 0)) ?></strong><span class="kontor-stat__label">Completed</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= $attentionSelected ? ' uk-box-shadow-medium' : '' ?>" href="./?status=dead"<?= $attentionSelected ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon<?= $attention > 0 ? ' kontor-stat__icon--danger' : '' ?>"><i class="fa fa-exclamation-triangle"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $attention) ?></strong><span class="kontor-stat__label">Needs attention</span></span></a></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle uk-margin-small-right"></i>About this workspace</h3><p>Kontor uses the queue for work that should not hold up a person, such as imports, exports, document generation and notifications. A completed job needs no action; retry a failed job only after its cause has been addressed.</p></div>

  <?php if ($total > 0 || $filtersActive): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <form class="uk-form-stacked" method="get" action="./"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="queue-name">Work area</label><select class="uk-select uk-margin-small-top" id="queue-name" name="queue"><option value="">All work areas</option><?php foreach ($queues as $queue): ?><option value="<?= $e($queue) ?>"<?= $selectedQueue === $queue ? ' selected' : '' ?>><?= $e($humanize($queue)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Limit the view to one background workflow.</div></div><div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="queue-status">State</label><select class="uk-select uk-margin-small-top" id="queue-status" name="status"><option value="">All states</option><?php foreach (['active', 'pending', 'reserved', 'completed', 'dead', 'cancelled'] as $status): ?><option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e($status === 'active' ? 'Waiting or running' : $statusLabel($status)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Use “Needs attention” to focus recovery work.</div></div><div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Apply</button></div><?php if ($filtersActive): ?><div class="uk-width-1-1 uk-width-auto@m"><a class="uk-button uk-button-default uk-link-reset uk-width-1-1" href="./">Clear</a></div><?php endif; ?><div class="uk-width-1-1 uk-width-expand@m uk-text-right@m"><span class="uk-text-meta"><?= $e((string) $totalJobs) ?> matching · <?= $e((string) count($jobs)) ?> on this page</span></div></div></form>
    </section>
  <?php endif; ?>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Operational view</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $filtersActive ? 'Matching jobs' : 'Recent background work' ?></h3><p class="uk-text-muted uk-margin-small-top">Newest work appears first, with the current state and next available action.</p></div><div><span class="uk-label"><?= $e((string) $totalJobs) ?> job<?= $totalJobs === 1 ? '' : 's' ?></span></div></div>

        <?php if ($jobs !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($jobs as $job): ?><?php $status = (string) $job['status']; $progress = max(0, min(100, (int) $job['progress'])); ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><i class="fa fa-cog uk-margin-small-right"></i><?= $e($humanize((string) $job['job_type'])) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($humanize((string) $job['queue'])) ?> · Created <?= $e((new DateTimeImmutable((string) $job['created_at']))->format('M j, Y · H:i')) ?></div></div><div class="uk-text-right@m"><span class="uk-label<?= $statusClass($status) ?>"><?= $e($statusLabel($status)) ?></span><div class="uk-text-meta uk-margin-small-top">Attempt <?= $e((string) max(1, (int) $job['attempts'])) ?> of <?= $e((string) max(1, (int) $job['max_attempts'])) ?></div></div></div><?php if (in_array($status, ['pending', 'reserved'], true)): ?><div class="uk-margin-small-top"><progress class="uk-progress uk-margin-small-bottom" value="<?= $e((string) $progress) ?>" max="100"></progress><span class="uk-text-meta"><?= $status === 'pending' ? 'Waiting to start' : $e((string) $progress) . '% complete' ?></span></div><?php endif; ?><?php if ($status === 'dead'): ?><div class="uk-alert-danger uk-margin-small-top" uk-alert><p><strong>This job could not finish.</strong><br>Address the related component or input before returning it to the queue.</p></div><?php endif; ?><?php if (($status === 'pending' && $canCancelJobs) || ($status === 'dead' && $canRetryJobs)): ?><div class="uk-flex uk-flex-right uk-margin-small-top"><?php if ($status === 'pending' && $canCancelJobs): ?><form method="post" action="<?= $e($adminUrl) ?>queue-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="uid" value="<?= $e((string) $job['uid']) ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><i class="fa fa-ban"></i> Cancel waiting job</button></form><?php elseif ($status === 'dead' && $canRetryJobs): ?><form method="post" action="<?= $e($adminUrl) ?>queue-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="action" value="retry"><input type="hidden" name="uid" value="<?= $e((string) $job['uid']) ?>"><button class="uk-button uk-button-primary uk-button-small" type="submit"><i class="fa fa-repeat"></i> Retry job</button></form><?php endif; ?></div><?php endif; ?></li><?php endforeach; ?></ul>
        <?php elseif ($filtersActive): ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching jobs</h4><p class="uk-text-muted">Try another work area or state.</p><a class="uk-button uk-button-default uk-link-reset" href="./">Clear filters</a></div>
        <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-check-circle fa-2x uk-text-success"></i><h4>Queue is clear</h4><p class="uk-text-muted">There is no background work waiting, running or requiring attention.</p></div><?php endif; ?>
      </section>

      <?php if ($totalPages > 1): ?><nav class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" aria-label="Queue pages" uk-grid><div><span class="uk-text-meta">Page <?= $e((string) $page) ?> of <?= $e((string) $totalPages) ?></span></div><div class="uk-flex uk-flex-middle uk-grid-small" uk-grid><?php if ($page > 1): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page - 1)) ?>"><i class="fa fa-chevron-left"></i> Previous</a></div><?php endif; ?><?php if ($page < $totalPages): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page + 1)) ?>">Next <i class="fa fa-chevron-right"></i></a></div><?php endif; ?></div></nav><?php endif; ?>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <aside class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">How to respond</p><h3 class="uk-card-title uk-margin-small-top"><?= $attention > 0 ? 'Recover failed work first' : ($active > 0 ? 'Let active work finish' : 'No action needed') ?></h3><p class="uk-text-muted"><?= $attention > 0 ? 'Review the affected business area before retrying. Repeated retries without a fix can hide the real cause.' : ($active > 0 ? 'Waiting and running jobs are being handled automatically. Refresh later unless progress remains unchanged.' : 'The queue is healthy when no work is waiting or requires attention.') ?></p><ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Waiting</strong><br><span class="uk-text-meta">The system will start this work automatically.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Running</strong><br><span class="uk-text-meta">Work is underway and progress will update here.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Needs attention</strong><br><span class="uk-text-meta">Fix the cause, then retry if your role allows it.</span></span></div></li></ol></aside>
    </div>
  </div>
</div>
