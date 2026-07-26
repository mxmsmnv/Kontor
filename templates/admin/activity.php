<?php

/** @var \Kontor\Core\Domain\AuditEvent[] $events */
/** @var string $query */
/** @var string $adminUrl */
/** @var callable $e */

$actionLabel = static fn (string $action): string => ucwords(str_replace(['.', '_'], ' ', $action));
$eventDetails = static function (\Kontor\Core\Domain\AuditEvent $event): string {
    $details = array_filter([
        'Previous' => $event->previous,
        'Current' => $event->current,
        'Metadata' => $event->metadata ?: null,
    ], static fn (mixed $value): bool => $value !== null);

    return (string) json_encode(
        $details,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Audit trail</p>
      <h2>Activity</h2>
      <p>Recent changes and operational events across Kontor.</p>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Action, component, entity or ID">
    </label>
    <button class="kontor-button" type="submit">Search</button>
    <?php if ($query !== ''): ?>
      <a class="kontor-button kontor-button--secondary" href="./">Clear</a>
    <?php endif; ?>
  </form>

  <?php if ($events): ?>
    <section class="kontor-card kontor-activity">
      <?php foreach ($events as $event): ?>
        <?php
        $canLink = in_array($event->entityType, ['contact', 'company'], true)
            && strlen($event->entityUid) === 26;
        $details = $eventDetails($event);
        ?>
        <article class="kontor-activity__event">
          <span class="kontor-activity__icon">
            <i class="fa fa-<?= $event->entityType === 'backup' ? 'database' : ($event->entityType === 'company' ? 'building' : 'history') ?>"></i>
          </span>
          <div class="kontor-activity__body">
            <div class="kontor-activity__title">
              <strong><?= $e($actionLabel($event->action)) ?></strong>
              <span class="kontor-pill kontor-pill--inactive"><?= $e($event->component) ?></span>
            </div>
            <p>
              <?= $e(ucfirst($event->entityType)) ?>
              <?php if ($canLink): ?>
                <a href="<?= $e($adminUrl . $event->entityType . '/?id=' . rawurlencode($event->entityUid)) ?>"><?= $e($event->entityUid) ?></a>
              <?php else: ?>
                <code><?= $e($event->entityUid) ?></code>
              <?php endif; ?>
              · <?= $e($event->actorType) ?> <?= $e($event->actorUid ?? 'system') ?>
            </p>
            <?php if ($details !== '' && $details !== '{}'): ?>
              <details class="kontor-activity__details">
                <summary>Event details</summary>
                <pre><?= $e($details) ?></pre>
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
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-history"></i>
      <h3><?= $query === '' ? 'No activity yet' : 'No matching events' ?></h3>
      <p><?= $query === '' ? 'Changes made in Kontor will appear here.' : 'Try another action, component, entity type, or ID.' ?></p>
    </div>
  <?php endif; ?>
</div>
