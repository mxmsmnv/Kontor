<?php

/** @var array{code: string, name: string} $values */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>inventory/">
      <i class="fa fa-arrow-left"></i> Back to inventory
    </a>
    <p class="kontor-eyebrow">Inventory · Location</p>
    <h2>Create warehouse</h2>
    <p>Add an active stock location for receipts, reservations, and transfers.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <form class="kontor-card kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="kontor-nativefield">
      <span>Code *</span>
      <input name="code" value="<?= $e($values['code']) ?>" placeholder="MAIN" maxlength="50" required>
    </label>
    <label class="kontor-nativefield">
      <span>Name *</span>
      <input name="name" value="<?= $e($values['name']) ?>" placeholder="Main warehouse" required>
    </label>
    <div class="kontor-nativeform__actions">
      <button class="kontor-button" type="submit" name="submit_save" value="1">
        <i class="fa fa-building"></i> Create warehouse
      </button>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>inventory/">Cancel</a>
    </div>
  </form>
</div>
