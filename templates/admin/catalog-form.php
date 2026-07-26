<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var bool $canViewPriceLists */
/** @var bool $canEditPriceLists */
/** @var \Kontor\Catalog\Domain\PriceListEntry[] $priceEntries */
/** @var array<string, array{name: string, status: string}> $priceListDetails */
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
$coveredPriceLists = count(array_unique(array_map(
    static fn (\Kontor\Catalog\Domain\PriceListEntry $entry): string => $entry->priceListUid,
    $priceEntries,
)));
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <div class="kontor-formhead__toolbar">
      <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-arrow-left"></i> Back to catalog
      </a>
      <?php if ($item !== null): ?>
        <form method="post" action="<?= $e($adminUrl) ?>catalog-item-duplicate/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>">
          <button class="kontor-button kontor-button--ghost" type="submit">
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
    <header class="kontor-pagehead">
      <div>
        <p class="kontor-eyebrow">Pricing coverage</p>
        <h2>Price-list tiers</h2>
        <p><?= $e(count($priceEntries)) ?> tier(s) across <?= $e($coveredPriceLists) ?> price list(s).</p>
      </div>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
        <i class="fa fa-tags"></i> Open price lists
      </a>
    </header>

    <?php if ($priceEntries): ?>
      <section class="kontor-card kontor-tablewrap">
        <table class="kontor-table">
          <thead>
            <tr><th>Price list</th><th>Minimum quantity</th><th>Price</th><th>Validity</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($priceEntries as $entry): ?>
              <?php
                $details = $priceListDetails[$entry->priceListUid] ?? ['name' => 'Unknown price list', 'status' => 'unknown'];
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
                <td><span class="kontor-pill<?= $details['status'] === 'active' ? '' : ' kontor-pill--inactive' ?>"><?= $e($details['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php else: ?>
      <div class="kontor-card kontor-empty">
        <i class="fa fa-tag"></i>
        <h3>No price-list tiers</h3>
        <p>Add this item to a price list when customer or quantity pricing is needed.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
