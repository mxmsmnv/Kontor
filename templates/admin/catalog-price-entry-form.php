<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\PriceList $priceList */
/** @var string $title */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog-price-list/?id=<?= $e(rawurlencode($priceList->uid->toString())) ?>">
      <i class="fa fa-arrow-left"></i> Back to <?= $e($priceList->name) ?>
    </a>
    <p class="kontor-eyebrow">Catalog pricing</p>
    <h2><?= $e($title) ?></h2>
    <p>Item, minimum quantity, <?= $e($priceList->currencyCode) ?> price, and optional validity period.</p>
  </header>

  <?= $form->render() ?>
</div>
