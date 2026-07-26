<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var string $backUrl */
/** @var string $backLabel */
/** @var string $eyebrow */
/** @var string $title */
/** @var string $description */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($backUrl) ?>">
      <i class="fa fa-arrow-left"></i> <?= $e($backLabel) ?>
    </a>
    <p class="kontor-eyebrow"><?= $e($eyebrow) ?></p>
    <h2><?= $e($title) ?></h2>
    <p><?= $e($description) ?></p>
  </header>
  <?= $form->render() ?>
</div>
