<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var string $title */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog/">
      <i class="fa fa-arrow-left"></i> Back to catalog
    </a>
    <p class="kontor-eyebrow">Products and services</p>
    <h2><?= $e($title) ?></h2>
    <p>Commercial identity, pricing, taxation, unit, and inventory behavior.</p>
  </header>

  <?= $form->render() ?>
</div>
