<?php

/** @var \Kontor\Core\Domain\AuditEvent[] $events */
/** @var string $query */
/** @var array{components: string[], entityTypes: string[], actions: string[]} $filterOptions */
/** @var string|null $selectedComponent */
/** @var string|null $selectedEntityType */
/** @var string|null $selectedAction */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalEvents */
/** @var \Kontor\Core\Application\AuditChangePresenter $changePresenter */
/** @var array<string, string> $actorLabels */
/** @var string $organizationName */
/** @var string $adminUrl */
/** @var callable $e */

$humanize = static fn (?string $value): string => $value === null || $value === ''
    ? 'Not set'
    : ucwords(str_replace(['.', '_', '-'], ' ', $value));
$entityLabels = [
    'organization' => 'Organization', 'contact' => 'Contact', 'company' => 'Company',
    'lead' => 'Lead', 'deal' => 'Deal', 'task' => 'Task', 'project' => 'Project',
    'quotation' => 'Quotation', 'sales_order' => 'Sales order', 'invoice' => 'Invoice',
    'payment' => 'Payment', 'catalog_item' => 'Catalog item', 'catalog_price_list' => 'Price list',
    'backup' => 'Backup', 'settings_profile' => 'Settings profile',
];
$entityRoutes = [
    'organization' => 'organization/', 'contact' => 'contact/', 'company' => 'company/',
    'lead' => 'crm-lead/', 'deal' => 'crm-deal/', 'task' => 'task/', 'project' => 'project/',
    'quotation' => 'sales-quotation/', 'sales_order' => 'sales-order/', 'invoice' => 'invoice/',
    'payment' => 'payment/', 'catalog_item' => 'catalog-item/',
    'catalog_price_list' => 'catalog-price-list/',
];
$eventIcon = static fn (string $entityType): string => match ($entityType) {
    'organization' => 'briefcase', 'contact' => 'user', 'company' => 'building',
    'lead' => 'bullseye', 'deal' => 'handshake-o', 'task' => 'check-square-o',
    'project' => 'flag', 'backup' => 'database', 'invoice' => 'file-text-o',
    'payment' => 'credit-card', default => 'history',
};
$actionClass = static fn (string $action): string => match ($action) {
    'created', 'restored', 'activated', 'completed', 'won' => ' uk-label-success',
    'deleted', 'archived', 'deactivated', 'lost', 'failed' => ' uk-label-warning',
    default => '',
};
$hasFilters = $query !== '' || $selectedComponent !== null || $selectedEntityType !== null || $selectedAction !== null;
$pageQuery = array_filter([
    'q' => $query, 'component' => $selectedComponent,
    'entity_type' => $selectedEntityType, 'action' => $selectedAction,
], static fn (mixed $value): bool => $value !== null && $value !== '');
$pageUrl = static fn (int $targetPage): string => './?' . http_build_query([...$pageQuery, 'page' => $targetPage]);
$filterUrl = static fn (string $facet, string $value): string => './?' . http_build_query([...$pageQuery, $facet => $value]);
$removeFilterUrl = static function (string $facet) use ($pageQuery): string {
    $parameters = $pageQuery;
    unset($parameters[$facet]);
    return $parameters === [] ? './' : './?' . http_build_query($parameters);
};
$metadataText = static fn (array $metadata): string => (string) json_encode(
    $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
);
$eventTitle = static function (\Kontor\Core\Domain\AuditEvent $event) use ($entityLabels, $humanize): string {
    $entity = $entityLabels[$event->entityType] ?? $humanize($event->entityType);
    return $entity . ' ' . strtolower($humanize($event->action));
};
$entityName = static fn (\Kontor\Core\Domain\AuditEvent $event): string => $event->entityType === 'organization'
    ? $organizationName
    : ($entityLabels[$event->entityType] ?? $humanize($event->entityType));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell kontor-activity-workspace">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Audit trail</p><h2>Activity</h2><p>Review who changed business records, what changed and when it happened.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>activity-export/?<?= $e(http_build_query($pageQuery)) ?>" download><i class="fa fa-download"></i> Export current view</a></div>
  </header>

  <section class="kontor-section-intro uk-margin-bottom" aria-label="About this section">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <div><strong>A readable history of important work</strong><p>Use filters to investigate a record type or action. Open an event to compare business values. Technical identifiers remain available only when you need them.</p></div>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-bottom">
    <form class="uk-form-stacked" method="get" action="./">
      <div class="uk-grid-small uk-flex-bottom" uk-grid>
        <div class="uk-width-1-1 uk-width-expand@m"><label class="uk-form-label" for="activity-search">Search activity</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon" uk-icon="icon: search"></span><input class="uk-input" id="activity-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Search record, action or identifier"></div><div class="uk-text-meta uk-margin-small-top">Search within the selected filters.</div></div>
        <div class="uk-width-1-2@s uk-width-1-5@l"><label class="uk-form-label" for="activity-component">Area</label><select class="uk-select uk-margin-small-top" id="activity-component" name="component"><option value="">All areas</option><?php foreach ($filterOptions['components'] as $component): ?><option value="<?= $e($component) ?>"<?= $selectedComponent === $component ? ' selected' : '' ?>><?= $e($humanize($component)) ?></option><?php endforeach; ?></select></div>
        <div class="uk-width-1-2@s uk-width-1-5@l"><label class="uk-form-label" for="activity-entity">Record type</label><select class="uk-select uk-margin-small-top" id="activity-entity" name="entity_type"><option value="">All record types</option><?php foreach ($filterOptions['entityTypes'] as $entityType): ?><option value="<?= $e($entityType) ?>"<?= $selectedEntityType === $entityType ? ' selected' : '' ?>><?= $e($entityLabels[$entityType] ?? $humanize($entityType)) ?></option><?php endforeach; ?></select></div>
        <div class="uk-width-1-2@s uk-width-1-5@l"><label class="uk-form-label" for="activity-action">Change</label><select class="uk-select uk-margin-small-top" id="activity-action" name="action"><option value="">All changes</option><?php foreach ($filterOptions['actions'] as $action): ?><option value="<?= $e($action) ?>"<?= $selectedAction === $action ? ' selected' : '' ?>><?= $e($humanize($action)) ?></option><?php endforeach; ?></select></div>
        <div class="uk-width-auto@s"><button class="uk-button uk-button-primary" type="submit">Apply filters</button></div>
        <?php if ($hasFilters): ?><div class="uk-width-auto@s"><a class="uk-button uk-button-default uk-link-reset" href="./">Reset</a></div><?php endif; ?>
      </div>
    </form>
    <?php if ($hasFilters): ?>
      <div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-top" uk-grid>
        <div><span class="uk-text-meta">Active filters</span></div>
        <?php foreach ([
          'q' => $query !== '' ? 'Search: ' . $query : null,
          'component' => $selectedComponent !== null ? 'Area: ' . $humanize($selectedComponent) : null,
          'entity_type' => $selectedEntityType !== null ? 'Record: ' . ($entityLabels[$selectedEntityType] ?? $humanize($selectedEntityType)) : null,
          'action' => $selectedAction !== null ? 'Change: ' . $humanize($selectedAction) : null,
        ] as $facet => $label): ?><?php if ($label !== null): ?><div><a class="uk-label uk-link-reset" href="<?= $e($removeFilterUrl($facet)) ?>"><?= $e($label) ?> <i class="fa fa-times" aria-hidden="true"></i></a></div><?php endif; ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><h3 class="uk-h4 uk-margin-remove"><?= $hasFilters ? 'Filtered activity' : 'Recent activity' ?></h3><p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom"><?= $e((string) $totalEvents) ?> event<?= $totalEvents === 1 ? '' : 's' ?><?= $totalPages > 1 ? ' · Page ' . $e((string) $page) . ' of ' . $e((string) $totalPages) : '' ?></p></div><?php if ($hasFilters): ?><div><span class="uk-label"><?= $e((string) count($events)) ?> shown</span></div><?php endif; ?></div>

  <?php if ($events): ?>
    <div class="uk-grid-small" uk-grid>
      <?php foreach ($events as $event): ?>
        <?php
        $changes = $changePresenter->changes($event);
        $metadata = $metadataText($event->metadata);
        $actor = $event->actorType === 'system'
            ? 'System'
            : ($event->actorUid !== null ? ($actorLabels[$event->actorUid] ?? $humanize($event->actorType)) : $humanize($event->actorType));
        $route = $entityRoutes[$event->entityType] ?? null;
        $canOpen = $route !== null && ($event->entityType === 'organization' || strlen($event->entityUid) === 26);
        ?>
        <div class="uk-width-1-1"><article class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-audit-event">
          <div class="uk-grid-small uk-flex-top" uk-grid>
            <div class="uk-width-auto"><span class="kontor-activity__icon"><i class="fa fa-<?= $e($eventIcon($event->entityType)) ?>"></i></span></div>
            <div class="uk-width-expand">
              <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
                <div><div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><h3 class="uk-h4 uk-margin-remove"><?= $e($eventTitle($event)) ?></h3></div><div><span class="uk-label<?= $actionClass($event->action) ?>"><?= $e($humanize($event->action)) ?></span></div></div><p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom"><?php if ($canOpen): ?><a class="uk-link-reset" href="<?= $e($adminUrl . $route . ($event->entityType === 'organization' ? '' : '?id=' . rawurlencode($event->entityUid))) ?>"><strong><?= $e($entityName($event)) ?></strong></a><?php else: ?><strong><?= $e($entityName($event)) ?></strong><?php endif; ?> · by <?= $e($actor) ?></p></div>
                <div class="uk-text-right@m"><time class="uk-text-meta" datetime="<?= $e($event->occurredAt->format(DATE_ATOM)) ?>"><?= $e($event->occurredAt->format('M j, Y · H:i')) ?></time><div class="uk-text-meta uk-margin-small-top"><?= $e(count($changes) === 0 ? 'No field comparison' : count($changes) . ' field ' . (count($changes) === 1 ? 'changed' : 'changes')) ?></div></div>
              </div>

              <?php if ($changes !== []): ?>
                <details class="uk-margin-top"<?= $hasFilters && $totalEvents <= 10 ? ' open' : '' ?>><summary class="uk-button uk-button-default uk-button-small">Review changes</summary><div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@l uk-margin-small-top" uk-grid>
                  <?php foreach ($changes as $change): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><strong><?= $e($change['field']) ?></strong><div class="uk-grid-small uk-child-width-expand uk-flex-middle uk-margin-small-top" uk-grid><div><span class="uk-text-meta uk-display-block">Before</span><span class="kontor-audit-value kontor-audit-value--before"><?= $e($change['previous']) ?></span></div><div class="uk-width-auto"><i class="fa fa-long-arrow-right uk-text-muted" aria-hidden="true"></i></div><div><span class="uk-text-meta uk-display-block">After</span><span class="kontor-audit-value kontor-audit-value--after"><?= $e($change['current']) ?></span></div></div></div></div><?php endforeach; ?>
                </div></details>
              <?php endif; ?>

              <details class="uk-margin-small-top kontor-audit-technical"><summary class="uk-text-meta">Technical details</summary><dl class="uk-description-list uk-description-list-divider uk-margin-small-top"><dt>Area</dt><dd><a class="uk-link-reset" href="<?= $e($filterUrl('component', $event->component)) ?>"><?= $e($humanize($event->component)) ?></a></dd><dt>Record identifier</dt><dd><code><?= $e($event->entityUid) ?></code></dd><?php if ($metadata !== '' && $metadata !== '{}'): ?><dt>Metadata</dt><dd><pre><?= $e($metadata) ?></pre></dd><?php endif; ?></dl></details>
            </div>
          </div>
        </article></div>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?><nav class="kontor-pagination" aria-label="Activity pages"><span><?= $e($totalEvents) ?> events · Page <?= $e($page) ?> of <?= $e($totalPages) ?></span><div><?php if ($page > 1): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page - 1)) ?>"><i class="fa fa-chevron-left"></i> Previous</a><?php endif; ?><?php if ($page < $totalPages): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page + 1)) ?>">Next <i class="fa fa-chevron-right"></i></a><?php endif; ?></div></nav><?php endif; ?>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-placeholder uk-text-center"><i class="fa fa-history fa-2x uk-text-muted"></i><h3><?= $hasFilters ? 'No activity matches these filters' : 'No activity yet' ?></h3><p class="uk-text-muted"><?= $hasFilters ? 'Remove one or more filters to broaden the history.' : 'Important changes made in Kontor will appear here.' ?></p><?php if ($hasFilters): ?><a class="uk-button uk-button-default uk-link-reset" href="./">Reset filters</a><?php endif; ?></section>
  <?php endif; ?>
</div>
