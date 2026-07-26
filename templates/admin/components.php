<?php

/** @var array $components */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">System</p>
      <h2>Components</h2>
      <p>Installed Kontor capabilities and their runtime status.</p>
    </div>
  </header>

  <section class="kontor-components">
    <?php foreach ($components as $component): ?>
      <?php $enabled = ($component['status'] ?? '') === 'enabled'; ?>
      <article class="kontor-card kontor-component">
        <div class="kontor-component__top">
          <h3><?= $e(ucwords(str_replace(['-', '_'], ' ', $component['name'] ?? 'Component'))) ?></h3>
          <span class="kontor-pill<?= $enabled ? '' : ' kontor-pill--inactive' ?>">
            <?= $e($component['status'] ?? 'unknown') ?>
          </span>
        </div>
        <p>Version <?= $e($component['version'] ?? '—') ?> · <?= $e($component['package'] ?? 'local') ?></p>
      </article>
    <?php endforeach; ?>
  </section>
</div>
