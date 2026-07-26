<?php

/** @var array{name: string, isDefault: bool} $values */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>crm-deals/">
      <i class="fa fa-arrow-left"></i> Back to deals
    </a>
    <p class="kontor-eyebrow">CRM foundation</p>
    <h2>Create pipeline</h2>
    <p>Kontor will add Incoming, Qualified, Proposal, Won, and Lost stages.</p>
  </header>

  <?php if ($error !== ''): ?>
    <div class="kontor-warning"><i class="fa fa-exclamation-triangle"></i><strong><?= $e($error) ?></strong></div>
  <?php endif; ?>

  <form class="kontor-card kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="kontor-nativefield kontor-nativefield--wide">
      <span>Name *</span>
      <input name="name" value="<?= $e($values['name']) ?>" placeholder="Sales pipeline" required>
    </label>
    <label class="kontor-nativecheck kontor-nativefield--wide">
      <input name="is_default" type="checkbox" value="1"<?= $values['isDefault'] ? ' checked' : '' ?>>
      <span>Use as the default pipeline for lead conversion</span>
    </label>
    <div class="kontor-nativeform__actions">
      <button class="kontor-button" type="submit" name="submit_save" value="1">
        <i class="fa fa-building"></i> Create pipeline
      </button>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>crm-deals/">Cancel</a>
    </div>
  </form>
</div>
