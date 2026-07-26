<?php

/** @var string $query */
/** @var \Kontor\SDK\DTO\SearchResult|null $result */
/** @var string $selectedEntityType */
/** @var array<string, string> $availableEntityTypes */
/** @var int $page */
/** @var int $totalPages */
/** @var string $adminUrl */
/** @var callable $e */

$scopeLabel = $selectedEntityType === ''
    ? (isset($availableEntityTypes['catalog_item']) ? 'Kontor' : 'contacts and companies')
    : strtolower($availableEntityTypes[$selectedEntityType]);
$resultPresentation = static fn (string $entityType): array => match ($entityType) {
    'company' => ['company', 'building', 'Company'],
    'catalog_item' => ['catalog-item', 'cube', 'Catalog item'],
    default => ['contact', 'user', 'Contact'],
};
$pageUrl = static function (int $targetPage) use ($query, $selectedEntityType): string {
    return './?' . http_build_query(array_filter([
        'q' => $query,
        'type' => $selectedEntityType,
        'page' => $targetPage,
    ], static fn (string|int $value): bool => $value !== ''));
};
$scopeUrl = static fn (string $entityType): string => './?' . http_build_query([
    'q' => $query,
    'type' => $entityType,
]);
$allTypesUrl = './?' . http_build_query(['q' => $query]);
$hasSearch = $query !== '' || $selectedEntityType !== '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-searchhero">
    <p class="kontor-eyebrow">Global directory</p>
    <h2>Find anything in Kontor</h2>
    <p>Search people, companies, products, and services from one place.</p>
    <form method="get" action="./">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Name, email, SKU or barcode" autofocus>
      <select name="type" aria-label="Entity type">
        <option value="">All available records</option>
        <?php foreach ($availableEntityTypes as $type => $label): ?>
          <option value="<?= $e($type) ?>"<?= $selectedEntityType === $type ? ' selected' : '' ?>><?= $e($label) ?> only</option>
        <?php endforeach; ?>
      </select>
      <button class="uk-button uk-button-primary kontor-button" type="submit">Search</button>
      <?php if ($hasSearch): ?>
        <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="./">
          <i class="fa fa-times"></i> Clear search
        </a>
      <?php endif; ?>
    </form>
  </header>

  <?php if ($result !== null): ?>
    <section class="kontor-searchresults">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Results</p>
          <h3><?= $e($result->total) ?> match<?= $result->total === 1 ? '' : 'es' ?> in <?= $e($scopeLabel) ?> for “<?= $e($query) ?>”</h3>
          <?php if ($result->total > count($result->hits)): ?>
            <p>
              Showing <?= $e(count($result->hits)) ?> result<?= count($result->hits) === 1 ? '' : 's' ?>
              on page <?= $e($page) ?> of <?= $e($totalPages) ?>.
            </p>
          <?php endif; ?>
        </div>
        <?php if ($selectedEntityType !== ''): ?>
          <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($allTypesUrl) ?>">
            <i class="fa fa-list"></i> All result types
          </a>
        <?php endif; ?>
      </div>

      <?php if ($result->hits): ?>
        <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-resultlist">
          <?php foreach ($result->hits as $hit): ?>
            <?php [$resultRoute, $resultIcon, $resultLabel] = $resultPresentation($hit->entityType); ?>
            <article class="kontor-result">
              <a class="kontor-result__main" href="<?= $e($adminUrl) ?><?= $e($hit->url ?: $resultRoute . '/?id=' . rawurlencode($hit->entityUid)) ?>">
                <span class="kontor-result__icon"><i class="fa fa-<?= $e($resultIcon) ?>"></i></span>
                <span class="kontor-result__body">
                  <strong><?= $e($hit->title) ?></strong>
                  <span><?= $e($hit->subtitle ?: ucfirst($hit->entityType)) ?></span>
                </span>
                <i class="fa fa-arrow-right"></i>
              </a>
              <a
                class="uk-label kontor-pill<?= $selectedEntityType === $hit->entityType ? '' : ' kontor-pill--inactive' ?>"
                href="<?= $e($scopeUrl($hit->entityType)) ?>"
                aria-label="Show only <?= $e($availableEntityTypes[$hit->entityType] ?? $hit->entityType) ?>"
                <?= $selectedEntityType === $hit->entityType ? 'aria-current="page"' : '' ?>
              ><?= $e($resultLabel) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <nav class="kontor-pagination" aria-label="Search result pages">
            <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
            <div>
              <?php if ($page > 1): ?>
                <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page - 1)) ?>">
                  <i class="fa fa-chevron-left"></i> Previous
                </a>
              <?php endif; ?>
              <?php if ($page < $totalPages): ?>
                <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page + 1)) ?>">
                  Next <i class="fa fa-chevron-right"></i>
                </a>
              <?php endif; ?>
            </div>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
          <i class="fa fa-search"></i>
          <h3>No results found</h3>
          <p>Try another name, email, SKU, or barcode within <?= $e($scopeLabel) ?>.</p>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($query !== ''): ?>
    <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
      <i class="fa fa-info-circle"></i>
      <h3>Enter at least two characters</h3>
    </div>
  <?php endif; ?>
</div>
