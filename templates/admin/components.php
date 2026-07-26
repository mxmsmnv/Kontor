<?php

/** @var array<int, array<string, mixed>> $components */
/** @var array{total: int, enabled: int, attention: int} $counts */
/** @var string $query */
/** @var string $selectedStatus */
/** @var bool $canSyncComponents */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$hasFilters = $query !== '' || $selectedStatus !== '';
$registeredSelected = $query === '' && $selectedStatus === '';
$enabledSelected = $query === '' && $selectedStatus === 'enabled';
$attentionSelected = $query === '' && $selectedStatus === 'attention';
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">System</p>
      <h2>Components</h2>
      <p>Installed Kontor capabilities, dependencies and runtime versions.</p>
    </div>
  </header>

  <section class="kontor-componentstats">
    <a class="kontor-card<?= $registeredSelected ? ' kontor-card--selected' : '' ?>" href="./"<?= $registeredSelected ? ' aria-current="page"' : '' ?>>
      <strong><?= $e($counts['total']) ?></strong><span>Registered</span>
    </a>
    <a class="kontor-card<?= $enabledSelected ? ' kontor-card--selected' : '' ?>" href="./?status=enabled"<?= $enabledSelected ? ' aria-current="page"' : '' ?>>
      <strong><?= $e($counts['enabled']) ?></strong><span>Enabled</span>
    </a>
    <a class="kontor-card<?= $counts['attention'] > 0 ? ' kontor-componentstats--warning' : '' ?><?= $attentionSelected ? ' kontor-card--selected' : '' ?>" href="./?status=attention"<?= $attentionSelected ? ' aria-current="page"' : '' ?>>
      <strong><?= $e($counts['attention']) ?></strong><span>Need attention</span>
    </a>
  </section>

  <form class="kontor-toolbar kontor-componentfilters" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Name, module or capability">
    </label>
    <select name="status" aria-label="Component status">
      <option value="">All statuses</option>
      <option value="enabled"<?= $selectedStatus === 'enabled' ? ' selected' : '' ?>>Enabled</option>
      <option value="disabled"<?= $selectedStatus === 'disabled' ? ' selected' : '' ?>>Disabled</option>
      <option value="installed"<?= $selectedStatus === 'installed' ? ' selected' : '' ?>>Installed</option>
      <option value="uninstalled"<?= $selectedStatus === 'uninstalled' ? ' selected' : '' ?>>Uninstalled</option>
      <option value="attention"<?= $selectedStatus === 'attention' ? ' selected' : '' ?>>Needs attention</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?><a class="kontor-button kontor-button--ghost" href="./">Clear</a><?php endif; ?>
  </form>

  <?php if ($components): ?>
    <section class="kontor-components">
      <?php foreach ($components as $component): ?>
        <article class="kontor-card kontor-component">
          <div class="kontor-component__top">
            <span class="kontor-component__icon"><i class="fa fa-<?= $e($component['icon']) ?>"></i></span>
            <div>
              <h3><?= $e($component['title']) ?></h3>
              <code><?= $e($component['moduleName']) ?></code>
            </div>
            <span class="kontor-pill<?= $component['status'] === 'enabled' ? '' : ' kontor-pill--inactive' ?>">
              <?= $e($component['status']) ?>
            </span>
          </div>
          <p><?= $e($component['summary']) ?></p>
          <dl class="kontor-component__facts">
            <div>
              <dt>Runtime</dt>
              <dd><?= $e($component['runtimeVersion']) ?></dd>
            </div>
            <div>
              <dt>Registry</dt>
              <dd>
                <?= $e($component['registryVersion']) ?>
                <?php if (!$component['versionInSync']): ?>
                  <span class="kontor-component__warning"><i class="fa fa-warning"></i> Out of sync</span>
                <?php endif; ?>
              </dd>
            </div>
            <div>
              <dt>Updated</dt>
              <dd><?= $e($component['updatedAt'] !== '' ? (new DateTimeImmutable($component['updatedAt']))->format('M j, Y H:i') : '—') ?></dd>
            </div>
          </dl>
          <?php if ($component['requires']): ?>
            <div class="kontor-component__requires">
              <span>Requires</span>
              <?php foreach ($component['requires'] as $dependency): ?>
                <code><?= $e($dependency) ?></code>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php $canSync = $canSyncComponents && $component['needsAttention'] && $component['runtimeInstalled']; ?>
          <?php if ($component['href'] !== '' || $canSync): ?>
            <div class="kontor-component__actions">
              <?php if ($component['href'] !== ''): ?>
                <a class="kontor-component__source" href="<?= $e($component['href']) ?>" target="_blank" rel="noreferrer">
                  View source <i class="fa fa-external-link"></i>
                </a>
              <?php endif; ?>
              <?php if ($canSync): ?>
                <form method="post" action="<?= $e($adminUrl) ?>component-sync/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="component" value="<?= $e($component['name']) ?>">
                  <button class="kontor-button kontor-button--ghost" type="submit">
                    <i class="fa fa-refresh"></i> Sync registry
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-cubes"></i>
      <h3>No matching components</h3>
      <p>Try another name or runtime status.</p>
    </div>
  <?php endif; ?>
</div>
