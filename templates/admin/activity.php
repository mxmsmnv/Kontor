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
/** @var string $adminUrl */
/** @var callable $e */

$actionLabel = static fn (string $action): string => ucwords(str_replace(['.', '_'], ' ', $action));
$eventMetadata = static function (\Kontor\Core\Domain\AuditEvent $event): string {
    return (string) json_encode(
        $event->metadata,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
};
$hasFilters = $query !== ''
    || $selectedComponent !== null
    || $selectedEntityType !== null
    || $selectedAction !== null;
$pageQuery = array_filter([
    'q' => $query,
    'component' => $selectedComponent,
    'entity_type' => $selectedEntityType,
    'action' => $selectedAction,
], static fn (mixed $value): bool => $value !== null && $value !== '');
$pageUrl = static function (int $targetPage) use ($pageQuery): string {
    return './?' . http_build_query([...$pageQuery, 'page' => $targetPage]);
};
$filterUrl = static function (string $facet, string $value) use ($pageQuery): string {
    return './?' . http_build_query([...$pageQuery, $facet => $value]);
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Audit trail</p>
      <h2>Activity</h2>
      <p>Recent changes and operational events across Kontor.</p>
    </div>
    <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>activity-export/?<?= $e(http_build_query($pageQuery)) ?>" download>
      <i class="fa fa-download"></i> Export CSV
    </a>
  </header>

  <form class="kontor-toolbar kontor-activityfilters" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Action, component, entity or ID">
    </label>
    <select name="component" aria-label="Component">
      <option value="">All components</option>
      <?php foreach ($filterOptions['components'] as $component): ?>
        <option value="<?= $e($component) ?>"<?= $selectedComponent === $component ? ' selected' : '' ?>><?= $e($component) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="entity_type" aria-label="Entity type">
      <option value="">All entity types</option>
      <?php foreach ($filterOptions['entityTypes'] as $entityType): ?>
        <option value="<?= $e($entityType) ?>"<?= $selectedEntityType === $entityType ? ' selected' : '' ?>><?= $e(ucfirst($entityType)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action" aria-label="Action">
      <option value="">All actions</option>
      <?php foreach ($filterOptions['actions'] as $action): ?>
        <option value="<?= $e($action) ?>"<?= $selectedAction === $action ? ' selected' : '' ?>><?= $e($actionLabel($action)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?><a class="kontor-button kontor-button--ghost" href="./">Clear</a><?php endif; ?>
    <span class="kontor-secondary kontor-filtercount"><?= $e($totalEvents) ?> matching · <?= $e(count($events)) ?> shown</span>
  </form>

  <?php if ($events): ?>
    <section class="kontor-card kontor-activity">
      <?php foreach ($events as $event): ?>
        <?php
        $canLink = in_array($event->entityType, ['contact', 'company'], true)
            && strlen($event->entityUid) === 26;
        $changes = $changePresenter->changes($event);
        $metadata = $eventMetadata($event);
        ?>
        <article class="kontor-activity__event">
          <span class="kontor-activity__icon">
            <i class="fa fa-<?= $event->entityType === 'backup' ? 'database' : ($event->entityType === 'company' ? 'building' : 'history') ?>"></i>
          </span>
          <div class="kontor-activity__body">
            <div class="kontor-activity__title">
              <strong>
                <a class="kontor-activity__facet" href="<?= $e($filterUrl('action', $event->action)) ?>">
                  <?= $e($actionLabel($event->action)) ?>
                </a>
              </strong>
              <a class="kontor-pill kontor-pill--inactive" href="<?= $e($filterUrl('component', $event->component)) ?>">
                <?= $e($event->component) ?>
              </a>
            </div>
            <p>
              <a class="kontor-activity__facet" href="<?= $e($filterUrl('entity_type', $event->entityType)) ?>">
                <?= $e(ucfirst($event->entityType)) ?>
              </a>
              <?php if ($canLink): ?>
                <a href="<?= $e($adminUrl . $event->entityType . '/?id=' . rawurlencode($event->entityUid)) ?>"><?= $e($event->entityUid) ?></a>
              <?php else: ?>
                <code><?= $e($event->entityUid) ?></code>
              <?php endif; ?>
              · <?= $e($event->actorType) ?> <?= $e($event->actorUid ?? 'system') ?>
            </p>
            <?php if ($changes !== [] || ($metadata !== '' && $metadata !== '{}')): ?>
              <details class="kontor-activity__details">
                <summary>
                  <?= $changes !== []
                    ? $e(count($changes)) . ' field ' . (count($changes) === 1 ? 'change' : 'changes')
                    : 'Event details' ?>
                </summary>
                <?php if ($changes !== []): ?>
                  <div class="kontor-changes">
                    <div class="kontor-changes__head">
                      <span>Field</span><span>Before</span><span>After</span>
                    </div>
                    <?php foreach ($changes as $change): ?>
                      <div class="kontor-changes__row">
                        <strong><?= $e($change['field']) ?></strong>
                        <span><?= $e($change['previous']) ?></span>
                        <span><?= $e($change['current']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if ($metadata !== '' && $metadata !== '{}'): ?>
                  <h4>Metadata</h4>
                  <pre><?= $e($metadata) ?></pre>
                <?php endif; ?>
              </details>
            <?php endif; ?>
          </div>
          <time datetime="<?= $e($event->occurredAt->format(DATE_ATOM)) ?>">
            <?= $e($event->occurredAt->format('M j, Y')) ?>
            <span><?= $e($event->occurredAt->format('H:i:s')) ?></span>
          </time>
        </article>
      <?php endforeach; ?>
    </section>
    <?php if ($totalPages > 1): ?>
      <nav class="kontor-pagination" aria-label="Activity pages">
        <span><?= $e($totalEvents) ?> events · Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        <div>
          <?php if ($page > 1): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page - 1)) ?>">
              <i class="fa fa-chevron-left"></i> Previous
            </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page + 1)) ?>">
              Next <i class="fa fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-history"></i>
      <h3><?= !$hasFilters ? 'No activity yet' : 'No matching events' ?></h3>
      <p><?= !$hasFilters ? 'Changes made in Kontor will appear here.' : 'Try another action, component, entity type, or ID.' ?></p>
    </div>
  <?php endif; ?>
</div>
