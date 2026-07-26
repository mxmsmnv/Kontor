<?php

/** @var array<int, array{type: string, code: string, label: string, usage: int}> $references */
/** @var string $query */
/** @var string|null $selectedType */
/** @var int $totalReferences */
/** @var string $adminUrl */
/** @var callable $e */

$hasFilters = $query !== '' || $selectedType !== null;
$filterParameters = array_filter([
    'q' => $query,
    'type' => $selectedType,
], static fn (?string $value): bool => $value !== null && $value !== '');
$filterUrl = static function (string $facet, string $value) use ($filterParameters): string {
    return './?' . http_build_query([...$filterParameters, $facet => $value]);
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Catalog configuration</p>
      <h2>References</h2>
      <p>Stable unit and tax-category codes used by items, imports, and integrations.</p>
    </div>
    <div class="kontor-backupactions">
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog/">
        <i class="fa fa-cubes"></i> Items
      </a>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-categories/">
        <i class="fa fa-folder-open"></i> Categories
      </a>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>catalog-price-lists/">
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
    <button class="uk-button uk-button-primary kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?>
      <a class="kontor-viewtoggle" href="./">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
    <span class="kontor-secondary"><?= $e($totalReferences) ?> registered · <?= $e(count($references)) ?> shown</span>
  </form>

  <?php if ($references): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Reference</th><th>Type</th><th>Machine code</th><th>Active items</th></tr></thead>
        <tbody>
          <?php foreach ($references as $reference): ?>
            <tr>
              <td>
                <strong>
                  <a class="kontor-catalogfacet" href="<?= $e($filterUrl('q', $reference['label'])) ?>"><?= $e($reference['label']) ?></a>
                </strong>
              </td>
              <td>
                <a class="kontor-catalogfacet uk-label kontor-pill" href="<?= $e($filterUrl('type', $reference['type'])) ?>">
                  <?= $reference['type'] === 'unit' ? 'Unit' : 'Tax code' ?>
                </a>
              </td>
              <td>
                <a class="kontor-catalogfacet" href="<?= $e($filterUrl('q', $reference['code'])) ?>">
                  <code><?= $e($reference['code']) ?></code>
                </a>
              </td>
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
    <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
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
