<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var string $title */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>catalog-categories/">
      <i class="fa fa-arrow-left"></i> Back to categories
    </a>
    <p class="kontor-eyebrow">Catalog structure</p>
    <h2><?= $e($title) ?></h2>
    <p>Localized names, hierarchy, display order, and lifecycle status.</p>
  </header>

  <?= $form->render() ?>
</div>
