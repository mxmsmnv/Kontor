<?php

/** @var \Kontor\Catalog\Domain\Category[] $categories */
/** @var array<string, string> $categoryNames */
/** @var string $displayLanguage */
/** @var string $query */
/** @var bool $showArchived */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalCategories */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$url = static function (int $targetPage, bool $archived) use ($query): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'archived' => $archived ? 1 : '',
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int $value): bool => $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Catalog structure</p>
      <h2>Categories</h2>
      <p>Organize products and services into reusable parent and child groups.</p>
    </div>
    <div class="kontor-backupactions">
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-cubes"></i> Items
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
        <i class="fa fa-tags"></i> Price lists
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-references/">
        <i class="fa fa-book"></i> References
      </a>
      <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-category/">
        <i class="fa fa-plus"></i> New category
      </a>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Category name">
    </label>
    <button class="kontor-button" type="submit">Search</button>
    <a class="kontor-viewtoggle" href="<?= $e($url(1, !$showArchived)) ?>">
      <i class="fa fa-<?= $showArchived ? 'folder-open' : 'archive' ?>"></i>
      <?= $showArchived ? 'Active categories' : 'Archive' ?>
    </a>
    <span class="kontor-secondary"><?= $e($totalCategories) ?> total · <?= $e(count($categories)) ?> shown</span>
  </form>

  <?php if ($categories): ?>
    <form
      class="kontor-bulkactions"
      id="catalog-category-bulk-form"
      method="post"
      action="<?= $e($adminUrl) ?>catalog-category-bulk-action/"
      data-kontor-bulk-form
      data-action-label="<?= $showArchived ? 'Restore' : 'Archive' ?>"
      data-entity-label="catalog category"
    >
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
      <input type="hidden" name="return_q" value="<?= $e($query) ?>">
      <input type="hidden" name="return_archived" value="<?= $showArchived ? '1' : '0' ?>">
      <input type="hidden" name="return_page" value="<?= $e($page) ?>">
      <span class="kontor-secondary" data-kontor-selected-count>0 selected</span>
      <button class="kontor-button kontor-button--ghost" type="submit">
        <i class="fa fa-<?= $showArchived ? 'undo' : 'archive' ?>"></i>
        <?= $showArchived ? 'Restore selected' : 'Archive selected' ?>
      </button>
    </form>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table">
        <thead>
          <tr>
            <th class="kontor-selectcell">
              <input
                type="checkbox"
                data-kontor-select-all="catalog-category-bulk-form"
                aria-label="Select all shown catalog categories"
              >
            </th>
            <th>Category</th><th>Parent</th><th>Order</th><th>Status</th><th><span class="kontor-visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
            <?php $categoryLabel = $category->nameIn($displayLanguage) ?? $category->nameIn('en') ?? reset($category->name) ?: 'Untitled category'; ?>
            <tr>
              <td class="kontor-selectcell">
                <input
                  type="checkbox"
                  name="ids[]"
                  value="<?= $e($category->uid->toString()) ?>"
                  form="catalog-category-bulk-form"
                  data-kontor-select-item="catalog-category-bulk-form"
                  aria-label="Select <?= $e($categoryLabel) ?>"
                >
              </td>
              <td>
                <strong>
                  <a href="<?= $e($adminUrl) ?>catalog-category/?id=<?= $e(rawurlencode($category->uid->toString())) ?>">
                    <?= $e($categoryLabel) ?>
                  </a>
                </strong>
              </td>
              <td><?= $e($category->parentUid !== null ? ($categoryNames[$category->parentUid] ?? 'Archived parent') : '—') ?></td>
              <td><?= $e($category->sortOrder) ?></td>
              <td><span class="kontor-pill<?= $category->status === 'active' ? '' : ' kontor-pill--inactive' ?>"><?= $e($category->status) ?></span></td>
              <td class="kontor-queueactions">
                <form method="post" action="<?= $e($adminUrl) ?>catalog-category-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="id" value="<?= $e($category->uid->toString()) ?>">
                  <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
                  <button type="submit" title="<?= $showArchived ? 'Restore category' : 'Archive category' ?>" aria-label="<?= $showArchived ? 'Restore category' : 'Archive category' ?>">
                    <i class="fa fa-<?= $showArchived ? 'undo' : 'archive' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php if ($totalPages > 1): ?>
      <nav class="kontor-pagination" aria-label="Category pages">
        <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <div>
          <?php if ($page > 1): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page - 1, $showArchived)) ?>"><i class="fa fa-chevron-left"></i> Previous</a><?php endif; ?>
          <?php if ($page < $totalPages): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page + 1, $showArchived)) ?>">Next <i class="fa fa-chevron-right"></i></a><?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-folder-open"></i>
      <h3><?= $query !== '' || $showArchived ? 'No matching categories' : 'No categories yet' ?></h3>
      <p><?= $query !== '' || $showArchived ? 'Try another search or category view.' : 'Create a category to organize the catalog.' ?></p>
    </div>
  <?php endif; ?>
</div>
