<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var \Kontor\Catalog\Domain\CatalogItem|null $item */
/** @var string $title */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
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
</div>
