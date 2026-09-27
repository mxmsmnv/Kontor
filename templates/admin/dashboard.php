<?php

/** @var array $components */
/** @var bool $contactsReady */
/** @var int $contactCount */
/** @var int $companyCount */
/** @var array $recentContacts */
/** @var bool $canViewCatalog */
/** @var bool $canCreateCatalogItems */
/** @var array $catalogSummary */
/** @var string $catalogLanguage */
/** @var \Kontor\Catalog\Domain\CatalogItem[] $recentCatalogItems */
/** @var bool $canViewActivity */
/** @var \Kontor\Core\Domain\AuditEvent[] $recentActivity */
/** @var bool $dashboardReady */
/** @var bool $canViewPersonalDashboard */
/** @var bool $canCreatePersonalDashboard */
/** @var bool $canEditPersonalDashboard */
/** @var \Kontor\Dashboard\Domain\Dashboard|null $personalDashboard */
/** @var array|null $renderedPersonalDashboard */
/** @var array<string, \Kontor\Dashboard\Contracts\WidgetProviderInterface> $availableDashboardWidgets */
/** @var string $dashboardHeadline */
/** @var string $dashboardMessage */
/** @var bool $dashboardIntroCustomized */
/** @var array<string, array<int, array{key: string, url: string, label: string, icon: string}>> $navigationGroups */
/** @var string[] $quickNavigationKeys */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$dashboardWidgets = $renderedPersonalDashboard['widgets'] ?? [];
$taskWidget = null;
foreach ($dashboardWidgets as $candidate) {
    if ($candidate['layout']->widgetKey === 'tasks.my_open') {
        $taskWidget = $candidate;
        break;
    }
}
$openTaskCount = (int) ($taskWidget['data']['count'] ?? 0);
$overdueTaskCount = (int) ($taskWidget['data']['overdueCount'] ?? 0);
$catalogItemCount = (int) ($catalogSummary['products'] ?? 0) + (int) ($catalogSummary['services'] ?? 0);
$catalogIssues = array_filter([
    'Missing sales price' => (int) ($catalogSummary['unpriced'] ?? 0),
    'Uncategorized' => (int) ($catalogSummary['uncategorized'] ?? 0),
    'Expired price lists' => (int) ($catalogSummary['expiredPriceLists'] ?? 0),
], static fn (int $count): bool => $count > 0);
$quickItems = [];
foreach ($navigationGroups as $items) {
    foreach ($items as $item) {
        if (in_array($item['key'], $quickNavigationKeys, true)) $quickItems[] = $item;
    }
}
$humanize = static fn (string $value): string => ucwords(str_replace(['.', '_', '-'], ' ', $value));
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@m">
        <div class="uk-flex uk-flex-between uk-flex-middle"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Operations workspace</p><button class="uk-button uk-button-default uk-button-small" type="button" uk-toggle="target: #kontor-dashboard-intro"><i class="fa fa-cog"></i> Personalize</button></div>
        <h2 class="uk-h1 uk-margin-small-top uk-margin-small-bottom"><?= $e($dashboardHeadline) ?></h2>
        <?php if ($dashboardMessage !== ''): ?><p class="uk-text-muted uk-margin-remove"><?= $e($dashboardMessage) ?></p><?php endif; ?>
      </div>
      <?php if ($contactsReady || $canCreateCatalogItems): ?><div class="uk-width-1-1 uk-width-auto@m"><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><?php if ($contactsReady): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>contact/"><i class="fa fa-user-plus"></i> New contact</a></div><div><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>company/"><i class="fa fa-building"></i> New company</a></div><?php endif; ?><?php if ($canCreateCatalogItems): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>catalog-item/"><i class="fa fa-cube"></i> New catalog item</a></div><?php endif; ?></div></div><?php endif; ?>
    </div>
  </section>

  <div id="kontor-dashboard-intro" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
      <button class="uk-modal-close-default" type="button" uk-close aria-label="Close"></button>
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Personal setting</p><h2 class="uk-modal-title uk-margin-small-top">Dashboard intro</h2><p class="uk-text-muted">Set a personal headline or motivational note. Only your Dashboard changes.</p>
      <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>dashboard-intro-save/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><div class="uk-margin"><label class="uk-form-label" for="dashboard-headline">Headline <span class="uk-text-danger">*</span></label><input class="uk-input uk-margin-small-top" id="dashboard-headline" name="dashboard_headline" maxlength="90" value="<?= $e($dashboardHeadline) ?>" required><div class="uk-text-meta uk-margin-small-top">Required. A short phrase you want to see when work starts.</div></div><div class="uk-margin"><label class="uk-form-label" for="dashboard-message">Supporting text</label><textarea class="uk-textarea uk-margin-small-top" id="dashboard-message" name="dashboard_message" maxlength="180" rows="3"><?= $e($dashboardMessage) ?></textarea><div class="uk-text-meta uk-margin-small-top">Optional context, reminder or team focus for the day.</div></div><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><?php if ($dashboardIntroCustomized): ?><button class="uk-button uk-button-default" name="action" value="reset" type="submit">Restore default</button><?php endif; ?></div><div class="uk-flex uk-grid-small" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" name="action" value="save" type="submit">Save intro</button></div></div></div></form>
    </div>
  </div>

  <section class="uk-margin-medium-bottom">
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Today</p><h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom">What needs your attention</h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">A compact view of current work and customer data.</p></div></div>
    <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@l uk-grid-match" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-check-square-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $openTaskCount) ?></strong><span class="kontor-stat__label">Open tasks</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $overdueTaskCount > 0 ? ' kontor-stat__icon--danger' : '' ?>"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $overdueTaskCount) ?></strong><span class="kontor-stat__label">Overdue tasks</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-address-book"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $contactCount) ?></strong><span class="kontor-stat__label">Active contacts</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-building"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $companyCount) ?></strong><span class="kontor-stat__label">Companies</span></span></div></div>
    </div>
  </section>

  <section class="uk-margin-medium-bottom">
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Quick access</p><h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom">Your pinned workspaces</h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Keep only the sections you use most often within one click.</p></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>sections/"><i class="fa fa-sliders"></i> Customize</a></div></div>
    <?php if ($quickItems !== []): ?><div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-4@l" uk-grid><?php foreach ($quickItems as $item): ?><div><a class="uk-card uk-card-default uk-card-small uk-card-body uk-link-reset uk-display-block uk-height-1-1" href="<?= $e($adminUrl . $item['url']) ?>"><div class="uk-flex uk-flex-middle"><span class="kontor-stat__icon uk-margin-small-right"><i class="fa fa-<?= $e($item['icon']) ?>"></i></span><strong><?= $e($item['label']) ?></strong></div></a></div><?php endforeach; ?></div><?php else: ?><div class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No pinned sections yet.</p><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>sections/">Choose quick access</a></div></div><?php endif; ?>
  </section>

  <?php if ($dashboardReady && $canViewPersonalDashboard): ?>
    <section class="uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Personal workspace</p><h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom"><?= $e($personalDashboard?->name ?? 'My dashboard') ?></h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Live widgets for the work assigned to you.</p></div><?php if ($personalDashboard !== null && $canEditPersonalDashboard && $availableDashboardWidgets !== []): ?><div><button class="uk-button uk-button-default uk-button-small" type="button" uk-toggle="target: #kontor-add-widget"><i class="fa fa-plus"></i> Add widget</button></div><?php endif; ?></div>
      <?php if ($personalDashboard === null): ?>
        <?php if ($canCreatePersonalDashboard): ?><form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>dashboard-save/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-expand@m"><label class="uk-form-label" for="dashboard-name">Dashboard name <span class="uk-text-danger">*</span></label><input class="uk-input uk-margin-small-top" id="dashboard-name" name="name" value="My dashboard" required><div class="uk-text-meta uk-margin-small-top">A private workspace visible only to you.</div></div><div class="uk-width-auto@m"><button class="uk-button uk-button-primary" type="submit">Create dashboard</button></div></div></form><?php else: ?><div class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No personal dashboard is configured for you yet.</p></div></div><?php endif; ?>
      <?php else: ?>
        <?php if ($canEditPersonalDashboard && $availableDashboardWidgets !== []): ?><div id="kontor-add-widget" class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-bottom" hidden><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-expand@m"><label class="uk-form-label" for="dashboard-widget">Widget</label><select class="uk-select uk-margin-small-top" id="dashboard-widget" name="widget_key"><?php foreach ($availableDashboardWidgets as $key => $provider): ?><option value="<?= $e($key) ?>"><?= $e($provider->title()) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Choose a live view to add to your personal workspace.</div></div><div class="uk-width-auto@m"><button class="uk-button uk-button-primary" name="action" value="add" type="submit"><i class="fa fa-plus"></i> Add widget</button></div></div></form></div><?php endif; ?>
        <?php if ($dashboardWidgets !== []): ?><div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@l uk-grid-match" uk-grid><?php foreach ($dashboardWidgets as $renderedWidget): ?><?php $layout = $renderedWidget['layout']; ?><article class="uk-card uk-card-default uk-card-small uk-card-body uk-flex uk-flex-column"><header class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><h3 class="uk-card-title uk-margin-remove"><?= $e($renderedWidget['title']) ?></h3><?php if ($canEditPersonalDashboard): ?><div class="uk-inline"><button class="uk-button uk-button-default uk-button-small" type="button" aria-label="Widget settings"><i class="fa fa-ellipsis-h"></i></button><div uk-dropdown="mode: click; pos: bottom-right"><form method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>"><input type="hidden" name="widget_uid" value="<?= $e($layout->uid->toString()) ?>"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-top">Widget order</p><div class="uk-grid-small uk-child-width-1-2" uk-grid><div><button class="uk-button uk-button-default uk-button-small uk-width-1-1" name="action" value="move_left" type="submit"><i class="fa fa-arrow-left"></i> Earlier</button></div><div><button class="uk-button uk-button-default uk-button-small uk-width-1-1" name="action" value="move_right" type="submit">Later <i class="fa fa-arrow-right"></i></button></div></div><button class="uk-button uk-button-danger uk-button-small uk-width-1-1 uk-margin-top" name="action" value="remove" type="submit"><i class="fa fa-trash"></i> Remove widget</button></form></div></div><?php endif; ?></header>
          <?php if ($layout->widgetKey === 'welcome'): ?><div class="uk-flex uk-flex-top uk-margin-medium-top"><span class="kontor-stat__icon uk-margin-small-right"><i class="fa fa-hand-spock-o"></i></span><span><strong>Welcome to your Kontor workspace.</strong><span class="uk-text-muted uk-display-block uk-margin-small-top">Your personal layout is ready for the live views you need.</span></span></div>
          <?php elseif ($layout->widgetKey === 'tasks.my_open'): ?><div class="uk-grid-small uk-child-width-1-2 uk-margin-medium-top" uk-grid><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) ($renderedWidget['data']['count'] ?? 0)) ?></strong><span class="kontor-stat__label">Open</span></span></div></div><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) ($renderedWidget['data']['overdueCount'] ?? 0)) ?></strong><span class="kontor-stat__label">Overdue</span></span></div></div></div><?php if (($renderedWidget['data']['tasks'] ?? []) !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($renderedWidget['data']['tasks'] as $task): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e((string) $task['title']) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= !empty($task['dueAt']) ? 'Due ' . $e(substr((string) $task['dueAt'], 0, 10)) : 'No due date' ?></div></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>task/?id=<?= $e(rawurlencode((string) $task['uid'])) ?>">Open</a></div></div></li><?php endforeach; ?></ul><?php else: ?><p class="uk-text-muted uk-margin-medium-top">No open tasks assigned to you.</p><?php endif; ?><div class="uk-margin-auto-top uk-padding-small uk-padding-remove-horizontal uk-padding-remove-bottom"><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>tasks/">Open task workspace</a></div>
          <?php else: ?><div class="uk-alert-primary uk-margin-medium-top" uk-alert><p>This widget is available, but does not yet have a dashboard presentation.</p></div><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No widgets yet.</p></div></div><?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canViewCatalog): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Commercial catalog</p><h2 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Catalog health</h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Products, services, pricing and classification readiness.</p></div><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>catalog/">Open catalog <i class="fa fa-angle-right"></i></a></div></div>
      <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@m uk-margin-medium-top" uk-grid><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) $catalogItemCount) ?></strong><span class="kontor-stat__label">Catalog items</span></span></div></div><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) ($catalogSummary['services'] ?? 0)) ?></strong><span class="kontor-stat__label">Services</span></span></div></div><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) ($catalogSummary['inventoryTracked'] ?? 0)) ?></strong><span class="kontor-stat__label">Inventory tracked</span></span></div></div><div><div class="kontor-stat"><span><strong class="kontor-stat__value"><?= $e((string) ($catalogSummary['priceLists'] ?? 0)) ?></strong><span class="kontor-stat__label">Price lists</span></span></div></div></div>
      <?php if ($catalogIssues !== []): ?><div class="uk-alert-warning uk-margin-medium-top" uk-alert><p><strong><i class="fa fa-exclamation-triangle"></i> Needs attention.</strong> <?php foreach ($catalogIssues as $label => $count): ?><span class="uk-margin-small-right"><?= $e((string) $count) ?> <?= $e(mb_strtolower($label)) ?></span><?php endforeach; ?></p></div><?php else: ?><div class="uk-alert-success uk-margin-medium-top" uk-alert><p><i class="fa fa-check"></i> Catalog data is ready for commercial use.</p></div><?php endif; ?>
      <?php if ($recentCatalogItems !== []): ?><div class="uk-margin-medium-top"><div class="uk-flex uk-flex-between uk-flex-middle"><strong>Recently updated</strong><span class="uk-text-meta"><?= $e((string) count($recentCatalogItems)) ?> item<?= count($recentCatalogItems) === 1 ? '' : 's' ?></span></div><ul class="uk-list uk-list-divider uk-margin-small-top uk-margin-remove-bottom"><?php foreach ($recentCatalogItems as $catalogItem): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($catalogItem->titleIn($catalogLanguage) ?? $catalogItem->titleIn('en') ?? reset($catalogItem->title) ?: 'Untitled item') ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e(ucfirst($catalogItem->itemType)) ?> · <?= $e(ucfirst($catalogItem->status)) ?><?= $catalogItem->salesPrice === null ? ' · No sales price' : '' ?></div></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>catalog-item/?id=<?= $e(rawurlencode($catalogItem->uid->toString())) ?>">Open item</a></div></div></li><?php endforeach; ?></ul></div><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!$contactsReady): ?><div class="uk-alert-warning uk-margin-medium-bottom" uk-alert><p><strong>Contacts is not installed.</strong> Install it to activate the customer workspace.</p></div><?php endif; ?>

  <section class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@l" uk-grid>
    <div><article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customers</p><h2 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Recently updated contacts</h2></div><?php if ($contactsReady): ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>contacts/">View contacts</a></div><?php endif; ?></div><?php if ($recentContacts !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($recentContacts as $contact): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div class="uk-flex uk-flex-middle uk-width-expand"><span class="kontor-avatar uk-margin-small-right"><?= $e(mb_substr($contact->displayName, 0, 2)) ?></span><span><strong><?= $e($contact->displayName) ?></strong><span class="uk-text-meta uk-display-block uk-margin-small-top"><?= $e($contact->email ?: $contact->jobTitle ?: 'No contact details yet') ?></span></span></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($contact->uid->toString())) ?>">Open</a></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><p class="uk-text-muted">No contacts yet.</p></div><?php endif; ?></article></div>
    <?php if ($canViewActivity): ?><div><article class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Activity</p><h2 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Recent changes</h2></div><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>activity/">Audit trail</a></div></div><?php if ($recentActivity !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach (array_slice($recentActivity, 0, 4) as $event): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div class="uk-flex uk-flex-middle uk-width-expand"><span class="kontor-stat__icon uk-margin-small-right"><i class="fa fa-history"></i></span><span><strong><?= $e($humanize($event->action)) ?></strong><span class="uk-text-meta uk-display-block uk-margin-small-top"><?= $e($humanize($event->entityType)) ?></span></span></div><time class="uk-text-meta" datetime="<?= $e($event->occurredAt->format(DATE_ATOM)) ?>"><?= $e($event->occurredAt->format('M j · H:i')) ?></time></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><p class="uk-text-muted">Operational changes will appear here.</p></div><?php endif; ?></article></div><?php endif; ?>
  </section>
</div>
