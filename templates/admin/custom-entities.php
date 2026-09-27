<?php

/** @var \Kontor\Entities\Domain\EntityDefinition[] $definitions */
/** @var array<string, int> $recordCounts */
/** @var array<string, int> $fieldCounts */
/** @var array<string, int> $viewCounts */
/** @var string $query */
/** @var bool $canManage */
/** @var string $adminUrl */
/** @var callable $e */

$matches = static function ($definition) use ($query): bool {
    if ($query === '') {
        return true;
    }

    return mb_stripos($definition->name, $query) !== false;
};
$visibleDefinitions = array_values(array_filter($definitions, $matches));
$totalRecords = array_sum($recordCounts);
$totalViews = array_sum($viewCounts);
$integrationCount = count(array_filter(
    $definitions,
    static fn ($definition): bool => $definition->apiExposed,
));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Workspace builder · Business data</p>
      <h2>Custom entities</h2>
      <p>Create purpose-built workspaces for information that does not belong in the standard CRM, such as assets, contracts, locations or inspections.</p>
    </div>
    <?php if ($canManage && $definitions !== []): ?>
      <div class="pw-module-actions kontor-pagehead__actions">
        <a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>custom-entity/">
          <i class="fa fa-plus"></i> New entity
        </a>
      </div>
    <?php endif; ?>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($definitions)) ?></strong><span class="kontor-stat__label">Data workspaces</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-list-alt"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $totalRecords) ?></strong><span class="kontor-stat__label">Records</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-filter"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $totalViews) ?></strong><span class="kontor-stat__label">Saved views</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-plug"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $integrationCount) ?></strong><span class="kontor-stat__label">Available to integrations</span></span></div></div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>
      <div>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">1 · Define</p>
        <h3 class="uk-h4 uk-margin-small-top uk-margin-remove-bottom"><i class="fa fa-sliders uk-margin-small-right"></i>Shape the workspace</h3>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Choose the fields people need, such as text, dates, amounts and relationships.</p>
      </div>
      <div>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">2 · Capture</p>
        <h3 class="uk-h4 uk-margin-small-top uk-margin-remove-bottom"><i class="fa fa-pencil-square-o uk-margin-small-right"></i>Add business records</h3>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Give the team a consistent place to create and maintain structured information.</p>
      </div>
      <div>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">3 · Focus</p>
        <h3 class="uk-h4 uk-margin-small-top uk-margin-remove-bottom"><i class="fa fa-eye uk-margin-small-right"></i>Save useful views</h3>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Filter and sort records for recurring work, then connect integrations only when needed.</p>
      </div>
    </div>
  </section>

  <?php if ($definitions !== []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Workspace directory</p>
        <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Your custom data</h3>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom"><?= $e((string) count($visibleDefinitions)) ?> of <?= $e((string) count($definitions)) ?> workspaces shown</p>
      </div>
      <form class="uk-form-stacked uk-margin" method="get" action="./">
        <div class="uk-grid-small uk-flex-bottom" uk-grid>
          <div class="uk-width-1-1 uk-width-expand@m">
            <label class="uk-form-label" for="entity-search">Search workspaces</label>
            <div class="uk-inline uk-width-1-1 uk-margin-small-top">
              <span class="uk-form-icon" uk-icon="icon: search"></span>
              <input class="uk-input" id="entity-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Search by workspace name">
            </div>
            <div class="uk-text-meta uk-margin-small-top">Search changes only this directory and does not affect your records.</div>
          </div>
          <div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-default uk-width-1-1" type="submit">Search</button></div>
          <?php if ($query !== ''): ?><div class="uk-width-1-1 uk-width-auto@m"><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="./">Reset</a></div><?php endif; ?>
        </div>
      </form>
    </section>

    <?php if ($visibleDefinitions !== []): ?>
      <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@xl" uk-grid>
        <?php foreach ($visibleDefinitions as $definition): $uid = $definition->uid->toString(); ?>
          <div>
            <article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid>
                <div class="uk-width-expand">
                  <span class="kontor-stat__icon"><i class="fa fa-cube"></i></span>
                  <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($definition->name) ?></h3>
                </div>
                <div><span class="uk-label<?= $definition->apiExposed ? ' uk-label-success' : '' ?>"><?= $definition->apiExposed ? 'Integration ready' : 'Workspace only' ?></span></div>
              </div>
              <div class="uk-grid-small uk-child-width-1-3 uk-margin" uk-grid>
                <div><div class="uk-text-meta">Fields</div><strong><?= $e((string) ($fieldCounts[$uid] ?? 0)) ?></strong></div>
                <div><div class="uk-text-meta">Records</div><strong><?= $e((string) ($recordCounts[$uid] ?? 0)) ?></strong></div>
                <div><div class="uk-text-meta">Views</div><strong><?= $e((string) ($viewCounts[$uid] ?? 0)) ?></strong></div>
              </div>
              <p class="uk-text-muted">Open this workspace to manage its structure, records and saved views.</p>
              <a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>custom-entity/?id=<?= $e(rawurlencode($uid)) ?>">Open workspace</a>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center">
        <i class="fa fa-search fa-2x uk-text-muted"></i>
        <h3>No matching workspaces</h3>
        <p>Try another name or reset the search to see the full directory.</p>
        <a class="uk-button uk-button-default uk-link-reset" href="./">Reset search</a>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center">
      <i class="fa fa-cubes fa-2x uk-text-muted"></i>
      <h3>Build your first data workspace</h3>
      <p>Start with one real business process. Examples include equipment registers, contract renewals, site inspections or partner onboarding.</p>
      <?php if ($canManage): ?>
        <a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>custom-entity/"><i class="fa fa-plus"></i> Create first entity</a>
      <?php else: ?>
        <p class="uk-text-meta">Ask a workspace administrator to create the first entity.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
