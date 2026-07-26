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
        <p>Open any workspace or pin up to <?= $e($quickNavigationLimit) ?> frequently used sections to the Kontor menu.</p>
      </div>
      <span class="uk-label"><?= $e($navigationCount) ?> sections</span>
    </header>

    <form method="post" action="<?= $e($adminUrl) ?>quick-navigation-save/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <div class="kontor-directory__toolbar">
        <label class="kontor-search">
          <i class="fa fa-search"></i>
          <input type="search" aria-label="Find a section" placeholder="Find a section…" autocomplete="off" data-kontor-directory-search>
        </label>
        <div class="kontor-directory__summary" aria-live="polite">
          <span><strong data-kontor-directory-visible-count><?= $e($navigationCount) ?></strong> shown</span>
          <span><strong data-kontor-quick-count><?= $e(count($quickNavigationKeys)) ?></strong>/<?= $e($quickNavigationLimit) ?> pinned</span>
          <button class="uk-button uk-button-primary uk-button-small kontor-button" type="submit">
            <i class="fa fa-check"></i> Save
          </button>
        </div>
      </div>

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
                      <span>
                        <i class="fa fa-thumb-tack"></i>
                        <span class="kontor-directory__pin-off">Pin</span>
                        <span class="kontor-directory__pin-on">Pinned</span>
                      </span>
                    </label>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty kontor-directory__empty" hidden data-kontor-directory-empty>
        <i class="fa fa-search"></i>
        <h3>No matching sections</h3>
        <p>Try another name or a broader search.</p>
      </div>
      <div class="kontor-directory__actions">
        <button class="uk-button uk-button-primary kontor-button" type="submit">
          <i class="fa fa-check"></i> Save quick access
        </button>
        <a class="uk-button uk-button-default kontor-button" href="<?= $e($adminUrl) ?>">
          <i class="fa fa-arrow-left"></i> Back to Dashboard
        </a>
      </div>
    </form>
  </section>
</div>
