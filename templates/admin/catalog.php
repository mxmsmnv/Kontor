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
$hasAdvancedFilters = $selectedInventory !== null
    || $selectedUnit !== null
    || $selectedTax !== null
    || $selectedCurrency !== null
    || $selectedPricing !== null;
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
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-medium-bottom">
    <div>
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Products and services</p>
      <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Sellable items, service offerings, prices, units, and inventory behavior.</p>
    </div>
    <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-categories/"><i class="fa fa-folder-open"></i> Categories</a></div>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-price-lists/"><i class="fa fa-tags"></i> Price lists</a></div>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-references/"><i class="fa fa-book"></i> References</a></div>
      <div><a class="uk-button uk-button-primary" href="<?= $e($adminUrl) ?>catalog-item/"><i class="fa fa-plus"></i> New item</a></div>
    </div>
  </header>

  <form class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom" method="get" action="./">
    <div class="uk-grid-small uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@l">
        <div class="uk-search uk-search-default uk-width-1-1">
          <span uk-search-icon></span>
          <input class="uk-search-input" name="q" type="search" value="<?= $e($query) ?>" placeholder="Title, SKU, barcode or description" aria-label="Search catalog">
        </div>
      </div>
      <div class="uk-width-1-2 uk-width-medium@m">
        <select class="uk-select" name="type" aria-label="Item type">
          <option value="">Products and services</option>
          <option value="product"<?= $selectedType === 'product' ? ' selected' : '' ?>>Products</option>
          <option value="service"<?= $selectedType === 'service' ? ' selected' : '' ?>>Services</option>
        </select>
      </div>
      <div class="uk-width-1-2 uk-width-medium@m">
        <select class="uk-select" name="category" aria-label="Catalog category">
          <option value="">All categories</option>
          <option value="uncategorized"<?= $selectedCategory === 'uncategorized' ? ' selected' : '' ?>>Uncategorized</option>
          <?php foreach ($categoryOptions as $uid => $label): ?>
            <option value="<?= $e($uid) ?>"<?= $selectedCategory === $uid ? ' selected' : '' ?>><?= $e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="uk-width-1-2 uk-width-small@m">
        <select class="uk-select" name="status" aria-label="Item status">
          <option value="">All statuses</option>
          <option value="active"<?= $selectedStatus === 'active' ? ' selected' : '' ?>>Active</option>
          <option value="inactive"<?= $selectedStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
          <option value="discontinued"<?= $selectedStatus === 'discontinued' ? ' selected' : '' ?>>Discontinued</option>
        </select>
      </div>
      <div class="uk-width-auto">
        <button class="uk-button uk-button-primary" type="submit">Filter</button>
      </div>
    </div>

    <div id="catalog-advanced-filters" class="uk-margin-top"<?= $hasAdvancedFilters ? '' : ' hidden' ?>>
      <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-3@m uk-child-width-1-5@l" uk-grid>
        <div>
          <select class="uk-select" name="inventory" aria-label="Inventory tracking">
            <option value="">All inventory modes</option>
            <option value="tracked"<?= $selectedInventory === 'tracked' ? ' selected' : '' ?>>Tracked inventory</option>
            <option value="untracked"<?= $selectedInventory === 'untracked' ? ' selected' : '' ?>>Inventory not tracked</option>
          </select>
        </div>
        <div>
          <select class="uk-select" name="unit" aria-label="Unit of measure">
            <option value="">All units</option>
            <?php foreach ($unitOptions as $code => $label): ?>
              <option value="<?= $e($code) ?>"<?= $selectedUnit === $code ? ' selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <select class="uk-select" name="tax" aria-label="Tax code">
            <option value="">All tax codes</option>
            <?php foreach ($taxOptions as $code => $label): ?>
              <option value="<?= $e($code) ?>"<?= $selectedTax === $code ? ' selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <select class="uk-select" name="currency" aria-label="Sales currency">
            <option value="">All currencies</option>
            <?php foreach ($currencyOptions as $code): ?>
              <option value="<?= $e($code) ?>"<?= $selectedCurrency === $code ? ' selected' : '' ?>><?= $e($code) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <select class="uk-select" name="pricing" aria-label="Sales pricing">
            <option value="">All pricing states</option>
            <option value="priced"<?= $selectedPricing === 'priced' ? ' selected' : '' ?>>With sales price</option>
            <option value="unpriced"<?= $selectedPricing === 'unpriced' ? ' selected' : '' ?>>Without sales price</option>
          </select>
        </div>
      </div>
    </div>

    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-top">
      <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
        <div>
          <button
            class="uk-button uk-button-text"
            type="button"
            uk-toggle="target: #catalog-advanced-filters"
            aria-controls="catalog-advanced-filters"
          ><i class="fa fa-sliders"></i> More filters<?= $hasAdvancedFilters ? ' · active' : '' ?></button>
        </div>
        <?php if ($hasFilters): ?>
          <div><a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($clearFiltersUrl) ?>"><i class="fa fa-times"></i> Clear</a></div>
        <?php endif; ?>
        <div class="uk-text-meta"><?= $e($totalItems) ?> item<?= $totalItems === 1 ? '' : 's' ?></div>
      </div>
      <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
        <div><a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>export/?entity=catalog_item&amp;format=csv"><i class="fa fa-download"></i> Export CSV</a></div>
        <div><a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>import/?entity=catalog_item"><i class="fa fa-upload"></i> Import</a></div>
        <div>
          <a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($url(1, !$showArchived)) ?>">
            <i class="fa fa-<?= $showArchived ? 'cubes' : 'archive' ?>"></i>
            <?= $showArchived ? 'Active items' : 'Archive' ?>
          </a>
        </div>
      </div>
    </div>
  </form>

  <?php if ($items): ?>
    <form
      class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-bottom uk-hidden"
      id="catalog-bulk-form"
      method="post"
      action="<?= $e($adminUrl) ?>catalog-bulk-action/"
      data-kontor-bulk-form
      data-kontor-hide-empty
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
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
        <strong data-kontor-selected-count>0 selected</strong>
        <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
          <div><button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="activate" data-action-label="Activate"><i class="fa fa-play"></i> Activate</button></div>
          <div><button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="deactivate" data-action-label="Deactivate"><i class="fa fa-pause"></i> Deactivate</button></div>
          <div><button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="discontinue" data-action-label="Discontinue"><i class="fa fa-stop"></i> Discontinue</button></div>
          <div>
            <button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>" data-action-label="<?= $showArchived ? 'Restore' : 'Archive' ?>">
              <i class="fa fa-<?= $showArchived ? 'undo' : 'archive' ?>"></i> <?= $showArchived ? 'Restore' : 'Archive' ?>
            </button>
          </div>
        </div>
      </div>
    </form>

    <section class="uk-card uk-card-default uk-card-small uk-overflow-auto">
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-margin-remove">
        <thead>
          <tr>
            <th class="uk-table-shrink">
              <input class="uk-checkbox" type="checkbox" data-kontor-select-all="catalog-bulk-form" aria-label="Select all shown catalog items">
            </th>
            <th>Item</th>
            <th>SKU</th>
            <th>Type</th>
            <th>Category</th>
            <th>Sales price</th>
            <th>Unit</th>
            <th>Status</th>
            <th class="uk-table-shrink"><span class="kontor-visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $unitLabel = $unitLabels[$item->unitCode] ?? $item->unitCode; ?>
            <tr>
              <td>
                <input
                  class="uk-checkbox"
                  type="checkbox"
                  name="ids[]"
                  value="<?= $e($item->uid->toString()) ?>"
                  form="catalog-bulk-form"
                  data-kontor-select-item="catalog-bulk-form"
                  aria-label="Select <?= $e($item->titleIn('en') ?? reset($item->title) ?: 'catalog item') ?>"
                >
              </td>
              <td class="kontor-list__body">
                <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-item/?id=<?= $e(rawurlencode($item->uid->toString())) ?>">
                  <strong><?= $e($item->titleIn('en') ?? reset($item->title) ?: 'Untitled item') ?></strong>
                </a>
                <?php if ($item->trackInventory): ?>
                  <div class="uk-text-meta">Inventory tracked</div>
                <?php endif; ?>
              </td>
              <td><code><?= $e($item->sku ?: '—') ?></code></td>
              <td class="uk-text-capitalize"><?= $e($item->itemType) ?></td>
              <td><?= $e($item->categoryUid !== null ? ($categoryNames[$item->categoryUid] ?? 'Unknown category') : '—') ?></td>
              <td class="uk-text-nowrap"><?= $e($money($item->salesPrice)) ?></td>
              <td>
                <?= $e($unitLabel) ?>
                <?php if ($unitLabel !== $item->unitCode): ?><div class="uk-text-meta"><?= $e($item->unitCode) ?></div><?php endif; ?>
              </td>
              <td>
                <span class="uk-label<?= $item->status === 'active' ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e($item->status) ?></span>
              </td>
              <td>
                <form method="post" action="<?= $e($adminUrl) ?>catalog-item-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>">
                  <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
                  <input type="hidden" name="return_q" value="<?= $e($query) ?>">
                  <input type="hidden" name="return_type" value="<?= $e($selectedType ?? '') ?>">
                  <input type="hidden" name="return_category" value="<?= $e($selectedCategory ?? '') ?>">
                  <input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>">
                  <input type="hidden" name="return_inventory" value="<?= $e($selectedInventory ?? '') ?>">
                  <input type="hidden" name="return_unit" value="<?= $e($selectedUnit ?? '') ?>">
                  <input type="hidden" name="return_tax" value="<?= $e($selectedTax ?? '') ?>">
                  <input type="hidden" name="return_currency" value="<?= $e($selectedCurrency ?? '') ?>">
                  <input type="hidden" name="return_pricing" value="<?= $e($selectedPricing ?? '') ?>">
                  <input type="hidden" name="return_archived" value="<?= $showArchived ? '1' : '0' ?>">
                  <input type="hidden" name="return_page" value="<?= $e($page) ?>">
                  <button class="uk-button uk-button-default uk-button-small" type="submit" title="<?= $showArchived ? 'Restore item' : 'Archive item' ?>" aria-label="<?= $showArchived ? 'Restore item' : 'Archive item' ?>">
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
      <nav class="uk-flex uk-flex-between uk-flex-middle uk-margin-top" aria-label="Catalog pages">
        <span class="uk-text-meta">Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <ul class="uk-pagination uk-margin-remove">
          <?php if ($page > 1): ?><li><a href="<?= $e($url($page - 1, $showArchived)) ?>"><span uk-pagination-previous></span> Previous</a></li><?php endif; ?>
          <?php if ($page < $totalPages): ?><li class="uk-margin-auto-left"><a href="<?= $e($url($page + 1, $showArchived)) ?>">Next <span uk-pagination-next></span></a></li><?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="uk-placeholder uk-text-center">
      <span uk-icon="icon: grid; ratio: 1.5"></span>
      <h3 class="uk-margin-small-top uk-margin-small-bottom"><?= $hasFilters || $showArchived ? 'No matching items' : 'Your catalog is empty' ?></h3>
      <p class="uk-text-muted uk-margin-remove"><?= $hasFilters || $showArchived ? 'Try another search or filter combination.' : 'Create the first product or service offered by your organization.' ?></p>
    </div>
  <?php endif; ?>
</div>
