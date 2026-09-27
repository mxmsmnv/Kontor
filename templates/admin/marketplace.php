<?php

/** @var \Kontor\Marketplace\Domain\Registry[] $registries */
/** @var \Kontor\Marketplace\Domain\MarketplaceListing[] $listings */
/** @var \Kontor\Marketplace\Domain\Publisher[] $publishers */
/** @var \Kontor\Marketplace\Domain\Advisory[] $advisories */
/** @var array<string, \Kontor\Marketplace\DTO\InstallabilityResult> $installability */
/** @var array{listings: int, advisories: int, skipped: string[]}|null $syncResult */
/** @var bool $canManage */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$activeRegistries = array_values(array_filter($registries, static fn ($registry): bool => $registry->isActive()));
$trustedRegistries = array_values(array_filter($registries, static fn ($registry): bool => $registry->trusted));
$verifiedPublishers = [];
foreach ($publishers as $publisher) {
    if ($publisher->verified) {
        $verifiedPublishers[$publisher->name] = true;
    }
}
$compatibleCount = 0;
foreach ($listings as $listing) {
    $check = $installability[$listing->registryName . ':' . $listing->package] ?? null;
    if ($check?->isInstallable()) {
        $compatibleCount++;
    }
}
$criticalAdvisories = array_values(array_filter(
    $advisories,
    static fn ($advisory): bool => $advisory->severity === 'critical',
));
$severityClasses = [
    'critical' => ' uk-label-danger',
    'high' => ' uk-label-danger',
    'medium' => ' uk-label-warning',
    'low' => ' uk-label-success',
];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Ecosystem · Components and security</p><h2>Marketplace</h2><p>Discover compatible Kontor components and review publisher trust and security guidance before adoption.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/"><i class="fa fa-cubes"></i> Installed components</a></div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-cube"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($listings)) ?></strong><span class="kontor-stat__label">Available listings</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $compatibleCount > 0 ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-check-circle"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $compatibleCount) ?></strong><span class="kontor-stat__label">Compatible now</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-certificate"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($verifiedPublishers)) ?></strong><span class="kontor-stat__label">Verified publishers</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $criticalAdvisories !== [] ? ' kontor-stat__icon--danger' : ' kontor-stat__icon--success' ?>"><i class="fa fa-shield"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($criticalAdvisories)) ?></strong><span class="kontor-stat__label">Critical advisories</span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3><p>Marketplace combines registry metadata, dependency checks and security advisories. Use it to evaluate a component before adoption; manage modules already present on this site from <strong>Installed components</strong>.</p></div>

  <?php if ($syncResult !== null): ?>
    <div class="uk-alert-success uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-check-circle"></i> Registry data updated</h3><p><?= $e((string) $syncResult['listings']) ?> component listing(s) and <?= $e((string) $syncResult['advisories']) ?> security advisory item(s) were synchronized.</p><?php if ($syncResult['skipped'] !== []): ?><details><summary><?= $e((string) count($syncResult['skipped'])) ?> item(s) need attention</summary><ul class="uk-list uk-list-bullet uk-margin-small-top"><?php foreach ($syncResult['skipped'] as $skipped): ?><li><?= $e($skipped) ?></li><?php endforeach; ?></ul></details><?php endif; ?></div>
  <?php endif; ?>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body" data-kontor-marketplace>
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Component discovery</p><h3 class="uk-card-title uk-margin-small-top">Catalog</h3><p class="uk-text-muted uk-margin-small-top">Compatibility reflects the current PHP and ProcessWire environment plus known dependency and security constraints.</p></div><div><span class="uk-label" data-kontor-marketplace-count><?= $e((string) count($listings)) ?> shown</span></div></div>

        <?php if ($listings !== []): ?>
          <div class="uk-grid-small uk-margin-medium-top" uk-grid>
            <div class="uk-width-1-1 uk-width-expand@s"><label class="uk-form-label" for="marketplace-search">Find a component</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon"><i class="fa fa-search"></i></span><input class="uk-input" id="marketplace-search" type="search" placeholder="Name, publisher or capability" data-kontor-marketplace-search></div></div>
            <div class="uk-width-1-1 uk-width-1-3@s"><label class="uk-form-label" for="marketplace-compatibility">Compatibility</label><select class="uk-select uk-margin-small-top" id="marketplace-compatibility" data-kontor-marketplace-state><option value="">All listings</option><option value="compatible">Compatible</option><option value="blocked">Needs attention</option></select></div>
          </div>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-margin-medium-top" uk-grid>
            <?php foreach ($listings as $listing): ?>
              <?php
              $check = $installability[$listing->registryName . ':' . $listing->package];
              $compatible = $check->isInstallable();
              $publisherVerified = $listing->publisherName !== null && isset($verifiedPublishers[$listing->publisherName]);
              $searchText = strtolower(implode(' ', array_filter([
                  $listing->title,
                  $listing->name,
                  $listing->description,
                  $listing->publisherName,
                  $listing->license,
              ])));
              ?>
              <div data-kontor-marketplace-listing data-search="<?= $e($searchText) ?>" data-state="<?= $compatible ? 'compatible' : 'blocked' ?>">
                <article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
                  <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><span class="kontor-stat__icon"><i class="fa fa-cube"></i></span></div><div><span class="uk-label<?= $compatible ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $compatible ? 'Compatible' : 'Needs attention' ?></span></div></div>
                  <h4 class="uk-card-title uk-margin-small-top"><?= $e($listing->title ?? $listing->name) ?></h4>
                  <p class="uk-text-muted"><?= $e($listing->description ?: 'No description was provided by this registry.') ?></p>
                  <ul class="uk-list uk-list-divider uk-margin-top"><li><span class="uk-text-meta">Version</span><div class="uk-margin-small-top"><?= $e($listing->version) ?><?= $listing->license !== null ? ' · ' . $e($listing->license) : '' ?></div></li><li><span class="uk-text-meta">Publisher</span><div class="uk-margin-small-top"><?= $e($listing->publisherName ?? 'Unknown publisher') ?><?php if ($publisherVerified): ?> <span class="uk-label uk-label-success">Verified</span><?php endif; ?></div></li></ul>
                  <?php if (!$check->dependencies->satisfied): ?><div class="uk-alert-warning" uk-alert><p><i class="fa fa-puzzle-piece"></i> <?= $e(implode(' ', $check->dependencies->problems())) ?></p></div><?php endif; ?>
                  <?php if ($check->hasCriticalAdvisory()): ?><div class="uk-alert-danger" uk-alert><p><i class="fa fa-shield"></i> A critical advisory affects this version.</p></div><?php endif; ?>
                  <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-top" uk-grid><div><span class="uk-text-meta">Source: <?= $e(ucfirst($listing->registryName)) ?></span></div><div><?php if ($listing->repositoryUrl !== null): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($listing->repositoryUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="fa fa-external-link"></i> Project</a><?php endif; ?></div></div>
                  <details class="uk-margin-top"><summary>Package details</summary><div class="uk-text-meta uk-margin-small-top"><?= $e($listing->package) ?> · synchronized <?= $e($listing->syncedAt->format('M j, Y · H:i')) ?></div></details>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="uk-placeholder uk-text-center uk-margin-medium-top" data-kontor-marketplace-empty hidden><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching components</h4><p class="uk-text-muted">Try another search or compatibility filter.</p></div>
        <?php else: ?>
          <div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-cubes fa-2x uk-text-muted"></i><h4>The catalog is waiting for registry data</h4><p class="uk-text-muted">The official source is configured but has not been synchronized yet. An administrator can import a signed or otherwise verified registry payload below.</p><?php if (!$canManage): ?><p class="uk-text-meta">Ask a Marketplace administrator to synchronize an active registry.</p><?php endif; ?></div>
        <?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Security guidance</p><h3 class="uk-card-title uk-margin-small-top">Advisories</h3><p class="uk-text-muted">Known issues affecting versions published by synchronized registries.</p>
        <?php if ($advisories !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($advisories as $advisory): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($advisory->title) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($advisory->package) ?> · affects <?= $e($advisory->affectedVersions) ?></div></div><div><span class="uk-label<?= $severityClasses[$advisory->severity] ?? '' ?>"><?= $e(ucfirst($advisory->severity)) ?></span></div></div><?php if ($advisory->description !== null): ?><p class="uk-text-muted uk-margin-small-top"><?= $e($advisory->description) ?></p><?php endif; ?><div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-top"><span class="uk-text-meta"><?= $e($advisory->publishedAt->format('M j, Y')) ?></span><?php if ($advisory->url !== null): ?><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($advisory->url) ?>" target="_blank" rel="noopener noreferrer">Guidance</a><?php endif; ?></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-top"><i class="fa fa-shield fa-2x uk-text-muted"></i><h4>No synchronized advisories</h4><p class="uk-text-muted"><?= $listings === [] ? 'Security data will appear after the first registry synchronization.' : 'No known advisories affect the synchronized catalog.' ?></p></div><?php endif; ?>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Publisher identity</p><h3 class="uk-card-title uk-margin-small-top">Publishers</h3><p class="uk-text-muted">Verification means the publisher was introduced through a trusted registry.</p>
        <?php if ($publishers !== []): ?><ul class="uk-list uk-list-divider uk-margin-top"><?php foreach ($publishers as $publisher): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($publisher->name) ?></strong><?php if ($publisher->url !== null): ?><div class="uk-text-meta uk-margin-small-top"><?= $e($publisher->url) ?></div><?php endif; ?></div><div><span class="uk-label<?= $publisher->verified ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $publisher->verified ? 'Verified' : 'Unverified' ?></span></div></div></li><?php endforeach; ?></ul><?php else: ?><p class="uk-text-muted uk-margin-top">Publisher identities will appear with synchronized listings.</p><?php endif; ?>
      </section>
    </div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Catalog sources</p><h3 class="uk-card-title uk-margin-small-top">Registries</h3><p class="uk-text-muted uk-margin-small-top">Registries provide component descriptions, publisher identities and advisory data. Trust a custom source only after verifying its owner and delivery process.</p></div><div><span class="uk-label"><?= $e((string) count($activeRegistries)) ?> active · <?= $e((string) count($trustedRegistries)) ?> trusted</span></div></div>
    <?php if ($registries !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($registries as $registry): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><i class="fa fa-database uk-margin-small-right"></i><?= $e(ucfirst($registry->name)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $registry->type === 'official' ? 'Official Kontor source' : 'Custom source' ?> · <?= $registry->trusted ? 'Trusted publisher data' : 'Untrusted publisher data' ?> · <?= $registry->lastSyncedAt !== null ? 'Updated ' . $e($registry->lastSyncedAt->format('M j, Y · H:i')) : 'Never synchronized' ?></div><details class="uk-margin-small-top"><summary>Source address</summary><div class="uk-text-meta uk-margin-small-top uk-text-break"><?= $e($registry->url) ?></div></details></div><div class="uk-flex uk-flex-middle uk-grid-small" uk-grid><div><span class="uk-label<?= $registry->isActive() ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $registry->isActive() ? 'Active' : 'Disabled' ?></span></div><?php if ($canManage): ?><div><form method="post" action="<?= $e($adminUrl) ?>marketplace-registry-toggle/" data-kontor-confirm="<?= $registry->isActive() ? 'Disable this registry? Its current catalog remains visible, but it should not receive new synchronization data.' : 'Enable this registry for future synchronization?' ?>"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="registry_name" value="<?= $e($registry->name) ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><i class="fa fa-<?= $registry->isActive() ? 'pause' : 'play' ?>"></i> <?= $registry->isActive() ? 'Disable' : 'Enable' ?></button></form></div><?php endif; ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>

    <?php if ($canManage): ?>
      <div class="uk-grid-medium uk-margin-medium-top" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@l"><details><summary class="uk-button uk-button-default"><i class="fa fa-plus"></i> Add custom registry</summary><form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>marketplace-registry/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><div class="uk-margin"><label class="uk-form-label" for="marketplace-registry-name">Registry name</label><input class="uk-input uk-margin-small-top" id="marketplace-registry-name" name="name" maxlength="64" pattern="[a-z][a-z0-9_-]{1,63}" placeholder="partner_catalog" aria-describedby="marketplace-registry-name-help" required><div class="uk-text-meta uk-margin-small-top" id="marketplace-registry-name-help">Use 2–64 lowercase letters, numbers, underscores or hyphens. The name identifies the source in Marketplace.</div></div><div class="uk-margin"><label class="uk-form-label" for="marketplace-registry-url">Registry index URL</label><input class="uk-input uk-margin-small-top" id="marketplace-registry-url" name="url" type="url" maxlength="2048" placeholder="https://publisher.example/registry.json" aria-describedby="marketplace-registry-url-help" required><div class="uk-text-meta uk-margin-small-top" id="marketplace-registry-url-help">Use the complete HTTP or HTTPS address supplied by the registry owner.</div></div><label><input class="uk-checkbox uk-margin-small-right" name="trusted" type="checkbox" value="1"> Trust publisher identities from this source</label><div class="uk-text-meta uk-margin-small-top">Only enable trust after independently verifying the registry owner and update channel.</div><div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add registry</button></div></form></details></div>
        <div class="uk-width-1-1 uk-width-1-2@l"><details><summary class="uk-button uk-button-default"><i class="fa fa-code"></i> Import registry data</summary><div class="uk-margin-medium-top"><div class="uk-alert-warning" uk-alert><p><i class="fa fa-exclamation-triangle"></i> Developer tool: import only data obtained from and verified against the selected registry.</p></div><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>marketplace-import/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><div class="uk-margin"><label class="uk-form-label" for="marketplace-import-registry">Target registry</label><select class="uk-select uk-margin-small-top" id="marketplace-import-registry" name="registry_name" aria-describedby="marketplace-import-registry-help" required><?php foreach ($registries as $registry): ?><option value="<?= $e($registry->name) ?>"<?= !$registry->isActive() ? ' disabled' : '' ?>><?= $e(ucfirst($registry->name)) ?><?= !$registry->isActive() ? ' · disabled' : '' ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top" id="marketplace-import-registry-help">Data is attributed to this source and updates its synchronization time.</div></div><div class="uk-margin"><label class="uk-form-label" for="marketplace-import-json">Registry JSON</label><textarea class="uk-textarea uk-margin-small-top" id="marketplace-import-json" name="payload_json" rows="10" placeholder='{"components":[],"advisories":[]}' aria-describedby="marketplace-import-json-help" required></textarea><div class="uk-text-meta uk-margin-small-top" id="marketplace-import-json-help">Paste valid JSON up to 1 MB. Invalid or unsupported items are reported without being imported.</div></div><div class="uk-flex uk-flex-right"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-refresh"></i> Synchronize data</button></div></form></div></details></div>
      </div>
    <?php endif; ?>
  </section>
</div>
