<?php

/** @var array<int, array<string, mixed>> $jobs */
/** @var array<string, int> $counts */
/** @var string[] $queues */
/** @var string|null $selectedQueue */
/** @var string|null $selectedStatus */
/** @var callable $e */

$total = array_sum($counts);
$active = ($counts['pending'] ?? 0) + ($counts['reserved'] ?? 0);
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
    <a class="kontor-button" href="./"><i class="fa fa-refresh"></i> Refresh</a>
  </header>

  <section class="kontor-queuestats">
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon"><i class="fa fa-list"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($total) ?></strong><span class="kontor-stat__label">All jobs</span></span>
    </article>
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon kontor-stat__icon--warning"><i class="fa fa-clock-o"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($active) ?></strong><span class="kontor-stat__label">Active</span></span>
    </article>
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-check"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($counts['completed'] ?? 0) ?></strong><span class="kontor-stat__label">Completed</span></span>
    </article>
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon kontor-stat__icon--danger"><i class="fa fa-exclamation-triangle"></i></span>
      <span><strong class="kontor-stat__value"><?= $e($counts['dead'] ?? 0) ?></strong><span class="kontor-stat__label">Dead letter</span></span>
    </article>
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
        <?php foreach (['pending', 'reserved', 'completed', 'dead', 'cancelled'] as $status): ?>
          <option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="kontor-button" type="submit">Filter</button>
    </div>
    <?php if ($selectedQueue !== null || $selectedStatus !== null): ?>
      <a class="kontor-button kontor-button--ghost" href="./">Clear filters</a>
    <?php endif; ?>
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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-tasks"></i>
      <h3><?= $selectedQueue !== null || $selectedStatus !== null ? 'No matching jobs' : 'Queue is clear' ?></h3>
      <p><?= $selectedQueue !== null || $selectedStatus !== null ? 'Try another queue or status filter.' : 'Background jobs will appear here when components dispatch work.' ?></p>
    </div>
  <?php endif; ?>
</div>
