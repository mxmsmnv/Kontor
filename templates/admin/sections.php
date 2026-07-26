<?php

/** @var array<string, array<int, array{key: string, url: string, label: string, icon: string}>> $navigationGroups */
/** @var string[] $quickNavigationKeys */
/** @var int $quickNavigationLimit */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$navigationCount = array_sum(array_map('count', $navigationGroups));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-directory" data-kontor-directory data-limit="<?= $e($quickNavigationLimit) ?>">
    <header class="kontor-sectionhead">
      <div>
        <p class="kontor-eyebrow">Workspace directory</p>
        <h2>All Kontor sections</h2>
        <p>Open any part of Kontor here. Pin only the sections you use often to the top menu.</p>
      </div>
      <span class="uk-label"><?= $e($navigationCount) ?> sections</span>
    </header>

    <div class="kontor-directory__toolbar">
      <label class="kontor-search">
        <i class="fa fa-search"></i>
        <input type="search" aria-label="Find a section" placeholder="Find a section…" data-kontor-directory-search>
      </label>
      <span class="kontor-secondary"><strong data-kontor-quick-count><?= $e(count($quickNavigationKeys)) ?></strong>/<?= $e($quickNavigationLimit) ?> in quick access</span>
    </div>

    <form method="post" action="<?= $e($adminUrl) ?>quick-navigation-save/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <div class="kontor-directory__groups">
        <?php foreach ($navigationGroups as $group => $items): ?>
          <section class="kontor-directory__group">
            <h3><?= $e($group) ?></h3>
            <div class="kontor-directory__items">
              <?php foreach ($items as $item): ?>
                <article class="kontor-directory__item" data-kontor-directory-item data-search="<?= $e(strtolower($group . ' ' . $item['label'])) ?>">
                  <a href="<?= $e($adminUrl . $item['url']) ?>">
                    <span class="kontor-directory__icon"><i class="fa fa-<?= $e($item['icon']) ?>"></i></span>
                    <strong><?= $e($item['label']) ?></strong>
                  </a>
                  <?php if ($item['key'] === 'dashboard'): ?>
                    <span class="uk-label kontor-pill kontor-pill--inactive">Home</span>
                  <?php else: ?>
                    <label class="kontor-directory__pin">
                      <input type="checkbox" name="quick_navigation[]" value="<?= $e($item['key']) ?>" aria-label="Add <?= $e($item['label']) ?> to quick access"<?= in_array($item['key'], $quickNavigationKeys, true) ? ' checked' : '' ?> data-kontor-quick-toggle>
                      Quick
                    </label>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
      <div class="kontor-directory__actions">
        <button class="uk-button uk-button-primary kontor-button" type="submit">
          <i class="fa fa-check"></i> Save quick access
        </button>
        <span class="kontor-secondary">Dashboard is always available by clicking “Kontor”.</span>
      </div>
    </form>
  </section>
</div>
