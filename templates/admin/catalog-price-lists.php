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
$date = static fn (?\DateTimeImmutable $value): string => $value?->format('M j, Y') ?? 'No limit';
$today = new \DateTimeImmutable('today');
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-medium-bottom">
    <div>
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Catalog pricing</p>
      <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Currency, validity periods, customer pricing, and quantity tiers.</p>
    </div>
    <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog/"><i class="fa fa-cubes"></i> Items</a></div>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-categories/"><i class="fa fa-folder-open"></i> Categories</a></div>
      <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-references/"><i class="fa fa-book"></i> References</a></div>
      <div><a class="uk-button uk-button-primary" href="<?= $e($adminUrl) ?>catalog-price-list/"><i class="fa fa-plus"></i> New price list</a></div>
    </div>
  </header>

  <form class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom" method="get" action="./" autocomplete="off">
    <div class="uk-grid-small uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@l">
        <div class="uk-search uk-search-default uk-width-1-1">
          <span uk-search-icon></span>
          <input class="uk-search-input" name="q" type="search" value="<?= $e($query) ?>" placeholder="Price list name" aria-label="Search price lists" autocomplete="off">
        </div>
      </div>
      <div class="uk-width-1-2 uk-width-medium@m">
        <select class="uk-select" name="status" aria-label="Price list status">
          <option value="">All statuses</option>
          <option value="active"<?= $selectedStatus === 'active' ? ' selected' : '' ?>>Active</option>
          <option value="inactive"<?= $selectedStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="uk-width-1-2 uk-width-medium@m">
        <select class="uk-select" name="validity" aria-label="Price list validity">
          <option value="">All validity periods</option>
          <option value="current"<?= $selectedValidity === 'current' ? ' selected' : '' ?>>Current</option>
          <option value="upcoming"<?= $selectedValidity === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
          <option value="expired"<?= $selectedValidity === 'expired' ? ' selected' : '' ?>>Expired</option>
        </select>
      </div>
      <div class="uk-width-1-2 uk-width-small@m">
        <select class="uk-select" name="currency" aria-label="Price list currency">
          <option value="">All currencies</option>
          <?php foreach ($currencyOptions as $code): ?>
            <option value="<?= $e($code) ?>"<?= $selectedCurrency === $code ? ' selected' : '' ?>><?= $e($code) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="uk-width-auto"><button class="uk-button uk-button-primary" type="submit">Filter</button></div>
    </div>
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-top">
      <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
        <?php if ($hasFilters): ?>
          <div><a class="uk-button uk-button-default uk-button-small kontor-button" href="./"><i class="fa fa-times"></i> Clear</a></div>
        <?php endif; ?>
        <div class="uk-text-meta"><?= $e($totalPriceLists) ?> price list<?= $totalPriceLists === 1 ? '' : 's' ?></div>
      </div>
      <div class="uk-text-meta"><?= $e(count($priceLists)) ?> shown</div>
    </div>
  </form>

  <?php if ($priceLists): ?>
    <form
      class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-bottom uk-hidden"
      id="catalog-price-list-bulk-form"
      method="post"
      action="<?= $e($adminUrl) ?>catalog-price-list-bulk-action/"
      data-kontor-bulk-form
      data-kontor-hide-empty
      data-entity-label="price list"
    >
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <input type="hidden" name="return_q" value="<?= $e($query) ?>">
      <input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>">
      <input type="hidden" name="return_validity" value="<?= $e($selectedValidity ?? '') ?>">
      <input type="hidden" name="return_currency" value="<?= $e($selectedCurrency ?? '') ?>">
      <input type="hidden" name="return_page" value="<?= $e($page) ?>">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
        <strong data-kontor-selected-count>0 selected</strong>
        <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
          <div><button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="activate" data-action-label="Activate"><i class="fa fa-play"></i> Activate</button></div>
          <div><button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="deactivate" data-action-label="Deactivate"><i class="fa fa-pause"></i> Deactivate</button></div>
        </div>
      </div>
    </form>

    <section class="uk-card uk-card-default uk-card-small uk-overflow-auto">
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-margin-remove">
        <thead>
          <tr>
            <th class="uk-table-shrink">
              <input class="uk-checkbox" type="checkbox" data-kontor-select-all="catalog-price-list-bulk-form" aria-label="Select all shown price lists">
            </th>
            <th>Price list</th>
            <th>Currency</th>
            <th>Validity</th>
            <th>Price tiers</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($priceLists as $priceList): ?>
            <?php
              $uid = $priceList->uid->toString();
              $validity = $priceList->validFrom !== null && $priceList->validFrom > $today
                  ? 'upcoming'
                  : ($priceList->validTo !== null && $priceList->validTo < $today ? 'expired' : 'current');
              $validityClass = $validity === 'current'
                  ? ' uk-label-success'
                  : ($validity === 'expired' ? ' uk-label-danger' : ' uk-label-warning');
            ?>
            <tr>
              <td>
                <input
                  class="uk-checkbox"
                  type="checkbox"
                  name="ids[]"
                  value="<?= $e($uid) ?>"
                  form="catalog-price-list-bulk-form"
                  data-kontor-select-item="catalog-price-list-bulk-form"
                  aria-label="Select <?= $e($priceList->name) ?>"
                >
              </td>
              <td class="kontor-list__body">
                <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($uid)) ?>"><strong><?= $e($priceList->name) ?></strong></a>
              </td>
              <td><code><?= $e($priceList->currencyCode) ?></code></td>
              <td>
                <span class="uk-label<?= $validityClass ?>"><?= $e($validity) ?></span>
                <div class="uk-text-meta uk-margin-small-top"><?= $e($date($priceList->validFrom)) ?> → <?= $e($date($priceList->validTo)) ?></div>
              </td>
              <td><?= $e($entryCounts[$uid] ?? 0) ?></td>
              <td><span class="uk-label<?= $priceList->status === 'active' ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e($priceList->status) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <?php if ($totalPages > 1): ?>
      <nav class="uk-flex uk-flex-between uk-flex-middle uk-margin-top" aria-label="Price list pages">
        <span class="uk-text-meta">Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <ul class="uk-pagination uk-margin-remove">
          <?php if ($page > 1): ?><li><a href="<?= $e($url($page - 1)) ?>"><span uk-pagination-previous></span> Previous</a></li><?php endif; ?>
          <?php if ($page < $totalPages): ?><li class="uk-margin-auto-left"><a href="<?= $e($url($page + 1)) ?>">Next <span uk-pagination-next></span></a></li><?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="uk-placeholder uk-text-center">
      <span uk-icon="icon: tag; ratio: 1.5"></span>
      <h3 class="uk-margin-small-top uk-margin-small-bottom"><?= $hasFilters ? 'No matching price lists' : 'No price lists yet' ?></h3>
      <p class="uk-text-muted uk-margin-remove"><?= $hasFilters ? 'Try another name, status, validity period, or currency.' : 'Create a price list, then add item and quantity tiers.' ?></p>
      <div class="uk-margin-top">
        <?php if ($hasFilters): ?>
          <a class="uk-button uk-button-default kontor-button" href="./"><i class="fa fa-times"></i> Clear filters</a>
        <?php else: ?>
          <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>catalog-price-list/"><i class="fa fa-plus"></i> Create price list</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
