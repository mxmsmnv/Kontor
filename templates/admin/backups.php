<?php

/** @var array<int, array<string, mixed>> $backups */
/** @var string $query */
/** @var string $selectedComponent */
/** @var string $selectedStatus */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalBackups */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var bool $canDownloadBackups */
/** @var callable $e */

$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / 1024 / 1024, 1) . ' MB';
};
$pageUrl = static function (int $targetPage) use ($query, $selectedComponent, $selectedStatus): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'component' => $selectedComponent,
        'status' => $selectedStatus,
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int $value): bool => $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
$hasFilters = $query !== '' || $selectedComponent !== '' || $selectedStatus !== '';
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Recovery</p>
      <h2>Backups</h2>
      <p>Create verified snapshots before imports, upgrades, or high-risk changes.</p>
    </div>
    <div class="kontor-backupactions">
      <?php foreach (['contacts' => 'Contacts snapshot', 'core' => 'Core snapshot'] as $component => $label): ?>
        <form method="post" action="<?= $e($adminUrl) ?>backup-create/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="component" value="<?= $e($component) ?>">
          <button class="kontor-button<?= $component === 'core' ? ' kontor-button--secondary' : '' ?>" type="submit">
            <i class="fa fa-database"></i> <?= $e($label) ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </header>

  <div class="kontor-setup">
    <strong>Restore stays in the recovery workflow.</strong>
    Snapshots can be created and verified here, while restore remains deliberately unavailable in the everyday admin UI.
  </div>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Backup ID, component or type">
    </label>
    <select name="component" aria-label="Backup component">
      <option value="">All components</option>
      <option value="contacts"<?= $selectedComponent === 'contacts' ? ' selected' : '' ?>>Contacts</option>
      <option value="core"<?= $selectedComponent === 'core' ? ' selected' : '' ?>>Core</option>
      <option value="unknown"<?= $selectedComponent === 'unknown' ? ' selected' : '' ?>>Unknown</option>
    </select>
    <select name="status" aria-label="Verification status">
      <option value="">All states</option>
      <option value="verified"<?= $selectedStatus === 'verified' ? ' selected' : '' ?>>Verified</option>
      <option value="failed"<?= $selectedStatus === 'failed' ? ' selected' : '' ?>>Verification failed</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?><a class="kontor-button kontor-button--ghost" href="./">Clear</a><?php endif; ?>
    <span class="kontor-secondary"><?= $e($totalBackups) ?> matching · <?= $e(count($backups)) ?> shown</span>
  </form>

  <?php if ($backups): ?>
    <section class="kontor-backuplist">
      <?php foreach ($backups as $backup): ?>
        <article class="kontor-card kontor-backup">
          <span class="kontor-backup__icon"><i class="fa fa-archive"></i></span>
          <div class="kontor-backup__body">
            <div>
              <strong><?= $e(ucfirst((string) $backup['component'])) ?> <?= $e((string) $backup['kind']) ?></strong>
              <span class="kontor-pill<?= $backup['verified'] ? '' : ' kontor-pill--danger' ?>">
                <?= $backup['verified'] ? 'Verified' : 'Verification failed' ?>
              </span>
            </div>
            <code><?= $e((string) $backup['id']) ?></code>
            <p>
              <?= $e((string) $backup['itemCount']) ?> items
              · <?= $e($formatBytes((int) $backup['sizeBytes'])) ?>
            </p>
          </div>
          <time datetime="<?= $e((string) $backup['createdAt']) ?>">
            <?= $e((new DateTimeImmutable((string) $backup['createdAt']))->format('M j, Y')) ?>
            <span><?= $e((new DateTimeImmutable((string) $backup['createdAt']))->format('H:i:s')) ?></span>
          </time>
          <?php if ($backup['verified'] && $canDownloadBackups): ?>
            <form class="kontor-backup__download" method="post" action="<?= $e($adminUrl) ?>backup-download/">
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
              <input type="hidden" name="id" value="<?= $e((string) $backup['id']) ?>">
              <button type="submit" title="Download verified snapshot" aria-label="Download verified snapshot">
                <i class="fa fa-download"></i>
              </button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
    <?php if ($totalPages > 1): ?>
      <nav class="kontor-pagination" aria-label="Backup pages">
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
      <i class="fa fa-database"></i>
      <h3><?= $hasFilters ? 'No matching snapshots' : 'No snapshots yet' ?></h3>
      <p><?= $hasFilters ? 'Try another backup ID, component, or verification state.' : 'Create a Contacts or Core snapshot to establish a recovery point.' ?></p>
    </div>
  <?php endif; ?>
</div>
