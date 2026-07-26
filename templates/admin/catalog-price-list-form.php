<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\PriceList|null $priceList */
/** @var \Kontor\Catalog\Domain\PriceListEntry[] $entries */
/** @var array<string, string> $itemNames */
/** @var string $title */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static function (\Kontor\SDK\ValueObjects\Money $value): string {
    $minor = $value->amountMinor();
    $negative = $minor < 0 ? '-' : '';
    $minor = abs($minor);

    return sprintf('%s%d.%02d %s', $negative, intdiv($minor, 100), $minor % 100, $value->currencyCode());
};
$quantity = static fn (float $value): string => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog-price-lists/">
      <i class="fa fa-arrow-left"></i> Back to price lists
    </a>
    <p class="kontor-eyebrow">Catalog pricing</p>
    <h2><?= $e($title) ?></h2>
    <p>Currency, lifecycle status, and optional validity period.</p>
  </header>

  <?= $form->render() ?>

  <?php if ($priceList !== null): ?>
    <header class="kontor-pagehead">
      <div>
        <p class="kontor-eyebrow">Tiered prices</p>
        <h2>Price tiers</h2>
        <p>Set prices per item and minimum order quantity.</p>
      </div>
      <a class="kontor-button" href="<?= $e($adminUrl) ?>catalog-price-entry/?list=<?= $e(rawurlencode($priceList->uid->toString())) ?>">
        <i class="fa fa-plus"></i> Add price tier
      </a>
    </header>

    <?php if ($entries): ?>
      <section class="kontor-card kontor-tablewrap">
        <table class="kontor-table">
          <thead><tr><th>Item</th><th>Minimum quantity</th><th>Price</th><th>Validity</th><th><span class="kontor-visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php $quantityValue = $quantity($entry->minQuantity); ?>
              <tr>
                <td><strong><a href="<?= $e($adminUrl) ?>catalog-price-entry/?list=<?= $e(rawurlencode($priceList->uid->toString())) ?>&amp;item=<?= $e(rawurlencode($entry->itemUid)) ?>&amp;quantity=<?= $e(rawurlencode($quantityValue)) ?>"><?= $e($itemNames[$entry->itemUid] ?? 'Archived item') ?></a></strong></td>
                <td><?= $e($quantityValue) ?></td>
                <td><?= $e($money($entry->price)) ?></td>
                <td><?= $e($entry->validFrom?->format('Y-m-d') ?? '—') ?> → <?= $e($entry->validTo?->format('Y-m-d') ?? '—') ?></td>
                <td class="kontor-queueactions">
                  <form method="post" action="<?= $e($adminUrl) ?>catalog-price-entry-action/">
                    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                    <input type="hidden" name="list" value="<?= $e($priceList->uid->toString()) ?>">
                    <input type="hidden" name="item" value="<?= $e($entry->itemUid) ?>">
                    <input type="hidden" name="quantity" value="<?= $e($quantityValue) ?>">
                    <button type="submit" title="Delete price tier" aria-label="Delete price tier"><i class="fa fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php else: ?>
      <div class="kontor-card kontor-empty">
        <i class="fa fa-tag"></i>
        <h3>No price tiers yet</h3>
        <p>Add a catalog item and its minimum-quantity price.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
