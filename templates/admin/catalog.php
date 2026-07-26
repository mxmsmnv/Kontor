<?php

/** @var \Kontor\Catalog\Domain\CatalogItem[] $items */
/** @var string $query */
/** @var string|null $selectedType */
/** @var string|null $selectedCategory */
/** @var string|null $selectedStatus */
/** @var string|null $selectedInventory */
/** @var string|null $selectedUnit */
/** @var string|null $selectedTax */
/** @var string|null $selectedCurrency */
/** @var string|null $selectedPricing */
/** @var array<string, string> $unitOptions */
/** @var array<string, string> $taxOptions */
/** @var string[] $currencyOptions */
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

$url = static function (int $targetPage, bool $archived) use ($query, $selectedType, $selectedCategory, $selectedStatus, $selectedInventory, $selectedUnit, $selectedTax, $selectedCurrency, $selectedPricing): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'type' => $selectedType,
        'category' => $selectedCategory,
        'status' => $selectedStatus,
        'inventory' => $selectedInventory,
        'unit' => $selectedUnit,
        'tax' => $selectedTax,
        'currency' => $selectedCurrency,
        'pricing' => $selectedPricing,
        'archived' => $archived ? 1 : '',
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
$hasFilters = $query !== ''
    || $selectedType !== null
    || $selectedCategory !== null
    || $selectedStatus !== null
    || $selectedInventory !== null
    || $selectedUnit !== null
    || $selectedTax !== null
    || $selectedCurrency !== null
    || $selectedPricing !== null;
$filterParameters = array_filter([
    'q' => $query,
    'type' => $selectedType,
    'category' => $selectedCategory,
    'status' => $selectedStatus,
    'inventory' => $selectedInventory,
    'unit' => $selectedUnit,
    'tax' => $selectedTax,
    'currency' => $selectedCurrency,
    'pricing' => $selectedPricing,
    'archived' => $showArchived ? 1 : '',
], static fn (string|int|null $value): bool => $value !== null && $value !== '');
$filterUrl = static function (string $facet, string $value) use ($filterParameters): string {
    return './?' . http_build_query([...$filterParameters, $facet => $value]);
};
$clearFiltersUrl = $showArchived ? './?archived=1' : './';
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
    <select name="unit" aria-label="Unit of measure">
      <option value="">All units</option>
      <?php foreach ($unitOptions as $code => $label): ?>
        <option value="<?= $e($code) ?>"<?= $selectedUnit === $code ? ' selected' : '' ?>><?= $e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="tax" aria-label="Tax code">
      <option value="">All tax codes</option>
      <?php foreach ($taxOptions as $code => $label): ?>
        <option value="<?= $e($code) ?>"<?= $selectedTax === $code ? ' selected' : '' ?>><?= $e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="currency" aria-label="Sales currency">
      <option value="">All sales currencies</option>
      <?php foreach ($currencyOptions as $code): ?>
        <option value="<?= $e($code) ?>"<?= $selectedCurrency === $code ? ' selected' : '' ?>><?= $e($code) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="pricing" aria-label="Sales pricing">
      <option value="">All pricing states</option>
      <option value="priced"<?= $selectedPricing === 'priced' ? ' selected' : '' ?>>With sales price</option>
      <option value="unpriced"<?= $selectedPricing === 'unpriced' ? ' selected' : '' ?>>Without sales price</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?>
      <a class="kontor-viewtoggle" href="<?= $e($clearFiltersUrl) ?>">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
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
      <input type="hidden" name="return_unit" value="<?= $e($selectedUnit ?? '') ?>">
      <input type="hidden" name="return_tax" value="<?= $e($selectedTax ?? '') ?>">
      <input type="hidden" name="return_currency" value="<?= $e($selectedCurrency ?? '') ?>">
      <input type="hidden" name="return_pricing" value="<?= $e($selectedPricing ?? '') ?>">
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
      <table class="kontor-table kontor-catalogtable">
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
                <?php if ($item->trackInventory): ?>
                  <small><a class="kontor-catalogfacet" href="<?= $e($filterUrl('inventory', 'tracked')) ?>">Inventory tracked</a></small>
                <?php endif; ?>
              </td>
              <td><code><?= $e($item->sku ?: '—') ?></code></td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('type', $item->itemType)) ?>"><?= $e(ucfirst($item->itemType)) ?></a>
              </td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('category', $item->categoryUid ?? 'uncategorized')) ?>">
                  <?= $e($item->categoryUid !== null ? ($categoryNames[$item->categoryUid] ?? 'Unknown category') : '—') ?>
                </a>
              </td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('pricing', $item->salesPrice !== null ? 'priced' : 'unpriced')) ?>">
                  <?= $e($money($item->salesPrice)) ?>
                </a>
              </td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('unit', $item->unitCode)) ?>">
                  <?= $e($unitLabels[$item->unitCode] ?? $item->unitCode) ?> <small><code><?= $e($item->unitCode) ?></code></small>
                </a>
              </td>
              <td>
                <a class="kontor-pill<?= $item->status === 'active' ? '' : ' kontor-pill--inactive' ?>" href="<?= $e($filterUrl('status', $item->status)) ?>">
                  <?= $e($item->status) ?>
                </a>
              </td>
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
      <h3><?= $hasFilters || $showArchived ? 'No matching items' : 'Your catalog is empty' ?></h3>
      <p><?= $hasFilters || $showArchived ? 'Try another search, type, category, status, inventory mode, unit, tax code, currency, pricing state, or catalog view.' : 'Create the first product or service offered by your organization.' ?></p>
    </div>
  <?php endif; ?>
</div>
