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
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <section class="kontor-hero">
    <div class="kontor-hero__content">
      <p class="kontor-eyebrow">Operations workspace</p>
      <h2>Your business, in one place.</h2>
      <p>Kontor connects customer data, companies and operational components inside ProcessWire.</p>
    </div>
    <?php if ($contactsReady || $canCreateCatalogItems): ?>
      <div class="kontor-hero__actions">
        <?php if ($contactsReady): ?>
          <a class="uk-button uk-button-default kontor-button kontor-button--light" href="<?= $e($adminUrl) ?>contact/">
            <i class="fa fa-plus"></i> New contact
          </a>
          <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>company/">
            <i class="fa fa-building"></i> New company
          </a>
        <?php endif; ?>
        <?php if ($canCreateCatalogItems): ?>
          <a class="uk-button <?= $contactsReady ? 'uk-button-default kontor-button--light' : 'uk-button-primary' ?> kontor-button" href="<?= $e($adminUrl) ?>catalog-item/">
            <i class="fa fa-cube"></i> New catalog item
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($dashboardReady && $canViewPersonalDashboard): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-panel">
      <header class="kontor-panel__head">
        <div>
          <p class="kontor-eyebrow">Personal layout</p>
          <h3><?= $e($personalDashboard?->name ?? 'Your dashboard') ?></h3>
        </div>
      </header>
      <?php if ($personalDashboard === null): ?>
        <?php if ($canCreatePersonalDashboard): ?>
          <form method="post" action="<?= $e($adminUrl) ?>dashboard-save/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <label>Dashboard name <input name="name" value="My dashboard" required></label>
            <button class="uk-button uk-button-primary kontor-button" type="submit">Create personal dashboard</button>
          </form>
        <?php else: ?>
          <p>No personal or role dashboard is configured for you yet.</p>
        <?php endif; ?>
      <?php else: ?>
        <div class="kontor-grid">
          <?php foreach ($renderedPersonalDashboard['widgets'] ?? [] as $renderedWidget): ?>
            <?php $layout = $renderedWidget['layout']; ?>
            <article class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-panel" style="grid-column: span <?= $e(max(2, min(12, $layout->width))) ?>;">
              <header class="kontor-panel__head"><h3><?= $e($renderedWidget['title']) ?></h3><span class="uk-label kontor-pill<?= $renderedWidget['cacheHit'] ? '' : ' kontor-pill--inactive' ?>"><?= $renderedWidget['cacheHit'] ? 'cached' : 'fresh' ?></span></header>
              <?php if ($layout->widgetKey === 'welcome'): ?>
                <p>Welcome to your saved Kontor workspace.</p>
                <small>Generated <?= $e($renderedWidget['data']['generatedAt'] ?? '') ?></small>
              <?php elseif ($layout->widgetKey === 'tasks.my_open'): ?>
                <p><strong><?= $e((string) ($renderedWidget['data']['count'] ?? 0)) ?></strong> open · <?= $e((string) ($renderedWidget['data']['overdueCount'] ?? 0)) ?> overdue</p>
                <?php if (($renderedWidget['data']['tasks'] ?? []) !== []): ?>
                  <ul class="kontor-list">
                    <?php foreach ($renderedWidget['data']['tasks'] as $task): ?>
                      <li>
                        <a href="<?= $e($adminUrl) ?>task/?id=<?= $e(rawurlencode((string) $task['uid'])) ?>"><?= $e((string) $task['title']) ?></a>
                        <span class="uk-label kontor-pill<?= !empty($task['overdue']) ? ' kontor-pill--danger' : '' ?>"><?= $e((string) $task['priority']) ?><?= !empty($task['dueAt']) ? ' · ' . $e(substr((string) $task['dueAt'], 0, 10)) : '' ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p>No open tasks assigned to you.</p>
                <?php endif; ?>
                <a href="<?= $e($adminUrl) ?>tasks/">Open tasks</a>
              <?php else: ?>
                <pre><?= $e(json_encode($renderedWidget['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
              <?php endif; ?>
              <?php if ($canEditPersonalDashboard): ?>
                <form method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>">
                  <input type="hidden" name="widget_uid" value="<?= $e($layout->uid->toString()) ?>">
                  <button name="action" value="move_left" type="submit">←</button>
                  <button name="action" value="move_right" type="submit">→</button>
                  <button name="action" value="narrower" type="submit">Narrower</button>
                  <button name="action" value="wider" type="submit">Wider</button>
                  <button name="action" value="remove" type="submit">Remove</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if (($renderedPersonalDashboard['widgets'] ?? []) === []): ?><p>No widgets yet.</p><?php endif; ?>
        <?php if ($canEditPersonalDashboard && $availableDashboardWidgets !== []): ?>
          <form method="post" action="<?= $e($adminUrl) ?>dashboard-widget-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="dashboard_uid" value="<?= $e($personalDashboard->uid->toString()) ?>">
            <label>Widget
              <select name="widget_key">
                <?php foreach ($availableDashboardWidgets as $key => $provider): ?><option value="<?= $e($key) ?>"><?= $e($provider->title()) ?></option><?php endforeach; ?>
              </select>
            </label>
            <button class="uk-button uk-button-primary kontor-button" name="action" value="add" type="submit">Add widget</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canViewCatalog): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-catalogoverview">
      <header class="kontor-panel__head">
        <div>
          <p class="kontor-eyebrow">Commercial catalog</p>
          <h3>Catalog overview</h3>
        </div>
        <a href="<?= $e($adminUrl) ?>catalog/">Open catalog</a>
      </header>
      <div class="kontor-catalogmetrics">
        <a href="<?= $e($adminUrl) ?>catalog/?type=product">
          <i class="fa fa-cube"></i>
          <strong><?= $e($catalogSummary['products']) ?></strong>
          <span>Products</span>
        </a>
        <a href="<?= $e($adminUrl) ?>catalog/?type=service">
          <i class="fa fa-wrench"></i>
          <strong><?= $e($catalogSummary['services']) ?></strong>
          <span>Services</span>
        </a>
        <a href="<?= $e($adminUrl) ?>catalog/?archived=1">
          <i class="fa fa-archive"></i>
          <strong><?= $e($catalogSummary['archived']) ?></strong>
          <span>Archived</span>
        </a>
        <a href="<?= $e($adminUrl) ?>catalog-price-lists/">
          <i class="fa fa-tags"></i>
          <strong><?= $e($catalogSummary['priceLists'] ?? 0) ?></strong>
          <span>Price lists</span>
        </a>
      </div>
      <div class="kontor-catalogrecent">
        <span class="kontor-secondary">
          <a href="<?= $e($adminUrl) ?>catalog/?pricing=unpriced">
            <?= $e($catalogSummary['unpriced']) ?> item(s) without sales price
          </a>
          ·
          <a href="<?= $e($adminUrl) ?>catalog/?category=uncategorized">
            <?= $e($catalogSummary['uncategorized']) ?> uncategorized item(s)
          </a>
          ·
          <a href="<?= $e($adminUrl) ?>catalog/?inventory=tracked">
            <?= $e($catalogSummary['inventoryTracked']) ?> item(s) track inventory
          </a>
          ·
          <a href="<?= $e($adminUrl) ?>catalog-price-lists/?validity=expired">
            <?= $e($catalogSummary['expiredPriceLists'] ?? 0) ?> expired price list(s)
          </a>
          ·
          <a href="<?= $e($adminUrl) ?>catalog-price-lists/?validity=upcoming">
            <?= $e($catalogSummary['upcomingPriceLists'] ?? 0) ?> upcoming price list(s)
          </a>
        </span>
        <?php if ($recentCatalogItems): ?>
          <div>
            <?php foreach ($recentCatalogItems as $item): ?>
              <a href="<?= $e($adminUrl) ?>catalog-item/?id=<?= $e(rawurlencode($item->uid->toString())) ?>">
                <?= $e($item->titleIn($catalogLanguage) ?? $item->titleIn('en') ?? reset($item->title) ?: 'Untitled item') ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span class="kontor-secondary">Create the first product or service to populate this overview.</span>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$contactsReady): ?>
    <div class="kontor-setup">
      <strong>Contacts is not installed yet.</strong>
      Install the Contacts component to activate the customer workspace.
    </div>
  <?php endif; ?>

  <section class="kontor-statgrid<?= $queueReady && $canViewQueue ? ' kontor-statgrid--four' : '' ?>">
    <a class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-stat" href="<?= $e($adminUrl) ?>contacts/">
      <span class="kontor-stat__icon"><i class="fa fa-address-book"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($contactCount) ?></strong>
        <span class="kontor-stat__label">Active contacts</span>
      </span>
    </a>
    <a class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-stat" href="<?= $e($adminUrl) ?>companies/">
      <span class="kontor-stat__icon"><i class="fa fa-building"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($companyCount) ?></strong>
        <span class="kontor-stat__label">Companies</span>
      </span>
    </a>
    <a class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-stat" href="<?= $e($adminUrl) ?>components/?status=enabled">
      <span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($enabledComponents) ?></strong>
        <span class="kontor-stat__label">Enabled components</span>
      </span>
    </a>
    <?php if ($queueReady && $canViewQueue): ?>
      <?php $activeJobs = ($queueCounts['pending'] ?? 0) + ($queueCounts['reserved'] ?? 0); ?>
      <a class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-stat" href="<?= $e($adminUrl) ?>queue/?status=active">
        <span class="kontor-stat__icon<?= ($queueCounts['dead'] ?? 0) > 0 ? ' kontor-stat__icon--danger' : '' ?>">
          <i class="fa fa-tasks"></i>
        </span>
        <span>
          <strong class="kontor-stat__value"><?= $e($activeJobs) ?></strong>
          <span class="kontor-stat__label">
            Active jobs<?= ($queueCounts['dead'] ?? 0) > 0 ? ' · ' . $e($queueCounts['dead']) . ' dead' : '' ?>
          </span>
        </span>
      </a>
    <?php endif; ?>
  </section>

  <section class="kontor-grid">
    <article class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-panel">
      <header class="kontor-panel__head">
        <h3>Recently updated contacts</h3>
        <?php if ($contactsReady): ?>
          <a href="<?= $e($adminUrl) ?>contacts/">View all</a>
        <?php endif; ?>
      </header>
      <?php if ($recentContacts): ?>
        <ul class="kontor-list">
          <?php foreach ($recentContacts as $contact): ?>
            <li>
              <span class="kontor-avatar"><?= $e(mb_substr($contact->displayName, 0, 2)) ?></span>
              <span class="kontor-list__body">
                <a href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($contact->uid->toString())) ?>">
                  <?= $e($contact->displayName) ?>
                </a>
                <small><?= $e($contact->email ?: $contact->jobTitle ?: 'No contact details yet') ?></small>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty">
          <i class="fa fa-user-plus"></i>
          <h3>No contacts yet</h3>
          <p>Create your first contact to start building the workspace.</p>
        </div>
      <?php endif; ?>
    </article>

    <aside class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-panel">
      <header class="kontor-panel__head"><h3>Your quick access</h3><a href="<?= $e($adminUrl) ?>sections/">Customize</a></header>
      <div class="kontor-quicklinks">
        <?php foreach ($navigationGroups as $items): ?>
          <?php foreach ($items as $item): ?>
            <?php if (in_array($item['key'], $quickNavigationKeys, true)): ?>
              <a class="kontor-quicklink" href="<?= $e($adminUrl . $item['url']) ?>">
                <i class="fa fa-<?= $e($item['icon']) ?>"></i><span><?= $e($item['label']) ?></span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($quickNavigationKeys === []): ?>
          <p class="kontor-secondary">No pinned sections yet.</p>
        <?php endif; ?>
      </div>
    </aside>
  </section>

  <?php if ($canViewActivity): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-dashboardactivity">
      <header class="kontor-panel__head">
        <h3>Recent activity</h3>
        <a href="<?= $e($adminUrl) ?>activity/">Open audit trail</a>
      </header>
      <?php if ($recentActivity): ?>
        <div class="kontor-dashboardactivity__list">
          <?php foreach ($recentActivity as $event): ?>
            <article>
              <span class="kontor-dashboardactivity__icon">
                <i class="fa fa-<?= $event->entityType === 'backup' ? 'database' : ($event->entityType === 'job' ? 'tasks' : 'history') ?>"></i>
              </span>
              <div>
                <strong>
                  <a href="<?= $e($adminUrl) ?>activity/?action=<?= $e(rawurlencode($event->action)) ?>">
                    <?= $e(ucwords(str_replace(['.', '_'], ' ', $event->action))) ?>
                  </a>
                </strong>
                <p>
                  <a href="<?= $e($adminUrl) ?>activity/?entity_type=<?= $e(rawurlencode($event->entityType)) ?>">
                    <?= $e(ucfirst($event->entityType)) ?>
                  </a>
                  ·
                  <a href="<?= $e($adminUrl) ?>activity/?component=<?= $e(rawurlencode($event->component)) ?>">
                    <?= $e($event->component) ?>
                  </a>
                </p>
              </div>
              <time datetime="<?= $e($event->occurredAt->format(DATE_ATOM)) ?>">
                <?= $e($event->occurredAt->format('M j, H:i')) ?>
              </time>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="kontor-dashboardactivity__empty">
          <i class="fa fa-history"></i>
          <span>Operational changes will appear here.</span>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
