<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var bool $canViewPriceLists */
/** @var bool $canEditPriceLists */
/** @var \Kontor\Catalog\Domain\PriceListEntry[] $priceEntries */
/** @var array<string, array{name: string, currency: string, status: string}> $priceListDetails */
/** @var array<string, string> $unitLabels */
/** @var string $selectedUnitCode */
/** @var array<string, string> $formLanguages */
/** @var string $defaultLanguage */
/** @var string $title */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static function (?\Kontor\SDK\ValueObjects\Money $value): string {
    if ($value === null) return 'Not set';
    $minor = $value->amountMinor();
    $negative = $minor < 0 ? '-' : '';
    $minor = abs($minor);
    return sprintf('%s%d.%02d %s', $negative, intdiv($minor, 100), $minor % 100, $value->currencyCode());
};
$quantity = static fn (float $number): string => rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
$field = static fn (string $name) => $form->getChildByName($name);
$value = static function (string $name) use ($form): string {
    $input = $form->getChildByName($name);
    return $input !== null ? (string) $input->attr('value') : '';
};
$options = static function (string $name) use ($form): array {
    $input = $form->getChildByName($name);
    return $input !== null && method_exists($input, 'getOptions') ? $input->getOptions() : [];
};
$errors = static fn (string $name): array => $form->getChildByName($name)?->getErrors() ?? [];
$formErrors = $form->getErrors();
$itemTypeOptions = $options('item_type');
$statusOptions = $options('status');
$unitOptions = $options('unit_code');
$taxOptions = $options('tax_code');
$categoryOptions = $options('category_uid');
$currencyOptions = $options('sales_currency');
$coveredPriceLists = count(array_unique(array_map(static fn ($entry): string => $entry->priceListUid, $priceEntries)));
$defaultDescription = $item?->description[$defaultLanguage] ?? $item?->description['en'] ?? '';
$categoryLabel = $item !== null ? ($categoryOptions[$item->categoryUid ?? ''] ?? 'Uncategorized') : 'Uncategorized';
$unitLabel = $item !== null ? ($unitLabels[$item->unitCode] ?? $item->unitCode) : '';
$taxLabel = $item !== null ? ($taxOptions[$item->taxCode ?? ''] ?? 'Not specified') : 'Not specified';
$translationLanguages = array_diff_key($formLanguages, [$defaultLanguage => true]);
$statusClass = static fn (string $status): string => match ($status) {
    'active' => ' uk-label-success',
    'inactive', 'discontinued' => ' uk-label-warning',
    default => '',
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow"><?= $item === null ? 'New catalog record' : 'Catalog workspace' ?></p><p><?= $item === null ? 'Create a reusable product or service for quotations, orders, invoices and price lists.' : 'Keep commercial identity, pricing and fulfillment behavior accurate wherever this item is used.' ?></p></div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>catalog/"><i class="fa fa-arrow-left"></i> Catalog</a>
      <?php if ($item !== null): ?><form method="post" action="<?= $e($adminUrl) ?>catalog-item-duplicate/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($item->uid->toString()) ?>"><button class="uk-button uk-button-default" type="submit"><i class="fa fa-copy"></i> Create a copy</button></form><?php endif; ?>
    </div>
  </header>

  <?php if ($item !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $e($itemTypeOptions[$item->itemType] ?? ucfirst($item->itemType)) ?></p><h2 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">At a glance</h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e($defaultDescription !== '' ? $defaultDescription : 'Add a concise description so teams know when to use this item.') ?></p></div>
        <div><span class="uk-label<?= $statusClass($item->status) ?>"><?= $e($statusOptions[$item->status] ?? ucfirst($item->status)) ?></span></div>
      </div>
      <div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-top" uk-grid>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($money($item->salesPrice)) ?></strong><span class="kontor-stat__label">Standard sales price</span></span></div></div>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-balance-scale"></i></span><span><strong class="kontor-stat__value"><?= $e($unitLabel) ?></strong><span class="kontor-stat__label">Billing unit</span></span></div></div>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-folder-open"></i></span><span><strong class="kontor-stat__value"><?= $e($categoryLabel) ?></strong><span class="kontor-stat__label">Category</span></span></div></div>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span><span><strong class="kontor-stat__value"><?= $item->trackInventory ? 'Tracked' : 'Not tracked' ?></strong><span class="kontor-stat__label">Inventory behavior</span></span></div></div>
      </div>
      <div class="uk-grid-small uk-child-width-auto uk-margin-medium-top" uk-grid><div><span class="uk-text-meta">SKU</span><strong class="uk-display-block uk-margin-small-top"><?= $e($item->sku ?: 'Not assigned') ?></strong></div><div><span class="uk-text-meta">Tax treatment</span><strong class="uk-display-block uk-margin-small-top"><?= $e($taxLabel) ?></strong></div></div>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-flex uk-flex-top"><span class="kontor-stat__icon uk-margin-small-right"><i class="fa fa-info-circle"></i></span><span><strong>About this section</strong><span class="uk-display-block uk-margin-small-top">Catalog records provide shared product or service data for sales documents, purchasing, pricing and inventory. Required fields define commercial identity; optional costs and translations can be completed when needed.</span><span class="uk-text-meta uk-display-block uk-margin-small-top">Changes affect future selections and documents. Existing issued documents keep their saved snapshots.</span></span></div>
  </section>

  <?php if ($formErrors !== []): ?><div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>The item could not be saved.</strong> Review the highlighted fields and try again.</p></div><?php endif; ?>

  <form class="uk-form-stacked" method="post" action="./<?= $item !== null ? '?id=' . $e(rawurlencode($item->uid->toString())) : '' ?>">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Identity and availability</p><h3 class="uk-card-title uk-margin-small-top">How teams recognize this item</h3><p class="uk-text-muted">Choose whether this is a product or service, give it a clear title and control where it remains available.</p>
          <div class="uk-grid-small uk-child-width-1-2@m uk-margin-medium-top" uk-grid>
            <div><label class="uk-form-label" for="catalog-item-type">Item type <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="catalog-item-type" name="item_type" required><?php foreach ($itemTypeOptions as $key => $label): ?><option value="<?= $e((string) $key) ?>"<?= $value('item_type') === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Products can participate in inventory; services represent time or non-stock work.</div><?php foreach ($errors('item_type') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
            <div><label class="uk-form-label" for="catalog-status">Status <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="catalog-status" name="status" required><?php foreach ($statusOptions as $key => $label): ?><option value="<?= $e((string) $key) ?>"<?= $value('status') === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Use Inactive to pause new use while preserving business history.</div><?php foreach ($errors('status') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          </div>
          <div class="uk-margin"><label class="uk-form-label" for="catalog-title-default">Default title (<?= $e(strtoupper($defaultLanguage)) ?>) <span class="uk-text-danger">*</span></label><input class="uk-input uk-margin-small-top" id="catalog-title-default" name="title_<?= $e($defaultLanguage) ?>" value="<?= $e($value('title_' . $defaultLanguage)) ?>" placeholder="A short name customers and teammates understand" required><div class="uk-text-meta uk-margin-small-top">Appears in catalog lists, quotations, orders and invoices.</div><?php foreach ($errors('title_' . $defaultLanguage) as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          <div class="uk-grid-small uk-child-width-1-2@m" uk-grid>
            <div><label class="uk-form-label" for="catalog-sku">SKU</label><input class="uk-input uk-margin-small-top" id="catalog-sku" name="sku" value="<?= $e($value('sku')) ?>" placeholder="For example: SERVICE-001"><div class="uk-text-meta uk-margin-small-top">Optional internal code for search, imports and integrations.</div><?php foreach ($errors('sku') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
            <div><label class="uk-form-label" for="catalog-barcode">Barcode</label><input class="uk-input uk-margin-small-top" id="catalog-barcode" name="barcode" value="<?= $e($value('barcode')) ?>" placeholder="EAN, UPC or another scannable code"><div class="uk-text-meta uk-margin-small-top">Optional. Usually left blank for services.</div><?php foreach ($errors('barcode') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          </div>
          <div class="uk-margin-remove-bottom uk-margin-top"><label class="uk-form-label" for="catalog-category">Category</label><select class="uk-select uk-margin-small-top" id="catalog-category" name="category_uid"><?php foreach ($categoryOptions as $key => $label): ?><option value="<?= $e((string) $key) ?>"<?= $value('category_uid') === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Optional. Categories improve browsing, reporting and catalog rules.</div><?php foreach ($errors('category_uid') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Description</p><h3 class="uk-card-title uk-margin-small-top">Explain what is included</h3><p class="uk-text-muted">Write enough context for sales documents and portals without adding internal secrets or unnecessary personal data.</p>
          <div class="uk-margin-medium-top"><label class="uk-form-label" for="catalog-description-default">Default description (<?= $e(strtoupper($defaultLanguage)) ?>)</label><textarea class="uk-textarea uk-margin-small-top" id="catalog-description-default" name="description_<?= $e($defaultLanguage) ?>" rows="6" placeholder="Scope, deliverables or product details customers should understand"><?= $e($value('description_' . $defaultLanguage)) ?></textarea><div class="uk-text-meta uk-margin-small-top">Optional. Keep it concise and suitable for customer-facing output.</div><?php foreach ($errors('description_' . $defaultLanguage) as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          <?php if ($translationLanguages !== []): ?><ul class="uk-accordion uk-margin-medium-top uk-margin-remove-bottom" uk-accordion><li><a class="uk-accordion-title uk-link-reset" href>Translations</a><div class="uk-accordion-content"><p class="uk-text-muted">Add localized content only for markets that need it. Blank translations fall back to the default language.</p><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@l" uk-grid><?php foreach ($translationLanguages as $locale => $languageLabel): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><h4 class="uk-margin-remove-top"><?= $e($languageLabel) ?> <span class="uk-text-meta"><?= $e(strtoupper($locale)) ?></span></h4><label class="uk-form-label" for="catalog-title-<?= $e($locale) ?>">Title</label><input class="uk-input uk-margin-small-top" id="catalog-title-<?= $e($locale) ?>" name="title_<?= $e($locale) ?>" value="<?= $e($value('title_' . $locale)) ?>"><label class="uk-form-label uk-display-block uk-margin-top" for="catalog-description-<?= $e($locale) ?>">Description</label><textarea class="uk-textarea uk-margin-small-top" id="catalog-description-<?= $e($locale) ?>" name="description_<?= $e($locale) ?>" rows="4"><?= $e($value('description_' . $locale)) ?></textarea></div></div><?php endforeach; ?></div></div></li></ul><?php endif; ?>
        </section>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Pricing</p><h3 class="uk-card-title uk-margin-small-top">Standard prices</h3><p class="uk-text-muted">Default amounts. Price-list tiers can override the sales price for customers or quantities.</p>
          <?php foreach (['sales' => ['Sales price', 'Default amount offered to customers.'], 'purchase' => ['Purchase price', 'What a supplier normally charges.'], 'cost' => ['Cost price', 'Your internal fully loaded cost.']] as $prefix => [$label, $note]): ?><div class="uk-margin"><label class="uk-form-label" for="catalog-<?= $e($prefix) ?>-price"><?= $e($label) ?></label><div class="uk-grid-small uk-margin-small-top" uk-grid><div class="uk-width-expand"><input class="uk-input" id="catalog-<?= $e($prefix) ?>-price" name="<?= $e($prefix) ?>_price" inputmode="decimal" value="<?= $e($value($prefix . '_price')) ?>" placeholder="0.00"></div><div class="uk-width-1-3"><select class="uk-select" name="<?= $e($prefix) ?>_currency" aria-label="<?= $e($label) ?> currency" required><?php foreach ($currencyOptions as $key => $currencyLabel): ?><option value="<?= $e((string) $key) ?>"<?= $value($prefix . '_currency') === (string) $key ? ' selected' : '' ?>><?= $e((string) $currencyLabel) ?></option><?php endforeach; ?></select></div></div><div class="uk-text-meta uk-margin-small-top"><?= $e($note) ?> Enter a decimal amount without a symbol.</div><?php foreach ($errors($prefix . '_price') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div><?php endforeach; ?>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Fulfillment</p><h3 class="uk-card-title uk-margin-small-top">Quantity and tax behavior</h3><p class="uk-text-muted">Controls how quantities are measured and how this item behaves in operational documents.</p>
          <div class="uk-margin"><label class="uk-form-label" for="catalog-unit">Unit <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="catalog-unit" name="unit_code" required><?php foreach ($unitOptions as $key => $label): ?><option value="<?= $e((string) $key) ?>"<?= $selectedUnitCode === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Used when quoting, ordering, invoicing and tracking quantities.</div><?php foreach ($errors('unit_code') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          <div class="uk-margin"><label class="uk-form-label" for="catalog-tax">Tax treatment</label><select class="uk-select uk-margin-small-top" id="catalog-tax" name="tax_code"><?php foreach ($taxOptions as $key => $label): ?><option value="<?= $e((string) $key) ?>"<?= $value('tax_code') === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Applied when the item is added to commercial documents.</div><?php foreach ($errors('tax_code') as $error): ?><div class="uk-text-danger uk-margin-small-top"><?= $e($error) ?></div><?php endforeach; ?></div>
          <div class="uk-margin-remove-bottom"><label><input class="uk-checkbox" name="track_inventory" type="checkbox" value="1"<?= (bool) ($field('track_inventory')?->attr('value') ?? $field('track_inventory')?->checked) ? ' checked' : '' ?>> <span class="uk-margin-small-left">Track inventory for this item</span></label><div class="uk-text-meta uk-margin-small-top">Enable only for stock-managed products. Services normally remain untracked.</div></div>
        </section>
      </div>
    </div>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom"><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Save changes</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $item === null ? 'Create the catalog record' : 'Update this catalog record' ?></h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Review prices, unit and status before saving because they affect future documents.</p></div><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>catalog/">Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"><i class="fa fa-save"></i> Save catalog item</button></div></div></div></section>
  </form>

  <?php if ($item !== null && $canViewPriceLists): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Price lists</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Customer and quantity pricing</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e((string) count($priceEntries)) ?> tier<?= count($priceEntries) === 1 ? '' : 's' ?> across <?= $e((string) $coveredPriceLists) ?> price list<?= $coveredPriceLists === 1 ? '' : 's' ?>. Add tiers only when the standard sales price should be overridden.</p></div><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>catalog-price-lists/"><i class="fa fa-tags"></i> Manage price lists</a></div></div>
      <?php if ($canEditPriceLists && $priceListDetails !== []): ?><form class="uk-form-stacked uk-margin-medium-top" method="get" action="<?= $e($adminUrl) ?>catalog-price-entry/"><input type="hidden" name="item" value="<?= $e($item->uid->toString()) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-expand@m"><label class="uk-form-label" for="catalog-tier-list">Add this item to a price list</label><select class="uk-select uk-margin-small-top" id="catalog-tier-list" name="list" required><?php foreach ($priceListDetails as $uid => $details): ?><option value="<?= $e($uid) ?>"><?= $e($details['name']) ?> · <?= $e($details['currency']) ?><?= $details['status'] !== 'active' ? ' · ' . $e(ucfirst($details['status'])) : '' ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">The next page sets minimum quantity, amount and validity.</div></div><div class="uk-width-auto@m"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add price tier</button></div></div></form><?php endif; ?>
      <?php if ($priceEntries !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($priceEntries as $entry): ?><?php $details = $priceListDetails[$entry->priceListUid] ?? ['name' => 'Unavailable price list', 'currency' => '', 'status' => 'unknown']; $quantityValue = $quantity($entry->minQuantity); ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($details['name']) ?></strong><div class="uk-text-meta uk-margin-small-top">From <?= $e($quantityValue) ?> <?= $e($unitLabel) ?> · <?= $e($entry->validFrom?->format('M j, Y') ?? 'No start date') ?> to <?= $e($entry->validTo?->format('M j, Y') ?? 'No end date') ?></div></div><div><strong><?= $e($money($entry->price)) ?></strong></div><div><span class="uk-label<?= $statusClass($details['status']) ?>"><?= $e(ucfirst($details['status'])) ?></span></div><?php if ($canEditPriceLists): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>catalog-price-entry/?list=<?= $e(rawurlencode($entry->priceListUid)) ?>&amp;item=<?= $e(rawurlencode($entry->itemUid)) ?>&amp;quantity=<?= $e(rawurlencode($quantityValue)) ?>">Edit tier</a></div><?php endif; ?></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-tags fa-2x uk-text-muted"></i><h3>No special pricing</h3><p class="uk-text-muted">This item currently uses its standard sales price everywhere.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
