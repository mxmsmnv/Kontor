<?php

/** @var array $components */
/** @var bool $contactsReady */
/** @var int $contactCount */
/** @var int $companyCount */
/** @var array $recentContacts */
/** @var bool $catalogReady */
/** @var bool $canViewCatalog */
/** @var bool $canCreateCatalogItems */
/** @var array{products: int, services: int, archived: int, inventoryTracked: int, unpriced: int, uncategorized: int, priceLists?: int, expiredPriceLists?: int, upcomingPriceLists?: int} $catalogSummary */
/** @var string $catalogLanguage */
/** @var \Kontor\Catalog\Domain\CatalogItem[] $recentCatalogItems */
/** @var bool $canViewActivity */
/** @var bool $canViewBackups */
/** @var bool $canViewHealth */
/** @var bool $canManageOrganization */
/** @var bool $queueReady */
/** @var bool $canViewQueue */
/** @var \Kontor\Core\Domain\AuditEvent[] $recentActivity */
/** @var array<string, int> $queueCounts */
/** @var bool $dashboardReady */
/** @var bool $canViewPersonalDashboard */
/** @var bool $canCreatePersonalDashboard */
/** @var bool $canEditPersonalDashboard */
/** @var \Kontor\Dashboard\Domain\Dashboard|null $personalDashboard */
/** @var array{dashboard: \Kontor\Dashboard\Domain\Dashboard, widgets: array<int, array{layout: \Kontor\Dashboard\Domain\DashboardWidget, title: string, data: array<string, mixed>, cacheHit: bool}>}|null $renderedPersonalDashboard */
/** @var array<string, \Kontor\Dashboard\Contracts\WidgetProviderInterface> $availableDashboardWidgets */
/** @var array<string, array<int, array{key: string, url: string, label: string, icon: string}>> $navigationGroups */
/** @var string[] $quickNavigationKeys */
/** @var string $adminUrl */
/** @var callable $e */

$enabledComponents = count(array_filter(
    $components,
    static fn (array $component): bool => ($component['status'] ?? '') === 'enabled'
));
$activeJobs = ($queueCounts['pending'] ?? 0) + ($queueCounts['reserved'] ?? 0);
$dashboardWidgets = $renderedPersonalDashboard['widgets'] ?? [];
$catalogIssues = array_filter([
    'Missing sales price' => (int) $catalogSummary['unpriced'],
    'Uncategorized' => (int) $catalogSummary['uncategorized'],
    'Expired price lists' => (int) ($catalogSummary['expiredPriceLists'] ?? 0),
], static fn (int $count): bool => $count > 0);
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-flex-middle" uk-grid>
      <div class="uk-width-1-1 uk-width-expand@m">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Operations workspace</p>
        <h2 class="uk-h1 uk-margin-small-top uk-margin-small-bottom">Your business, in one place.</h2>
        <p class="uk-text-muted uk-margin-remove">Kontor connects customer data, companies and operational components inside ProcessWire.</p>
      </div>
      <?php if ($contactsReady || $canCreateCatalogItems): ?>
        <div class="uk-width-1-1 uk-width-auto@m">
          <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
            <?php if ($contactsReady): ?>
              <div><a class="uk-button uk-button-default kontor-button" href="<?= $e($adminUrl) ?>contact/"><i class="fa fa-plus"></i> New contact</a></div>
              <div><a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>company/"><i class="fa fa-building"></i> New company</a></div>
            <?php endif; ?>
            <?php if ($canCreateCatalogItems): ?>
              <div><a class="uk-button uk-button-default kontor-button" href="<?= $e($adminUrl) ?>catalog-item/"><i class="fa fa-cube"></i> New catalog item</a></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($dashboardReady && $canViewPersonalDashboard): ?>
    <section class="uk-margin-medium-bottom">
      <header class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
        <div>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Personal layout</p>
          <h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom"><?= $e($personalDashboard?->name ?? 'Your dashboard') ?></h2>
        </div>
      </header>

      <?php if ($personalDashboard === null): ?>
        <?php if ($canCreatePersonalDashboard): ?>
          <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>dashboard-save/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <div class="uk-grid-small uk-flex-bottom" uk-grid>
              <label class="uk-width-expand">Dashboard name <input class="uk-input uk-margin-small-top" name="name" value="My dashboard" required></label>
              <div class="uk-width-auto"><button class="uk-button uk-button-primary" type="submit">Create personal dashboard</button></div>
            </div>
          </form>
        <?php else: ?>
          <div class="uk-placeholder uk-text-center"><p class="uk-text-muted uk-margin-remove">No personal or role dashboard is configured for you yet.</p></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="kontor-dashboardwidgets">
          <?php foreach ($dashboardWidgets as $renderedWidget): ?>
            <?php
              $layout = $renderedWidget['layout'];
              $widgetSpan = max(2, min(12, $layout->width));
              $widgetColumn = max(1, min(13 - $widgetSpan, $layout->positionX + 1));
            ?>
            <article
              class="uk-card uk-card-default uk-card-small uk-card-body uk-flex uk-flex-column kontor-dashboardwidget"
              style="--kontor-widget-column: <?= $e($widgetColumn) ?>; --kontor-widget-span: <?= $e($widgetSpan) ?>;"
            >
              <header class="kontor-panel__head">
                <h3><?= $e($renderedWidget['title']) ?></h3>
                <span class="kontor-dashboardwidget__status" title="<?= $renderedWidget['cacheHit'] ? 'Served from widget cache' : 'Freshly generated' ?>">
                  <i class="fa fa-<?= $renderedWidget['cacheHit'] ? 'bolt' : 'clock-o' ?>"></i>
                  <?= $renderedWidget['cacheHit'] ? 'Cached' : 'Fresh' ?>
                </span>
              </header>

              <?php if ($layout->widgetKey === 'welcome'): ?>
                <div class="kontor-dashboardwidget__welcome">
                  <span class="kontor-dashboardwidget__heroicon"><i class="fa fa-hand-spock-o"></i></span>
                  <div>
                    <strong>Welcome to your Kontor workspace.</strong>
                    <p>Your personal layout is active and ready for more widgets.</p>
                  </div>
                </div>
              <?php elseif ($layout->widgetKey === 'tasks.my_open'): ?>
                <div class="kontor-dashboardwidget__metrics">
                  <span><strong><?= $e((string) ($renderedWidget['data']['count'] ?? 0)) ?></strong> open</span>
                  <span><strong><?= $e((string) ($renderedWidget['data']['overdueCount'] ?? 0)) ?></strong> overdue</span>
                </div>
                <?php if (($renderedWidget['data']['tasks'] ?? []) !== []): ?>
                  <ul class="kontor-list">
                    <?php foreach ($renderedWidget['data']['tasks'] as $task): ?>
                      <li>
                        <span class="kontor-list__body">
                          <a href="<?= $e($adminUrl) ?>task/?id=<?= $e(rawurlencode((string) $task['uid'])) ?>"><?= $e((string) $task['title']) ?></a>
                        </span>
                        <span class="uk-label<?= !empty($task['overdue']) ? ' uk-label-danger' : '' ?>"><?= $e((string) $task['priority']) ?><?= !empty($task['dueAt']) ? ' · ' . $e(substr((string) $task['dueAt'], 0, 10)) : '' ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p class="uk-text-muted uk-margin-small-top">No open tasks assigned to you.</p>
                <?php endif; ?>
                <div class="uk-margin-top"><a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>tasks/">Open tasks</a></div>
              <?php else: ?>
                <pre><?= $e(json_encode($renderedWidget['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
              <?php endif; ?>

              <?php if ($canEditPersonalDashboard): ?>
                <form class="kontor-dashboardwidget__controls" method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>">
                  <input type="hidden" name="widget_uid" value="<?= $e($layout->uid->toString()) ?>">
                  <span>Layout</span>
                  <button class="uk-button uk-button-default uk-button-small" name="action" value="move_left" type="submit" title="Move left" aria-label="Move widget left"><i class="fa fa-arrow-left"></i></button>
                  <button class="uk-button uk-button-default uk-button-small" name="action" value="move_right" type="submit" title="Move right" aria-label="Move widget right"><i class="fa fa-arrow-right"></i></button>
                  <button class="uk-button uk-button-default uk-button-small" name="action" value="narrower" type="submit" title="Make narrower" aria-label="Make widget narrower"><i class="fa fa-compress"></i></button>
                  <button class="uk-button uk-button-default uk-button-small" name="action" value="wider" type="submit" title="Make wider" aria-label="Make widget wider"><i class="fa fa-expand"></i></button>
                  <button class="uk-button uk-button-default uk-button-small kontor-dashboardwidget__remove" name="action" value="remove" type="submit" title="Remove widget" aria-label="Remove widget"><i class="fa fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>

          <?php if ($canEditPersonalDashboard && $availableDashboardWidgets !== []): ?>
            <article
              class="uk-card uk-card-default uk-card-small uk-card-body uk-flex uk-flex-column kontor-dashboardwidget"
              style="--kontor-widget-column: auto; --kontor-widget-span: 4;"
            >
              <header class="kontor-panel__head"><h3>Add widget</h3></header>
              <p class="uk-text-muted uk-margin-small-top">Extend your personal workspace with another live view.</p>
              <form class="uk-form-stacked uk-margin-auto-top" method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/">
                <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                <input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>">
                <label>
                  <span class="uk-form-label">Widget</span>
                  <select class="uk-select uk-margin-small-top" name="widget_key">
                    <?php foreach ($availableDashboardWidgets as $key => $provider): ?><option value="<?= $e($key) ?>"><?= $e($provider->title()) ?></option><?php endforeach; ?>
                  </select>
                </label>
                <button class="uk-button uk-button-primary uk-width-1-1 uk-margin-top" name="action" value="add" type="submit"><i class="fa fa-plus"></i> Add widget</button>
              </form>
            </article>
          <?php endif; ?>
        </div>

        <?php if ($dashboardWidgets === [] && (!$canEditPersonalDashboard || $availableDashboardWidgets === [])): ?>
          <div class="uk-placeholder uk-text-center"><p class="uk-text-muted uk-margin-remove">No widgets yet.</p></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canViewCatalog): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <header class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
        <div>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Commercial catalog</p>
          <h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom">Catalog overview</h2>
          <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Products, services, inventory and pricing readiness.</p>
        </div>
        <a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>catalog/"><span class="uk-visible@s">Open </span>Catalog</a>
      </header>

      <dl class="uk-grid-small uk-grid-divider uk-child-width-1-2 uk-child-width-1-4@m uk-margin-remove" uk-grid>
        <div>
          <dt class="uk-text-meta"><i class="fa fa-cube uk-text-primary"></i> Products</dt>
          <dd class="uk-h2 uk-margin-small-top uk-margin-remove-bottom"><?= $e($catalogSummary['products']) ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta"><i class="fa fa-wrench uk-text-primary"></i> Services</dt>
          <dd class="uk-h2 uk-margin-small-top uk-margin-remove-bottom"><?= $e($catalogSummary['services']) ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta"><i class="fa fa-cubes uk-text-primary"></i> Inventory tracked</dt>
          <dd class="uk-h2 uk-margin-small-top uk-margin-remove-bottom"><?= $e($catalogSummary['inventoryTracked']) ?></dd>
        </div>
        <div>
          <dt class="uk-text-meta"><i class="fa fa-tags uk-text-primary"></i> Price lists</dt>
          <dd class="uk-h2 uk-margin-small-top uk-margin-remove-bottom"><?= $e($catalogSummary['priceLists'] ?? 0) ?></dd>
        </div>
      </dl>

      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin-top">
        <div class="uk-flex uk-flex-wrap uk-grid-small uk-text-meta" uk-grid>
          <span><strong><?= $e($catalogSummary['archived']) ?></strong> archived items</span>
          <span><strong><?= $e($catalogSummary['upcomingPriceLists'] ?? 0) ?></strong> scheduled price lists</span>
        </div>
        <?php if ($catalogIssues === []): ?>
          <span class="uk-label uk-label-success"><i class="fa fa-check"></i> Catalog ready</span>
        <?php endif; ?>
      </div>

      <?php if ($catalogIssues !== []): ?>
        <div class="uk-alert-warning uk-margin-top uk-margin-remove-bottom" uk-alert>
          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
            <strong><i class="fa fa-exclamation-triangle"></i> Needs attention</strong>
            <ul class="uk-subnav uk-subnav-divider uk-margin-remove">
              <?php foreach ($catalogIssues as $label => $count): ?>
                <li><span><strong><?= $e($count) ?></strong> <?= $e(strtolower($label)) ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($recentCatalogItems): ?>
        <div class="uk-background-muted uk-padding-small uk-margin-top">
          <div class="uk-flex uk-flex-between uk-flex-middle">
            <strong>Recently updated</strong>
            <span class="uk-text-meta"><?= $e(count($recentCatalogItems)) ?> item<?= count($recentCatalogItems) === 1 ? '' : 's' ?></span>
          </div>
          <ul class="uk-list uk-list-divider uk-margin-small-top uk-margin-remove-bottom">
            <?php foreach ($recentCatalogItems as $item): ?>
              <li class="uk-flex uk-flex-between uk-flex-middle">
                <div>
                  <a href="<?= $e($adminUrl) ?>catalog-item/?id=<?= $e(rawurlencode($item->uid->toString())) ?>">
                    <strong><?= $e($item->titleIn($catalogLanguage) ?? $item->titleIn('en') ?? reset($item->title) ?: 'Untitled item') ?></strong>
                  </a>
                  <div class="uk-text-meta">
                    <?= $e(ucfirst($item->itemType)) ?> · <?= $e(ucfirst($item->status)) ?>
                    <?= $item->salesPrice === null ? ' · No sales price' : '' ?>
                  </div>
                </div>
                <i class="fa fa-chevron-right uk-text-muted" aria-hidden="true"></i>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!$contactsReady): ?>
    <div class="uk-alert-warning uk-margin-medium-bottom" uk-alert>
      <strong>Contacts is not installed yet.</strong> Install the Contacts component to activate the customer workspace.
    </div>
  <?php endif; ?>

  <section class="uk-margin-medium-bottom">
    <header class="uk-margin-bottom">
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Workspace status</p>
      <h2 class="uk-h3 uk-margin-small-top uk-margin-remove-bottom">At a glance</h2>
    </header>
    <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@l uk-grid-match" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-address-book"></i></span><span><strong class="kontor-stat__value"><?= $e($contactCount) ?></strong><span class="kontor-stat__label">Active contacts</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-building"></i></span><span><strong class="kontor-stat__value"><?= $e($companyCount) ?></strong><span class="kontor-stat__label">Companies</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span><span><strong class="kontor-stat__value"><?= $e($enabledComponents) ?></strong><span class="kontor-stat__label">Enabled components</span></span></div></div>
      <?php if ($queueReady && $canViewQueue): ?>
        <div>
          <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat">
            <span class="kontor-stat__icon<?= ($queueCounts['dead'] ?? 0) > 0 ? ' kontor-stat__icon--danger' : '' ?>"><i class="fa fa-tasks"></i></span>
            <span><strong class="kontor-stat__value"><?= $e($activeJobs) ?></strong><span class="kontor-stat__label">Active jobs<?= ($queueCounts['dead'] ?? 0) > 0 ? ' · ' . $e($queueCounts['dead']) . ' dead' : '' ?></span></span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="uk-grid-medium uk-child-width-1-2@m uk-grid-match uk-margin-medium-bottom" uk-grid>
    <div>
      <article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <header class="kontor-panel__head">
          <h2 class="uk-h3 uk-margin-remove">Recently updated contacts</h2>
          <?php if ($contactsReady): ?><a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>contacts/">View all</a><?php endif; ?>
        </header>
        <?php if ($recentContacts): ?>
          <ul class="kontor-list">
            <?php foreach ($recentContacts as $contact): ?>
              <li>
                <span class="kontor-avatar"><?= $e(mb_substr($contact->displayName, 0, 2)) ?></span>
                <span class="kontor-list__body">
                  <a href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($contact->uid->toString())) ?>"><?= $e($contact->displayName) ?></a>
                  <small><?= $e($contact->email ?: $contact->jobTitle ?: 'No contact details yet') ?></small>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="uk-placeholder uk-text-center"><p class="uk-text-muted uk-margin-remove">No contacts yet.</p></div>
        <?php endif; ?>
      </article>
    </div>

    <div>
      <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <header class="kontor-panel__head">
          <h2 class="uk-h3 uk-margin-remove">Your quick access</h2>
          <a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>sections/">Customize</a>
        </header>
        <div class="kontor-quicklinks">
          <?php foreach ($navigationGroups as $items): ?>
            <?php foreach ($items as $item): ?>
              <?php if (in_array($item['key'], $quickNavigationKeys, true)): ?>
                <a class="kontor-quicklink" href="<?= $e($adminUrl . $item['url']) ?>"><i class="fa fa-<?= $e($item['icon']) ?>"></i><span><?= $e($item['label']) ?></span></a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
          <?php if ($quickNavigationKeys === []): ?><p class="uk-text-muted">No pinned sections yet.</p><?php endif; ?>
        </div>
      </aside>
    </div>
  </section>

  <?php if ($canViewActivity): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body">
      <header class="kontor-panel__head">
        <h2 class="uk-h3 uk-margin-remove">Recent activity</h2>
        <a class="uk-button uk-button-default uk-button-small kontor-button" href="<?= $e($adminUrl) ?>activity/">Open audit trail</a>
      </header>
      <?php if ($recentActivity): ?>
        <div class="kontor-dashboardactivity__list">
          <?php foreach ($recentActivity as $event): ?>
            <article>
              <span class="kontor-dashboardactivity__icon"><i class="fa fa-<?= $event->entityType === 'backup' ? 'database' : ($event->entityType === 'job' ? 'tasks' : 'history') ?>"></i></span>
              <div>
                <strong><?= $e(ucwords(str_replace(['.', '_'], ' ', $event->action))) ?></strong>
                <p><?= $e(ucfirst($event->entityType)) ?> · <?= $e($event->component) ?></p>
              </div>
              <time datetime="<?= $e($event->occurredAt->format(DATE_ATOM)) ?>"><?= $e($event->occurredAt->format('M j, H:i')) ?></time>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="uk-placeholder uk-text-center"><p class="uk-text-muted uk-margin-remove">Operational changes will appear here.</p></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
