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
$allSelected = $selectedQueue === null && $selectedStatus === null;
$activeSelected = $selectedQueue === null && $selectedStatus === 'active';
$completedSelected = $selectedQueue === null && $selectedStatus === 'completed';
$deadSelected = $selectedQueue === null && $selectedStatus === 'dead';
$pageUrl = static function (int $targetPage) use ($selectedQueue, $selectedStatus): string {
    $query = http_build_query(array_filter([
        'queue' => $selectedQueue,
        'status' => $selectedStatus,
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $query === '' ? './' : './?' . $query;
};
$refreshUrl = $pageUrl($page);
$statusClass = static fn (string $status): string => match ($status) {
    'completed' => '',
    'pending' => ' kontor-pill--warning',
    'dead' => ' kontor-pill--critical',
    default => ' kontor-pill--inactive',
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Background work</p>
      <h2>Queue monitor</h2>
      <p>Recent asynchronous work, retries, progress, and dead-letter failures.</p>
    </div>
    <a class="kontor-button" href="<?= $e($refreshUrl) ?>"><i class="fa fa-refresh"></i> Refresh</a>
  </header>

  <section class="kontor-queuestats">
    <a class="kontor-card kontor-stat<?= $allSelected ? ' kontor-card--selected' : '' ?>" href="./"<?= $allSelected ? ' aria-current="page"' : '' ?>>
      <span class="kontor-stat__icon"><i class="fa fa-list"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($total) ?></strong><span class="kontor-stat__label">All jobs</span></span>
    </a>
    <a class="kontor-card kontor-stat<?= $activeSelected ? ' kontor-card--selected' : '' ?>" href="./?status=active"<?= $activeSelected ? ' aria-current="page"' : '' ?>>
      <span class="kontor-stat__icon kontor-stat__icon--warning"><i class="fa fa-clock-o"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($active) ?></strong><span class="kontor-stat__label">Active</span></span>
    </a>
    <a class="kontor-card kontor-stat<?= $completedSelected ? ' kontor-card--selected' : '' ?>" href="./?status=completed"<?= $completedSelected ? ' aria-current="page"' : '' ?>>
      <span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-check"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($counts['completed'] ?? 0) ?></strong><span class="kontor-stat__label">Completed</span></span>
    </a>
    <a class="kontor-card kontor-stat<?= $deadSelected ? ' kontor-card--selected' : '' ?>" href="./?status=dead"<?= $deadSelected ? ' aria-current="page"' : '' ?>>
      <span class="kontor-stat__icon kontor-stat__icon--danger"><i class="fa fa-exclamation-triangle"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($counts['dead'] ?? 0) ?></strong><span class="kontor-stat__label">Dead letter</span></span>
    </a>
  </section>

  <form class="kontor-toolbar kontor-queuefilters" method="get" action="./">
    <div>
      <select name="queue" aria-label="Queue">
        <option value="">All queues</option>
        <?php foreach ($queues as $queue): ?>
          <option value="<?= $e($queue) ?>"<?= $selectedQueue === $queue ? ' selected' : '' ?>><?= $e($queue) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" aria-label="Status">
        <option value="">All statuses</option>
        <?php foreach (['active', 'pending', 'reserved', 'completed', 'dead', 'cancelled'] as $status): ?>
          <option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="kontor-button" type="submit">Filter</button>
    </div>
    <?php if ($selectedQueue !== null || $selectedStatus !== null): ?>
      <a class="kontor-button kontor-button--ghost" href="./">Clear filters</a>
    <?php endif; ?>
    <span class="kontor-secondary"><?= $e($totalJobs) ?> matching · <?= $e(count($jobs)) ?> shown</span>
  </form>

  <?php if ($jobs): ?>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table kontor-queuetable">
        <thead>
          <tr>
            <th>Job</th>
            <th>Queue</th>
            <th>Status</th>
            <th>Progress</th>
            <th>Attempts</th>
            <th>Created</th>
            <?php if ($canCancelJobs || $canRetryJobs): ?><th><span class="kontor-visually-hidden">Actions</span></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <?php $status = (string) $job['status']; ?>
            <tr>
              <td>
                <strong><?= $e((string) $job['job_type']) ?></strong>
                <code><?= $e((string) $job['uid']) ?></code>
                <?php if (!empty($job['error_message'])): ?>
                  <details class="kontor-queueerror">
                    <summary>View error</summary>
                    <p><?= $e((string) $job['error_message']) ?></p>
                  </details>
                <?php endif; ?>
              </td>
              <td><span class="kontor-secondary"><?= $e((string) $job['queue']) ?></span></td>
              <td><span class="kontor-pill<?= $statusClass($status) ?>"><?= $e($status) ?></span></td>
              <td>
                <div class="kontor-progress" title="<?= $e((int) $job['progress']) ?>%">
                  <span style="width: <?= $e((int) $job['progress']) ?>%"></span>
                </div>
                <small><?= $e((int) $job['progress']) ?>%</small>
              </td>
              <td><?= $e((int) $job['attempts']) ?> / <?= $e((int) $job['max_attempts']) ?></td>
              <td>
                <time datetime="<?= $e((string) $job['created_at']) ?>">
                  <?= $e((new DateTimeImmutable((string) $job['created_at']))->format('M j, H:i')) ?>
                </time>
              </td>
              <?php if ($canCancelJobs || $canRetryJobs): ?>
                <td class="kontor-queueactions">
                  <?php if ($status === 'pending' && $canCancelJobs): ?>
                    <form method="post" action="<?= $e($adminUrl) ?>queue-action/">
                      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="uid" value="<?= $e((string) $job['uid']) ?>">
                      <button type="submit" title="Cancel pending job" aria-label="Cancel pending job">
                        <i class="fa fa-ban"></i>
                      </button>
                    </form>
                  <?php elseif ($status === 'dead' && $canRetryJobs): ?>
                    <form method="post" action="<?= $e($adminUrl) ?>queue-action/">
                      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                      <input type="hidden" name="action" value="retry">
                      <input type="hidden" name="uid" value="<?= $e((string) $job['uid']) ?>">
                      <button type="submit" title="Retry dead-letter job" aria-label="Retry dead-letter job">
                        <i class="fa fa-repeat"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php if ($totalPages > 1): ?>
      <nav class="kontor-pagination" aria-label="Queue pages">
        <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <div>
          <?php if ($page > 1): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page - 1)) ?>">
              <i class="fa fa-chevron-left"></i> Previous
            </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page + 1)) ?>">
              Next <i class="fa fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-tasks"></i>
      <h3><?= $selectedQueue !== null || $selectedStatus !== null ? 'No matching jobs' : 'Queue is clear' ?></h3>
      <p><?= $selectedQueue !== null || $selectedStatus !== null ? 'Try another queue or status filter.' : 'Background jobs will appear here when components dispatch work.' ?></p>
    </div>
  <?php endif; ?>
</div>
