<?php

/** @var \Kontor\Catalog\Domain\CatalogItem[] $items */
/** @var string $query */
/** @var string|null $selectedType */
/** @var string|null $selectedCategory */
/** @var string|null $selectedStatus */
/** @var string|null $selectedInventory */
/** @var array<string, string> $categoryOptions */
/** @var array<string, string> $categoryNames */
/** @var bool $showArchived */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalItems */
/** @var array<string, string> $unitLabels */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$url = static function (int $targetPage, bool $archived) use ($query, $selectedType, $selectedCategory, $selectedStatus, $selectedInventory): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'type' => $selectedType,
        'category' => $selectedCategory,
        'status' => $selectedStatus,
        'inventory' => $selectedInventory,
        'archived' => $archived ? 1 : '',
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
$money = static function (?\Kontor\SDK\ValueObjects\Money $value): string {
    if ($value === null) {
        return '—';
    }

    $minor = $value->amountMinor();
    $negative = $minor < 0 ? '-' : '';
    $minor = abs($minor);

    return sprintf(
        '%s%d.%02d %s',
        $negative,
        intdiv($minor, 100),
        $minor % 100,
        $value->currencyCode(),
    );
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Products and services</p>
      <h2>Catalog</h2>
      <p>Sellable items, service offerings, prices, units, and inventory behavior.</p>
    </div>
    <div class="kontor-backupactions">
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-categories/">
        <i class="fa fa-folder-open"></i> Categories
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
        <i class="fa fa-tags"></i> Price lists
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-references/">
        <i class="fa fa-book"></i> References
      </a>
      <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-item/">
        <i class="fa fa-plus"></i> New item
      </a>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Title, SKU, barcode or description">
    </label>
    <select name="type" aria-label="Item type">
      <option value="">Products and services</option>
      <option value="product"<?= $selectedType === 'product' ? ' selected' : '' ?>>Products</option>
      <option value="service"<?= $selectedType === 'service' ? ' selected' : '' ?>>Services</option>
    </select>
    <select name="category" aria-label="Catalog category">
      <option value="">All categories</option>
      <option value="uncategorized"<?= $selectedCategory === 'uncategorized' ? ' selected' : '' ?>>Uncategorized</option>
      <?php foreach ($categoryOptions as $uid => $label): ?>
        <option value="<?= $e($uid) ?>"<?= $selectedCategory === $uid ? ' selected' : '' ?>><?= $e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Item status">
      <option value="">All statuses</option>
      <option value="active"<?= $selectedStatus === 'active' ? ' selected' : '' ?>>Active</option>
      <option value="inactive"<?= $selectedStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
      <option value="discontinued"<?= $selectedStatus === 'discontinued' ? ' selected' : '' ?>>Discontinued</option>
    </select>
    <select name="inventory" aria-label="Inventory tracking">
      <option value="">All inventory modes</option>
      <option value="tracked"<?= $selectedInventory === 'tracked' ? ' selected' : '' ?>>Tracked</option>
      <option value="untracked"<?= $selectedInventory === 'untracked' ? ' selected' : '' ?>>Not tracked</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <a class="kontor-viewtoggle" href="<?= $e($adminUrl) ?>export/?entity=catalog_item&amp;format=csv">
      <i class="fa fa-download"></i> Export CSV
    </a>
    <a class="kontor-viewtoggle" href="<?= $e($adminUrl) ?>import/?entity=catalog_item">
      <i class="fa fa-upload"></i> Import
    </a>
    <a class="kontor-viewtoggle" href="<?= $e($url(1, !$showArchived)) ?>">
      <i class="fa fa-<?= $showArchived ? 'cubes' : 'archive' ?>"></i>
      <?= $showArchived ? 'Active items' : 'Archive' ?>
    </a>
    <span class="kontor-secondary"><?= $e($totalItems) ?> total · <?= $e(count($items)) ?> shown</span>
  </form>

  <?php if ($items): ?>
    <form
      class="kontor-bulkactions"
      id="catalog-bulk-form"
      method="post"
      action="<?= $e($adminUrl) ?>catalog-bulk-action/"
      data-kontor-bulk-form
      data-entity-label="catalog item"
    >
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <input type="hidden" name="return_q" value="<?= $e($query) ?>">
      <input type="hidden" name="return_type" value="<?= $e($selectedType) ?>">
      <input type="hidden" name="return_category" value="<?= $e($selectedCategory ?? '') ?>">
      <input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>">
      <input type="hidden" name="return_inventory" value="<?= $e($selectedInventory ?? '') ?>">
      <input type="hidden" name="return_archived" value="<?= $showArchived ? '1' : '0' ?>">
      <input type="hidden" name="return_page" value="<?= $e($page) ?>">
      <span class="kontor-secondary" data-kontor-selected-count>0 selected</span>
      <button class="kontor-button kontor-button--ghost" type="submit" name="action" value="activate" data-action-label="Activate">
        <i class="fa fa-play"></i> Activate selected
      </button>
      <button class="kontor-button kontor-button--ghost" type="submit" name="action" value="deactivate" data-action-label="Deactivate">
        <i class="fa fa-pause"></i> Deactivate selected
      </button>
      <button class="kontor-button kontor-button--ghost" type="submit" name="action" value="discontinue" data-action-label="Discontinue">
        <i class="fa fa-stop"></i> Discontinue selected
      </button>
      <button
        class="kontor-button kontor-button--ghost"
        type="submit"
        name="action"
        value="<?= $showArchived ? 'restore' : 'archive' ?>"
        data-action-label="<?= $showArchived ? 'Restore' : 'Archive' ?>"
      >
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
                data-kontor-select-all="catalog-bulk-form"
                aria-label="Select all shown catalog items"
              >
            </th>
            <th>Item</th>
            <th>SKU</th>
            <th>Type</th>
            <th>Category</th>
            <th>Sales price</th>
            <th>Unit</th>
            <th>Status</th>
            <th><span class="kontor-visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td class="kontor-selectcell">
                <input
                  type="checkbox"
                  name="ids[]"
                  value="<?= $e($item->uid->toString()) ?>"
                  form="catalog-bulk-form"
                  data-kontor-select-item="catalog-bulk-form"
                  aria-label="Select <?= $e($item->titleIn('en') ?? reset($item->title) ?: 'catalog item') ?>"
                >
              </td>
              <td>
                <strong>
                  <a href="<?= $e($adminUrl) ?>catalog-item/?id=<?= $e(rawurlencode($item->uid->toString())) ?>">
                    <?= $e($item->titleIn('en') ?? reset($item->title) ?: 'Untitled item') ?>
                  </a>
                </strong>
                <?php if ($item->trackInventory): ?><small>Inventory tracked</small><?php endif; ?>
              </td>
              <td><code><?= $e($item->sku ?: '—') ?></code></td>
              <td><?= $e(ucfirst($item->itemType)) ?></td>
              <td><?= $e($item->categoryUid !== null ? ($categoryNames[$item->categoryUid] ?? 'Unknown category') : '—') ?></td>
              <td><?= $e($money($item->salesPrice)) ?></td>
              <td><?= $e($unitLabels[$item->unitCode] ?? $item->unitCode) ?> <small><code><?= $e($item->unitCode) ?></code></small></td>
              <td><span class="kontor-pill<?= $item->status === 'active' ? '' : ' kontor-pill--inactive' ?>"><?= $e($item->status) ?></span></td>
              <td class="kontor-queueactions">
                <form method="post" action="<?= $e($adminUrl) ?>catalog-item-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>">
                  <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
                  <button type="submit" title="<?= $showArchived ? 'Restore item' : 'Archive item' ?>" aria-label="<?= $showArchived ? 'Restore item' : 'Archive item' ?>">
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
      <nav class="kontor-pagination" aria-label="Catalog pages">
        <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <div>
          <?php if ($page > 1): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($url($page - 1, $showArchived)) ?>">
              <i class="fa fa-chevron-left"></i> Previous
            </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($url($page + 1, $showArchived)) ?>">
              Next <i class="fa fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-cubes"></i>
      <h3><?= $query !== '' || $selectedType !== null || $selectedCategory !== null || $selectedStatus !== null || $selectedInventory !== null || $showArchived ? 'No matching items' : 'Your catalog is empty' ?></h3>
      <p><?= $query !== '' || $selectedType !== null || $selectedCategory !== null || $selectedStatus !== null || $selectedInventory !== null || $showArchived ? 'Try another search, type, category, status, inventory mode, or catalog view.' : 'Create the first product or service offered by your organization.' ?></p>
    </div>
  <?php endif; ?>
</div>
