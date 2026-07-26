<?php

/** @var array{code: string, name: string} $values */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>expenses/"><i class="fa fa-arrow-left"></i> Back to expenses</a><p class="kontor-eyebrow">Expenses · Classification</p><h2>Create expense category</h2></header>
  <?php if ($error !== ''): ?><div class="kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <form class="kontor-card kontor-nativeform" method="post" action="./">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="kontor-nativefield"><span>Code *</span><input name="code" value="<?= $e($values['code']) ?>" placeholder="TRAVEL" required></label>
    <label class="kontor-nativefield"><span>Name *</span><input name="name" value="<?= $e($values['name']) ?>" placeholder="Travel" required></label>
    <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_save" value="1">Create category</button><a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>expenses/">Cancel</a></div>
  </form>
</div>
