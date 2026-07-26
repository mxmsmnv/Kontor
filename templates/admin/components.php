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
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="uk-margin-medium-bottom">
    <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">System registry</p>
    <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Installed Kontor capabilities, dependencies and runtime versions.</p>
  </header>

  <nav aria-label="Component status">
    <ul class="uk-subnav uk-subnav-pill uk-margin-medium-bottom">
      <li<?= $registeredSelected ? ' class="uk-active"' : '' ?>>
        <a href="./"<?= $registeredSelected ? ' aria-current="page"' : '' ?>>
          Registered <span class="uk-badge"><?= $e($counts['total']) ?></span>
        </a>
      </li>
      <li<?= $enabledSelected ? ' class="uk-active"' : '' ?>>
        <a href="./?status=enabled"<?= $enabledSelected ? ' aria-current="page"' : '' ?>>
          Enabled <span class="uk-badge"><?= $e($counts['enabled']) ?></span>
        </a>
      </li>
      <li<?= $attentionSelected ? ' class="uk-active"' : '' ?>>
        <a href="./?status=attention"<?= $attentionSelected ? ' aria-current="page"' : '' ?>>
          Need attention <span class="uk-badge"><?= $e($counts['attention']) ?></span>
        </a>
      </li>
    </ul>
  </nav>

  <form class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom" method="get" action="./">
    <div class="uk-grid-small uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@m">
        <div class="uk-search uk-search-default uk-width-1-1">
          <span uk-search-icon></span>
          <input class="uk-search-input" name="q" type="search" value="<?= $e($query) ?>" placeholder="Name, module or capability" aria-label="Search components">
        </div>
      </div>
      <div class="uk-width-1-1 uk-width-medium@m">
        <select class="uk-select" name="status" aria-label="Component status">
          <option value="">All statuses</option>
          <option value="enabled"<?= $selectedStatus === 'enabled' ? ' selected' : '' ?>>Enabled</option>
          <option value="disabled"<?= $selectedStatus === 'disabled' ? ' selected' : '' ?>>Disabled</option>
          <option value="installed"<?= $selectedStatus === 'installed' ? ' selected' : '' ?>>Installed</option>
          <option value="uninstalled"<?= $selectedStatus === 'uninstalled' ? ' selected' : '' ?>>Uninstalled</option>
          <option value="attention"<?= $selectedStatus === 'attention' ? ' selected' : '' ?>>Needs attention</option>
        </select>
      </div>
      <div class="uk-width-auto">
        <button class="uk-button uk-button-primary" type="submit">Filter</button>
      </div>
      <?php if ($hasFilters): ?>
        <div class="uk-width-auto">
          <a class="uk-button uk-button-default" href="./">Clear</a>
        </div>
      <?php endif; ?>
      <div class="uk-width-auto uk-text-meta">
        <?= $e(count($components)) ?> matching component<?= count($components) === 1 ? '' : 's' ?>
      </div>
    </div>
  </form>

  <?php if ($components): ?>
    <section class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-match" uk-grid>
      <?php foreach ($components as $component): ?>
        <div>
          <article class="uk-card uk-card-default uk-card-small uk-card-body uk-flex uk-flex-column">
            <header class="uk-grid-small uk-flex-middle" uk-grid>
              <div class="uk-width-auto">
                <span class="uk-text-primary"><i class="fa fa-<?= $e($component['icon']) ?>"></i></span>
              </div>
              <div class="uk-width-expand">
                <h3 class="uk-card-title uk-margin-remove"><?= $e($component['title']) ?></h3>
                <div class="uk-text-meta"><?= $e($component['moduleName']) ?></div>
              </div>
              <div class="uk-width-auto">
                <span class="uk-label<?= $component['status'] === 'enabled' ? ' uk-label-success' : ' uk-label-warning' ?>">
                  <?= $e($component['status']) ?>
                </span>
              </div>
            </header>

            <p class="uk-text-small uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e($component['summary']) ?></p>

            <dl class="uk-grid-small uk-grid-divider uk-child-width-1-3 uk-margin-medium-top uk-margin-remove-bottom" uk-grid>
              <div>
                <dt class="uk-text-meta">Runtime</dt>
                <dd class="uk-margin-remove"><?= $e($component['runtimeVersion']) ?></dd>
              </div>
              <div>
                <dt class="uk-text-meta">Registry</dt>
                <dd class="uk-margin-remove">
                  <?= $e($component['registryVersion']) ?>
                  <?php if (!$component['versionInSync']): ?>
                    <span class="uk-text-warning uk-display-block uk-text-small"><i class="fa fa-warning"></i> Out of sync</span>
                  <?php endif; ?>
                </dd>
              </div>
              <div>
                <dt class="uk-text-meta">Updated</dt>
                <dd class="uk-margin-remove"><?= $e($component['updatedAt'] !== '' ? (new DateTimeImmutable($component['updatedAt']))->format('M j, Y H:i') : '—') ?></dd>
              </div>
            </dl>

            <?php if ($component['requires']): ?>
              <div class="uk-margin-top">
                <div class="uk-text-meta uk-margin-small-bottom">Requires</div>
                <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
                  <?php foreach ($component['requires'] as $dependency): ?>
                    <code><?= $e($dependency) ?></code>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php $canSync = $canSyncComponents && $component['needsAttention'] && $component['runtimeInstalled']; ?>
            <?php if ($component['href'] !== '' || $canSync): ?>
              <footer class="uk-flex uk-flex-middle uk-flex-between uk-margin-auto-top uk-padding-small uk-padding-remove-horizontal uk-padding-remove-bottom">
                <?php if ($component['href'] !== ''): ?>
                  <a class="uk-button uk-button-text" href="<?= $e($component['href']) ?>" target="_blank" rel="noreferrer">
                    Source <i class="fa fa-external-link"></i>
                  </a>
                <?php else: ?>
                  <span></span>
                <?php endif; ?>
                <?php if ($canSync): ?>
                  <form method="post" action="<?= $e($adminUrl) ?>component-sync/">
                    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                    <input type="hidden" name="component" value="<?= $e($component['name']) ?>">
                    <button class="uk-button uk-button-default uk-button-small" type="submit">
                      <i class="fa fa-refresh"></i> Sync registry
                    </button>
                  </form>
                <?php endif; ?>
              </footer>
            <?php endif; ?>
          </article>
        </div>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <div class="uk-placeholder uk-text-center">
      <span uk-icon="icon: grid; ratio: 1.5"></span>
      <h3 class="uk-margin-small-top uk-margin-small-bottom">No matching components</h3>
      <p class="uk-text-muted uk-margin-remove">Try another name or runtime status.</p>
    </div>
  <?php endif; ?>
</div>
