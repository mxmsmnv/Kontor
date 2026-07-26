<?php

/** @var \Kontor\Catalog\Domain\PriceList[] $priceLists */
/** @var array<string, int> $entryCounts */
/** @var string $query */
/** @var string|null $selectedStatus */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalPriceLists */
/** @var string $adminUrl */
/** @var callable $e */

$url = static function (int $targetPage) use ($query, $selectedStatus): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'status' => $selectedStatus,
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));

    return $parameters === '' ? './' : './?' . $parameters;
};
$date = static fn (?\DateTimeImmutable $value): string => $value?->format('Y-m-d') ?? '—';
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
    <button class="kontor-button" type="submit">Filter</button>
    <span class="kontor-secondary"><?= $e($totalPriceLists) ?> total · <?= $e(count($priceLists)) ?> shown</span>
  </form>

  <?php if ($priceLists): ?>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table">
        <thead>
          <tr><th>Price list</th><th>Currency</th><th>Validity</th><th>Price tiers</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($priceLists as $priceList): ?>
            <?php $uid = $priceList->uid->toString(); ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($uid)) ?>"><?= $e($priceList->name) ?></a></strong></td>
              <td><code><?= $e($priceList->currencyCode) ?></code></td>
              <td><?= $e($date($priceList->validFrom)) ?> → <?= $e($date($priceList->validTo)) ?></td>
              <td><?= $e($entryCounts[$uid] ?? 0) ?></td>
              <td><span class="kontor-pill<?= $priceList->status === 'active' ? '' : ' kontor-pill--inactive' ?>"><?= $e($priceList->status) ?></span></td>
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
      <h3><?= $query !== '' || $selectedStatus !== null ? 'No matching price lists' : 'No price lists yet' ?></h3>
      <p><?= $query !== '' || $selectedStatus !== null ? 'Try another search or status.' : 'Create a price list, then add item and quantity tiers.' ?></p>
    </div>
  <?php endif; ?>
</div>
