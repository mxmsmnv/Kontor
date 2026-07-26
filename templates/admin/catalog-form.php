<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var bool $canViewPriceLists */
/** @var bool $canEditPriceLists */
/** @var \Kontor\Catalog\Domain\PriceListEntry[] $priceEntries */
/** @var array<string, array{name: string, currency: string, status: string}> $priceListDetails */
/** @var string $title */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static function (\Kontor\SDK\ValueObjects\Money $value): string {
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
$priceListFilterUrl = static fn (string $facet, string $value): string =>
    $adminUrl . 'catalog-price-lists/?' . http_build_query([$facet => $value]);
$coveredPriceLists = count(array_unique(array_map(
    static fn (\Kontor\Catalog\Domain\PriceListEntry $entry): string => $entry->priceListUid,
    $priceEntries,
)));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <div class="kontor-formhead__toolbar">
      <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-arrow-left"></i> Back to catalog
      </a>
      <?php if ($item !== null): ?>
        <form method="post" action="<?= $e($adminUrl) ?>catalog-item-duplicate/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>">
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">
            <i class="fa fa-copy"></i> Duplicate item
          </button>
        </form>
      <?php endif; ?>
    </div>
    <p class="kontor-eyebrow">Products and services</p>
    <h2><?= $e($title) ?></h2>
    <p>Localized identity and description, pricing, taxation, unit, and inventory behavior.</p>
  </header>

  <?= $form->render() ?>

  <?php if ($item !== null && $canViewPriceLists): ?>
    <header class="pw-module-head kontor-pagehead">
      <div>
        <p class="kontor-eyebrow">Pricing coverage</p>
        <h2>Price-list tiers</h2>
        <p><?= $e(count($priceEntries)) ?> tier(s) across <?= $e($coveredPriceLists) ?> price list(s).</p>
      </div>
      <div class="kontor-priceactions">
        <?php if ($canEditPriceLists && $priceListDetails): ?>
          <form method="get" action="<?= $e($adminUrl) ?>catalog-price-entry/">
            <input type="hidden" name="item" value="<?= $e($item->uid->toString()) ?>">
            <select name="list" aria-label="Price list for new tier" required>
              <?php foreach ($priceListDetails as $uid => $details): ?>
                <option value="<?= $e($uid) ?>"><?= $e($details['name']) ?><?= $details['status'] !== 'active' ? ' · ' . $e($details['status']) : '' ?></option>
              <?php endforeach; ?>
            </select>
            <button class="uk-button uk-button-primary kontor-button" type="submit"><i class="fa fa-plus"></i> Add price tier</button>
          </form>
        <?php endif; ?>
        <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
          <i class="fa fa-tags"></i> Open price lists
        </a>
      </div>
    </header>

    <?php if ($priceEntries): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
        <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
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
                  <strong>
                    <?php if ($canEditPriceLists): ?>
                      <a href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($entry->priceListUid)) ?>"><?= $e($details['name']) ?></a>
                    <?php else: ?>
                      <?= $e($details['name']) ?>
                    <?php endif; ?>
                  </strong>
                  <?php if ($details['currency'] !== ''): ?>
                    <span class="kontor-secondary">
                      <a class="kontor-catalogfacet" href="<?= $e($priceListFilterUrl('currency', $details['currency'])) ?>">
                        <?= $e($details['currency']) ?> price lists
                      </a>
                    </span>
                  <?php endif; ?>
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
                <td>
                  <a
                    class="kontor-catalogfacet uk-label kontor-pill<?= $details['status'] === 'active' ? '' : ' kontor-pill--inactive' ?>"
                    href="<?= $e($priceListFilterUrl('status', $details['status'])) ?>"
                  ><?= $e($details['status']) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php else: ?>
      <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
        <i class="fa fa-tag"></i>
        <h3>No price-list tiers</h3>
        <p>Add this item to a price list when customer or quantity pricing is needed.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
