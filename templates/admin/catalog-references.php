<?php

/** @var array<int, array{type: string, code: string, label: string, usage: int}> $references */
/** @var string $query */
/** @var string|null $selectedType */
/** @var int $totalReferences */
/** @var string $adminUrl */
/** @var callable $e */

$hasFilters = $query !== '' || $selectedType !== null;
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Catalog configuration</p>
      <h2>References</h2>
      <p>Stable unit and tax-category codes used by items, imports, and integrations.</p>
    </div>
    <div class="kontor-backupactions">
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-cubes"></i> Items
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-categories/">
        <i class="fa fa-folder-open"></i> Categories
      </a>
      <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
        <i class="fa fa-tags"></i> Price lists
      </a>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Code or label">
    </label>
    <select name="type" aria-label="Reference type">
      <option value="">Units and tax codes</option>
      <option value="unit"<?= $selectedType === 'unit' ? ' selected' : '' ?>>Units</option>
      <option value="tax"<?= $selectedType === 'tax' ? ' selected' : '' ?>>Tax codes</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?>
      <a class="kontor-viewtoggle" href="./">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
    <span class="kontor-secondary"><?= $e($totalReferences) ?> registered · <?= $e(count($references)) ?> shown</span>
  </form>

  <?php if ($references): ?>
    <section class="kontor-card kontor-tablewrap">
      <table class="kontor-table">
        <thead><tr><th>Reference</th><th>Type</th><th>Machine code</th><th>Active items</th></tr></thead>
        <tbody>
          <?php foreach ($references as $reference): ?>
            <tr>
              <td><strong><?= $e($reference['label']) ?></strong></td>
              <td><span class="kontor-pill"><?= $reference['type'] === 'unit' ? 'Unit' : 'Tax code' ?></span></td>
              <td><code><?= $e($reference['code']) ?></code></td>
              <td>
                <?php if ($reference['usage'] > 0): ?>
                  <a href="<?= $e($adminUrl) ?>catalog/?<?= $reference['type'] === 'unit' ? 'unit' : 'tax' ?>=<?= rawurlencode($reference['code']) ?>">
                    <?= $e($reference['usage']) ?>
                  </a>
                <?php else: ?>
                  0
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-book"></i>
      <h3>No matching references</h3>
      <p>Try another code, label, or reference type.</p>
    </div>
  <?php endif; ?>

  <p class="kontor-import__notice">
    <i class="fa fa-info-circle"></i>
    Tax codes are generic categories. Jurisdiction-specific rates belong to localization components.
  </p>
</div>
