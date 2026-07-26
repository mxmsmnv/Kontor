<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var bool $canViewPriceLists */
/** @var bool $canEditPriceLists */
/** @var \Kontor\Catalog\Domain\PriceListEntry[] $priceEntries */
/** @var array<string, array{name: string, currency: string, status: string}> $priceListDetails */
/** @var array<string, string> $unitLabels */
/** @var string $title */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

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
$quantity = static fn (float $value): string => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
$coveredPriceLists = count(array_unique(array_map(
    static fn (\Kontor\Catalog\Domain\PriceListEntry $entry): string => $entry->priceListUid,
    $priceEntries,
)));
$unitLabel = $item !== null ? ($unitLabels[$item->unitCode] ?? $item->unitCode) : '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-margin-medium-bottom">
    <div>
      <a class="uk-button uk-button-text uk-margin-small-bottom" href="<?= $e($adminUrl) ?>catalog/"><i class="fa fa-arrow-left"></i> Catalog</a>
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Products and services</p>
      <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Identity, pricing, taxation, unit, translations, and inventory behavior.</p>
    </div>
    <?php if ($item !== null): ?>
      <form method="post" action="<?= $e($adminUrl) ?>catalog-item-duplicate/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>">
        <button class="uk-button uk-button-default" type="submit"><i class="fa fa-copy"></i> Duplicate item</button>
      </form>
    <?php endif; ?>
  </header>

  <?php if ($item !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom" aria-label="Item summary">
      <dl class="uk-grid-small uk-grid-divider uk-child-width-1-2 uk-child-width-1-3@m uk-child-width-1-6@l uk-margin-remove" uk-grid>
        <div>
          <dt class="uk-text-meta">Status</dt>
          <dd class="uk-margin-small-top"><span class="uk-label<?= $item->status === 'active' ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e($item->status) ?></span></dd>
        </div>
        <div>
          <dt class="uk-text-meta">Type</dt>
          <dd class="uk-margin-small-top uk-text-capitalize"><?= $e($item->itemType) ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta">SKU</dt>
          <dd class="uk-margin-small-top"><code><?= $e($item->sku ?: '—') ?></code></dd>
        </div>
        <div>
          <dt class="uk-text-meta">Sales price</dt>
          <dd class="uk-margin-small-top"><?= $e($money($item->salesPrice)) ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta">Unit</dt>
          <dd class="uk-margin-small-top"><?= $e($unitLabel) ?><?php if ($unitLabel !== $item->unitCode): ?> <span class="uk-text-meta"><?= $e($item->unitCode) ?></span><?php endif; ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta">Inventory</dt>
          <dd class="uk-margin-small-top"><?= $item->trackInventory ? 'Tracked' : 'Not tracked' ?></dd>
        </div>
      </dl>
    </section>
  <?php endif; ?>

  <section class="uk-margin-medium-bottom">
    <header class="uk-margin-bottom">
      <h2 class="uk-h3 uk-margin-remove">Item details</h2>
      <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Required business data first; optional translations stay collapsed until needed.</p>
    </header>
    <?= $form->render() ?>
  </section>

  <?php if ($item !== null && $canViewPriceLists): ?>
    <section class="uk-margin-large-top">
      <header class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-bottom">
        <div>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Pricing coverage</p>
          <h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom">Price-list tiers</h2>
          <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e(count($priceEntries)) ?> tier<?= count($priceEntries) === 1 ? '' : 's' ?> across <?= $e($coveredPriceLists) ?> price list<?= $coveredPriceLists === 1 ? '' : 's' ?>.</p>
        </div>
        <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
          <?php if ($canEditPriceLists && $priceListDetails): ?>
            <div>
              <form class="uk-flex uk-flex-middle uk-grid-small" method="get" action="<?= $e($adminUrl) ?>catalog-price-entry/" uk-grid>
                <input type="hidden" name="item" value="<?= $e($item->uid->toString()) ?>">
                <div>
                  <select class="uk-select" name="list" aria-label="Price list for new tier" required>
                    <?php foreach ($priceListDetails as $uid => $details): ?>
                      <option value="<?= $e($uid) ?>"><?= $e($details['name']) ?><?= $details['status'] !== 'active' ? ' · ' . $e($details['status']) : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add tier</button></div>
              </form>
            </div>
          <?php endif; ?>
          <div><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>catalog-price-lists/"><i class="fa fa-tags"></i> Price lists</a></div>
        </div>
      </header>

      <?php if ($priceEntries): ?>
        <div class="uk-card uk-card-default uk-card-small uk-overflow-auto">
          <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-margin-remove">
            <thead>
              <tr><th>Price list</th><th>Minimum quantity</th><th>Price</th><th>Validity</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($priceEntries as $entry): ?>
                <?php
                  $details = $priceListDetails[$entry->priceListUid] ?? [
                      'name' => 'Unknown price list',
                      'currency' => '',
                      'status' => 'unknown',
                  ];
                  $quantityValue = $quantity($entry->minQuantity);
                ?>
                <tr>
                  <td>
                    <?php if ($canEditPriceLists): ?>
                      <a class="uk-link-heading" href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($entry->priceListUid)) ?>"><strong><?= $e($details['name']) ?></strong></a>
                    <?php else: ?>
                      <strong><?= $e($details['name']) ?></strong>
                    <?php endif; ?>
                    <?php if ($details['currency'] !== ''): ?><div class="uk-text-meta"><?= $e($details['currency']) ?></div><?php endif; ?>
                  </td>
                  <td><?= $e($quantityValue) ?></td>
                  <td>
                    <?php if ($canEditPriceLists): ?>
                      <a href="<?= $e($adminUrl) ?>catalog-price-entry/?list=<?= $e(rawurlencode($entry->priceListUid)) ?>&amp;item=<?= $e(rawurlencode($entry->itemUid)) ?>&amp;quantity=<?= $e(rawurlencode($quantityValue)) ?>"><?= $e($money($entry->price)) ?></a>
                    <?php else: ?>
                      <?= $e($money($entry->price)) ?>
                    <?php endif; ?>
                  </td>
                  <td><?= $e($entry->validFrom?->format('Y-m-d') ?? '—') ?> → <?= $e($entry->validTo?->format('Y-m-d') ?? '—') ?></td>
                  <td><span class="uk-label<?= $details['status'] === 'active' ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e($details['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="uk-placeholder uk-text-center">
          <span uk-icon="icon: tag; ratio: 1.5"></span>
          <h3 class="uk-margin-small-top uk-margin-small-bottom">No price-list tiers</h3>
          <p class="uk-text-muted uk-margin-remove">Add this item to a price list when customer or quantity pricing is needed.</p>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
