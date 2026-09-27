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
<div class="ProcessKontor pw-module-workspace kontor-shell kontor-directory" data-kontor-directory data-limit="<?= $e($quickNavigationLimit) ?>">
  <header class="uk-grid-small uk-flex-middle uk-margin-medium-bottom" uk-grid>
    <div class="uk-width-expand">
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Workspace directory</p>
      <h2 class="uk-margin-small-top uk-margin-small-bottom">All Kontor sections</h2>
      <p class="uk-text-muted uk-margin-remove">Open any workspace or pin up to <?= $e($quickNavigationLimit) ?> frequently used sections to the Kontor menu. Components is always available there.</p>
    </div>
    <div class="uk-width-auto">
      <span class="uk-label"><?= $e($navigationCount) ?> sections</span>
    </div>
  </header>

  <form method="post" action="<?= $e($adminUrl) ?>quick-navigation-save/">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">

    <div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-grid-small uk-flex-middle" uk-grid>
        <div class="uk-width-1-1 uk-width-expand@m">
          <div class="uk-search uk-search-default uk-width-1-1">
            <span uk-search-icon></span>
            <input class="uk-search-input" type="search" aria-label="Find a section" placeholder="Find a section…" autocomplete="off" data-kontor-directory-search>
          </div>
        </div>
        <div class="uk-width-1-1 uk-width-auto@m uk-flex uk-flex-middle uk-grid-small uk-text-meta" aria-live="polite" uk-grid>
          <span><strong data-kontor-directory-visible-count><?= $e($navigationCount) ?></strong> shown</span>
          <span><strong data-kontor-quick-count><?= $e(count($quickNavigationKeys)) ?></strong>/<?= $e($quickNavigationLimit) ?> pinned</span>
        </div>
      </div>
    </div>

    <?php foreach ($navigationGroups as $group => $items): ?>
      <section class="kontor-directory__group uk-margin-large-top">
        <h3 class="uk-heading-line uk-text-small uk-text-uppercase"><span><?= $e($group) ?></span></h3>
        <div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l uk-grid-match" uk-grid>
          <?php foreach ($items as $item): ?>
            <div data-kontor-directory-item data-search="<?= $e(strtolower($group . ' ' . $item['label'])) ?>">
              <article class="uk-card uk-card-default uk-card-small uk-card-body kontor-directory__item uk-flex uk-flex-middle uk-flex-between">
                <a class="uk-link-reset uk-flex uk-flex-middle uk-flex-1" href="<?= $e($adminUrl . $item['url']) ?>">
                  <span class="uk-margin-small-right uk-text-primary"><i class="fa fa-<?= $e($item['icon']) ?>"></i></span>
                  <strong><?= $e($item['label']) ?></strong>
                </a>
                <?php if ($item['key'] === 'dashboard'): ?>
                  <span class="uk-label">Home</span>
                <?php elseif ($item['key'] === 'components'): ?>
                  <span class="uk-label">Menu</span>
                <?php else: ?>
                  <label class="uk-flex uk-flex-middle uk-text-meta uk-margin-small-left">
                    <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="quick_navigation[]" value="<?= $e($item['key']) ?>" aria-label="Add <?= $e($item['label']) ?> to quick access"<?= in_array($item['key'], $quickNavigationKeys, true) ? ' checked' : '' ?> data-kontor-quick-toggle>
                    Quick access
                  </label>
                <?php endif; ?>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="uk-placeholder uk-text-center" hidden data-kontor-directory-empty>
      <span uk-icon="icon: search; ratio: 1.5"></span>
      <h3 class="uk-margin-small-top uk-margin-small-bottom">No matching sections</h3>
      <p class="uk-text-muted uk-margin-remove">Try another name or a broader search.</p>
    </div>

    <div class="uk-flex uk-flex-right uk-margin-large-top">
      <button class="uk-button uk-button-primary" type="submit">
        <i class="fa fa-check"></i> Save quick access
      </button>
    </div>
  </form>
</div>
