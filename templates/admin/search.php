<?php

/** @var string $query */
/** @var \Kontor\SDK\DTO\SearchResult|null $result */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-searchhero">
    <p class="kontor-eyebrow">Global directory</p>
    <h2>Find anything in Kontor</h2>
    <p>Search contacts and companies from one place.</p>
    <form method="get" action="./">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Name, company or email" autofocus>
      <button class="kontor-button" type="submit">Search</button>
    </form>
  </header>

  <?php if ($result !== null): ?>
    <section class="kontor-searchresults">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Results</p>
          <h3><?= $e($result->total) ?> matches for “<?= $e($query) ?>”</h3>
        </div>
      </div>

      <?php if ($result->hits): ?>
        <div class="kontor-card kontor-resultlist">
          <?php foreach ($result->hits as $hit): ?>
            <a class="kontor-result" href="<?= $e($adminUrl) ?><?= $hit->entityType === 'company' ? 'company' : 'contact' ?>/?id=<?= $e(rawurlencode($hit->entityUid)) ?>">
              <span class="kontor-result__icon"><i class="fa fa-<?= $hit->entityType === 'company' ? 'building' : 'user' ?>"></i></span>
              <span class="kontor-result__body">
                <strong><?= $e($hit->title) ?></strong>
                <span><?= $e($hit->subtitle ?: ucfirst($hit->entityType)) ?></span>
              </span>
              <span class="kontor-pill kontor-pill--inactive"><?= $e($hit->entityType) ?></span>
              <i class="fa fa-arrow-right"></i>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="kontor-card kontor-empty">
          <i class="fa fa-search"></i>
          <h3>No results found</h3>
          <p>Try a longer name, company or email term.</p>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($query !== ''): ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-info-circle"></i>
      <h3>Enter at least two characters</h3>
    </div>
  <?php endif; ?>
</div>
