<?php

/** @var array $components */
/** @var bool $contactsReady */
/** @var int $contactCount */
/** @var int $companyCount */
/** @var array $recentContacts */
/** @var bool $catalogReady */
/** @var bool $canViewCatalog */
/** @var bool $canCreateCatalogItems */
/** @var array{products: int, services: int, archived: int, inventoryTracked: int, unpriced: int, priceLists?: int, expiredPriceLists?: int, upcomingPriceLists?: int} $catalogSummary */
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
/** @var string $adminUrl */
/** @var callable $e */

$enabledComponents = count(array_filter(
    $components,
    static fn (array $component): bool => ($component['status'] ?? '') === 'enabled'
));
?>
<div class="kontor-shell">
  <section class="kontor-hero">
    <div class="kontor-hero__content">
      <p class="kontor-eyebrow">Operations workspace</p>
      <h2>Your business, in one place.</h2>
      <p>Kontor connects customer data, companies and operational components inside ProcessWire.</p>
    </div>
    <?php if ($contactsReady || $canCreateCatalogItems): ?>
      <div class="kontor-hero__actions">
        <?php if ($contactsReady): ?>
          <a class="kontor-button kontor-button--light" href="<?= $e($adminUrl) ?>contact/">
            <i class="fa fa-plus"></i> New contact
          </a>
          <a class="kontor-button" href="<?= $e($adminUrl) ?>company/">
            <i class="fa fa-building"></i> New company
          </a>
        <?php endif; ?>
        <?php if ($canCreateCatalogItems): ?>
          <a class="kontor-button<?= $contactsReady ? ' kontor-button--light' : '' ?>" href="<?= $e($adminUrl) ?>catalog-item/">
            <i class="fa fa-cube"></i> New catalog item
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($canViewCatalog): ?>
    <section class="kontor-card kontor-catalogoverview">
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
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon"><i class="fa fa-address-book"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($contactCount) ?></strong>
        <span class="kontor-stat__label">Active contacts</span>
      </span>
    </article>
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon"><i class="fa fa-building"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($companyCount) ?></strong>
        <span class="kontor-stat__label">Companies</span>
      </span>
    </article>
    <article class="kontor-card kontor-stat">
      <span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span>
      <span>
        <strong class="kontor-stat__value"><?= $e($enabledComponents) ?></strong>
        <span class="kontor-stat__label">Enabled components</span>
      </span>
    </article>
    <?php if ($queueReady && $canViewQueue): ?>
      <?php $activeJobs = ($queueCounts['pending'] ?? 0) + ($queueCounts['reserved'] ?? 0); ?>
      <article class="kontor-card kontor-stat">
        <span class="kontor-stat__icon<?= ($queueCounts['dead'] ?? 0) > 0 ? ' kontor-stat__icon--danger' : '' ?>">
          <i class="fa fa-tasks"></i>
        </span>
        <span>
          <strong class="kontor-stat__value"><?= $e($activeJobs) ?></strong>
          <span class="kontor-stat__label">
            Active jobs<?= ($queueCounts['dead'] ?? 0) > 0 ? ' · ' . $e($queueCounts['dead']) . ' dead' : '' ?>
          </span>
        </span>
      </article>
    <?php endif; ?>
  </section>

  <section class="kontor-grid">
    <article class="kontor-card kontor-panel">
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
        <div class="kontor-empty">
          <i class="fa fa-user-plus"></i>
          <h3>No contacts yet</h3>
          <p>Create your first contact to start building the workspace.</p>
        </div>
      <?php endif; ?>
    </article>

    <aside class="kontor-card kontor-panel">
      <header class="kontor-panel__head"><h3>Quick access</h3></header>
      <div class="kontor-quicklinks">
        <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>contacts/">
          <i class="fa fa-address-book"></i><span>Browse contacts</span>
        </a>
        <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>companies/">
          <i class="fa fa-building"></i><span>Browse companies</span>
        </a>
        <?php if ($canViewCatalog): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>catalog/">
            <i class="fa fa-cube"></i><span>Browse catalog</span>
          </a>
        <?php endif; ?>
        <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>components/">
          <i class="fa fa-cubes"></i><span>Component status</span>
        </a>
        <?php if ($canViewActivity): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>activity/">
            <i class="fa fa-history"></i><span>Recent activity</span>
          </a>
        <?php endif; ?>
        <?php if ($canViewBackups): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>backups/">
            <i class="fa fa-database"></i><span>Backup snapshots</span>
          </a>
        <?php endif; ?>
        <?php if ($canViewHealth): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>health/">
            <i class="fa fa-heartbeat"></i><span>System health</span>
          </a>
        <?php endif; ?>
        <?php if ($canManageOrganization): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>organization/">
            <i class="fa fa-briefcase"></i><span>Organization settings</span>
          </a>
        <?php endif; ?>
        <?php if ($queueReady && $canViewQueue): ?>
          <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>queue/">
            <i class="fa fa-tasks"></i><span>Queue monitor</span>
          </a>
        <?php endif; ?>
      </div>
    </aside>
  </section>

  <?php if ($canViewActivity): ?>
    <section class="kontor-card kontor-dashboardactivity">
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
