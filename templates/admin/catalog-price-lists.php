<?php

/** @var \Kontor\Catalog\Domain\PriceList[] $priceLists */
/** @var array<string, int> $entryCounts */
/** @var string $query */
/** @var string|null $selectedStatus */
/** @var string|null $selectedValidity */
/** @var string|null $selectedCurrency */
/** @var array<string, string> $currencyOptions */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalPriceLists */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$url = static function (int $targetPage) use ($query, $selectedStatus, $selectedValidity, $selectedCurrency): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'status' => $selectedStatus,
        'validity' => $selectedValidity,
        'currency' => $selectedCurrency,
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
$hasFilters = $query !== ''
    || $selectedStatus !== null
    || $selectedValidity !== null
    || $selectedCurrency !== null;
$filterParameters = array_filter([
    'q' => $query,
    'status' => $selectedStatus,
    'validity' => $selectedValidity,
    'currency' => $selectedCurrency,
], static fn (?string $value): bool => $value !== null && $value !== '');
$filterUrl = static function (string $facet, string $value) use ($filterParameters): string {
    return './?' . http_build_query([...$filterParameters, $facet => $value]);
};
$date = static fn (?\DateTimeImmutable $value): string => $value?->format('Y-m-d') ?? '—';
$today = new \DateTimeImmutable('today');
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Catalog pricing</p>
      <h2>Price lists</h2>
      <p>Currency, validity periods, customer pricing, and quantity tiers.</p>
    </div>
    <div class="kontor-backupactions">
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-cubes"></i> Items
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-categories/">
        <i class="fa fa-folder-open"></i> Categories
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-references/">
        <i class="fa fa-book"></i> References
      </a>
      <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-price-list/">
        <i class="fa fa-plus"></i> New price list
      </a>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Price list name">
    </label>
    <select name="status" aria-label="Price list status">
      <option value="">All statuses</option>
      <option value="active"<?= $selectedStatus === 'active' ? ' selected' : '' ?>>Active</option>
      <option value="inactive"<?= $selectedStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
    </select>
    <select name="validity" aria-label="Price list validity">
      <option value="">All validity periods</option>
      <option value="current"<?= $selectedValidity === 'current' ? ' selected' : '' ?>>Current</option>
      <option value="upcoming"<?= $selectedValidity === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
      <option value="expired"<?= $selectedValidity === 'expired' ? ' selected' : '' ?>>Expired</option>
    </select>
    <select name="currency" aria-label="Price list currency">
      <option value="">All currencies</option>
      <?php foreach ($currencyOptions as $code): ?>
        <option value="<?= $e($code) ?>"<?= $selectedCurrency === $code ? ' selected' : '' ?>><?= $e($code) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?>
      <a class="kontor-viewtoggle" href="./">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
    <span class="kontor-secondary"><?= $e($totalPriceLists) ?> total · <?= $e(count($priceLists)) ?> shown</span>
  </form>

  <?php if ($priceLists): ?>
    <form
      class="kontor-bulkactions"
      id="catalog-price-list-bulk-form"
      method="post"
      action="<?= $e($adminUrl) ?>catalog-price-list-bulk-action/"
      data-kontor-bulk-form
      data-entity-label="price list"
    >
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <input type="hidden" name="return_q" value="<?= $e($query) ?>">
      <input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>">
      <input type="hidden" name="return_validity" value="<?= $e($selectedValidity ?? '') ?>">
      <input type="hidden" name="return_currency" value="<?= $e($selectedCurrency ?? '') ?>">
      <input type="hidden" name="return_page" value="<?= $e($page) ?>">
      <span class="kontor-secondary" data-kontor-selected-count>0 selected</span>
      <button
        class="kontor-button kontor-button--ghost"
        type="submit"
        name="action"
        value="activate"
        data-action-label="Activate"
      >
        <i class="fa fa-play"></i> Activate selected
      </button>
      <button
        class="kontor-button kontor-button--ghost"
        type="submit"
        name="action"
        value="deactivate"
        data-action-label="Deactivate"
      >
        <i class="fa fa-pause"></i> Deactivate selected
      </button>
    </form>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table">
        <thead>
          <tr>
            <th class="kontor-selectcell">
              <input
                type="checkbox"
                data-kontor-select-all="catalog-price-list-bulk-form"
                aria-label="Select all shown price lists"
              >
            </th>
            <th>Price list</th><th>Currency</th><th>Validity</th><th>Price tiers</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($priceLists as $priceList): ?>
            <?php $uid = $priceList->uid->toString(); ?>
            <?php $validity = $priceList->validFrom !== null && $priceList->validFrom > $today
                ? 'upcoming'
                : ($priceList->validTo !== null && $priceList->validTo < $today ? 'expired' : 'current'); ?>
            <tr>
              <td class="kontor-selectcell">
                <input
                  type="checkbox"
                  name="ids[]"
                  value="<?= $e($uid) ?>"
                  form="catalog-price-list-bulk-form"
                  data-kontor-select-item="catalog-price-list-bulk-form"
                  aria-label="Select <?= $e($priceList->name) ?>"
                >
              </td>
              <td><strong><a href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($uid)) ?>"><?= $e($priceList->name) ?></a></strong></td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('currency', $priceList->currencyCode)) ?>">
                  <code><?= $e($priceList->currencyCode) ?></code>
                </a>
              </td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('validity', $validity)) ?>">
                  <?= $e($date($priceList->validFrom)) ?> → <?= $e($date($priceList->validTo)) ?>
                </a>
              </td>
              <td><?= $e($entryCounts[$uid] ?? 0) ?></td>
              <td>
                <a class="kontor-catalogfacet kontor-pill<?= $priceList->status === 'active' ? '' : ' kontor-pill--inactive' ?>" href="<?= $e($filterUrl('status', $priceList->status)) ?>">
                  <?= $e($priceList->status) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php if ($totalPages > 1): ?>
      <nav class="kontor-pagination" aria-label="Price list pages">
        <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <div>
          <?php if ($page > 1): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page - 1)) ?>"><i class="fa fa-chevron-left"></i> Previous</a><?php endif; ?>
          <?php if ($page < $totalPages): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page + 1)) ?>">Next <i class="fa fa-chevron-right"></i></a><?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-tags"></i>
      <h3><?= $hasFilters ? 'No matching price lists' : 'No price lists yet' ?></h3>
      <p><?= $hasFilters ? 'Try another search, status, validity period, or currency.' : 'Create a price list, then add item and quantity tiers.' ?></p>
    </div>
  <?php endif; ?>
</div>
