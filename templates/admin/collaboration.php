<?php

/** @var \Kontor\Collaboration\Domain\Note[] $notes */
/** @var \Kontor\Collaboration\Domain\Comment[] $comments */
/** @var array<string, array{label: string, route: string}> $entityLinks */
/** @var array<int, string> $authorLabels */
/** @var bool $canViewNotes */
/** @var bool $canViewTasks */
/** @var bool $canCreateTasks */
/** @var string $query */
/** @var string $selectedView */
/** @var string $adminUrl */
/** @var callable $e */

$entityContext = static function (string $type, string $uid) use ($adminUrl, $entityLinks): array {
    $key = $type . ':' . $uid;
    if (isset($entityLinks[$key])) {
        return [
            'label' => $entityLinks[$key]['label'],
            'url' => $adminUrl . $entityLinks[$key]['route'],
        ];
    }

    return [
        'label' => ucfirst(str_replace('_', ' ', $type)) . ' unavailable',
        'url' => null,
    ];
};
$authorLabel = static fn ($record): string => $record->createdBy !== null
    ? ($authorLabels[$record->createdBy] ?? 'Former team member')
    : 'Kontor';
$recordType = static fn (string $type): string => match ($type) {
    'deal' => 'Deal',
    'lead' => 'Lead',
    default => ucfirst(str_replace('_', ' ', $type)),
};
$relativeDate = static function (DateTimeImmutable $date): string {
    $today = new DateTimeImmutable('today');
    $day = $date->setTime(0, 0);
    if ($day == $today) {
        return 'Today · ' . $date->format('H:i');
    }
    if ($day == $today->modify('-1 day')) {
        return 'Yesterday · ' . $date->format('H:i');
    }

    return $date->format('M j, Y · H:i');
};
$feed = [];
foreach ($comments as $comment) {
    $feed[] = ['kind' => 'comment', 'record' => $comment];
}
if ($canViewNotes) {
    foreach ($notes as $note) {
        $feed[] = ['kind' => 'note', 'record' => $note];
    }
}
usort($feed, static fn (array $left, array $right): int => $right['record']->createdAt <=> $left['record']->createdAt);
$feed = array_values(array_filter($feed, static function (array $item) use ($selectedView, $query, $entityContext, $authorLabel): bool {
    if ($selectedView === 'comments' && $item['kind'] !== 'comment') {
        return false;
    }
    if ($selectedView === 'notes' && $item['kind'] !== 'note') {
        return false;
    }
    if ($query === '') {
        return true;
    }
    $record = $item['record'];
    $context = $entityContext($record->entityType, $record->entityUid);
    $haystack = $record->body . ' ' . $context['label'] . ' ' . $authorLabel($record);

    return mb_stripos($haystack, $query) !== false;
}));
$connectedRecords = count(array_unique(array_map(
    static fn ($record): string => $record->entityType . ':' . $record->entityUid,
    array_filter(array_merge($comments, $notes), static fn ($record): bool => isset($entityLinks[$record->entityType . ':' . $record->entityUid]))
)));
$today = new DateTimeImmutable('today');
$updatesToday = count(array_filter(array_merge($comments, $notes), static fn ($record): bool => $record->createdAt >= $today));
$viewUrl = static function (string $view) use ($query): string {
    $parameters = array_filter(['view' => $view === 'all' ? null : $view, 'q' => $query]);
    return './' . ($parameters !== [] ? '?' . http_build_query($parameters) : '');
};
$hasFilters = $query !== '' || $selectedView !== 'all';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Teamwork · Shared context</p>
      <h2>Collaboration</h2>
      <p>Follow conversations and private team notes without losing the customer, deal, project or task behind them.</p>
    </div>
    <?php if ($canViewTasks): ?><div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-check-square-o"></i> Open tasks</a>
      <?php if ($canCreateTasks): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>task/"><i class="fa fa-plus"></i> New task</a><?php endif; ?>
    </div><?php endif; ?>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-comments-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($comments)) ?></strong><span class="kontor-stat__label">Recent discussions</span></span></div></div>
    <?php if ($canViewNotes): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-sticky-note-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($notes)) ?></strong><span class="kontor-stat__label">Internal notes</span></span></div></div><?php endif; ?>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-link"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $connectedRecords) ?></strong><span class="kontor-stat__label">Connected records</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $updatesToday) ?></strong><span class="kontor-stat__label">Updates today</span></span></div></div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body">
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
      <div>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Team inbox</p>
        <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Recent collaboration</h3>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Open the connected record to reply, add a note or continue the work.</p>
      </div>
      <div><div class="uk-button-group" aria-label="Collaboration view">
        <a class="uk-button uk-button-small <?= $selectedView === 'all' ? 'uk-button-primary' : 'uk-button-default' ?>" href="<?= $e($viewUrl('all')) ?>"<?= $selectedView === 'all' ? ' aria-current="page"' : '' ?>>All</a>
        <a class="uk-button uk-button-small <?= $selectedView === 'comments' ? 'uk-button-primary' : 'uk-button-default' ?>" href="<?= $e($viewUrl('comments')) ?>"<?= $selectedView === 'comments' ? ' aria-current="page"' : '' ?>>Discussions</a>
        <?php if ($canViewNotes): ?><a class="uk-button uk-button-small <?= $selectedView === 'notes' ? 'uk-button-primary' : 'uk-button-default' ?>" href="<?= $e($viewUrl('notes')) ?>"<?= $selectedView === 'notes' ? ' aria-current="page"' : '' ?>>Internal notes</a><?php endif; ?>
      </div></div>
    </div>

    <form class="uk-form-stacked uk-margin" method="get" action="./">
      <?php if ($selectedView !== 'all'): ?><input type="hidden" name="view" value="<?= $e($selectedView) ?>"><?php endif; ?>
      <div class="uk-grid-small uk-flex-bottom" uk-grid>
        <div class="uk-width-1-1 uk-width-expand@m"><label class="uk-form-label" for="collaboration-search">Search collaboration</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon" uk-icon="icon: search"></span><input class="uk-input" id="collaboration-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="Search message, record or team member"></div><div class="uk-text-meta uk-margin-small-top">Searches the latest discussions and the internal notes you are allowed to see.</div></div>
        <div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-default uk-width-1-1" type="submit">Search</button></div>
        <?php if ($hasFilters): ?><div class="uk-width-1-1 uk-width-auto@m"><a class="uk-button uk-button-text uk-width-1-1" href="./">Reset</a></div><?php endif; ?>
      </div>
    </form>

    <?php if ($feed !== []): ?>
      <ul class="uk-list uk-list-divider uk-margin-remove-bottom">
        <?php foreach ($feed as $item): $record = $item['record']; $context = $entityContext($record->entityType, $record->entityUid); ?>
          <li>
            <article class="uk-grid-small uk-flex-top" uk-grid>
              <div class="uk-width-auto"><span class="kontor-stat__icon"><i class="fa fa-<?= $item['kind'] === 'note' ? 'sticky-note-o' : 'comment-o' ?>"></i></span></div>
              <div class="uk-width-expand">
                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
                  <div><strong><?= $e($authorLabel($record)) ?></strong> <span class="uk-label<?= $item['kind'] === 'note' ? ' uk-label-warning' : '' ?>"><?= $item['kind'] === 'note' ? 'Internal note' : 'Discussion' ?></span></div>
                  <time class="uk-text-meta" datetime="<?= $e($record->createdAt->format(DATE_ATOM)) ?>"><?= $e($relativeDate($record->createdAt)) ?></time>
                </div>
                <p class="uk-margin-small-top uk-margin-small-bottom"><?= nl2br($e($record->body)) ?></p>
                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
                  <div class="uk-text-meta"><i class="fa fa-<?= $record->entityType === 'task' ? 'check-square-o' : ($record->entityType === 'project' ? 'briefcase' : 'link') ?>"></i> <?= $e($recordType($record->entityType)) ?> · <?= $e($context['label']) ?></div>
                  <?php if ($context['url'] !== null): ?><div><a class="uk-button uk-button-text" href="<?= $e($context['url']) ?>">Open record <i class="fa fa-angle-right"></i></a></div><?php endif; ?>
                </div>
              </div>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty uk-margin-remove-bottom">
        <i class="fa fa-comments-o"></i>
        <h3><?= $e(match (true) {
            $query !== '' => 'No matching collaboration',
            $selectedView === 'notes' => 'No internal notes yet',
            $selectedView === 'comments' => 'No discussions yet',
            default => 'No collaboration yet',
        }) ?></h3>
        <p><?= $e($query !== ''
            ? 'Try another search or show all activity.'
            : ($selectedView === 'notes'
                ? 'Private team notes added to connected work will appear here.'
                : 'Discussions and internal notes will appear here when they are added to connected work.')) ?></p>
        <?php if ($hasFilters): ?><a class="uk-button uk-button-default" href="./">Show all activity</a><?php elseif ($canViewTasks): ?><a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>tasks/">Open tasks</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
