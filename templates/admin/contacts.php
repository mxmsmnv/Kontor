<?php

/** @var array $contacts */
/** @var string $query */
/** @var string $selectedStatus */
/** @var bool $showArchived */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalRecords */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$pageUrl = static function (int $targetPage) use ($query, $selectedStatus, $showArchived): string {
    return './?' . http_build_query(array_filter([
        'q' => $query,
        'status' => $selectedStatus,
        'archived' => $showArchived ? 1 : '',
        'page' => $targetPage,
    ], static fn (string|int $value): bool => $value !== ''));
};
$viewUrl = './?' . http_build_query(array_filter([
    'q' => $query,
    'archived' => $showArchived ? '' : 1,
], static fn (string|int $value): bool => $value !== ''));
$filterParameters = array_filter([
    'q' => $query,
    'status' => $selectedStatus,
], static fn (string $value): bool => $value !== '');
$statusUrl = static fn (string $status): string => './?' . http_build_query([
    ...$filterParameters,
    'status' => $status,
]);
$clearFiltersUrl = $showArchived ? './?archived=1' : './';
$hasFilters = $query !== '' || $selectedStatus !== '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Customer directory</p>
      <h2>Contacts</h2>
      <p>People, communication details and relationship context.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>import/?entity=contact">
        <i class="fa fa-upload"></i> Import
      </a>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>export/?entity=contact&amp;format=csv">
        <i class="fa fa-download"></i> CSV
      </a>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>export/?entity=contact&amp;format=json">
        JSON
      </a>
      <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>contact/">
        <i class="fa fa-plus"></i> New contact
      </a>
    </div>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Search name, email, phone or role">
    </label>
    <?php if (!$showArchived): ?>
      <select name="status" aria-label="Contact status">
        <option value="">All statuses</option>
        <option value="active"<?= $selectedStatus === 'active' ? ' selected' : '' ?>>Active</option>
        <option value="inactive"<?= $selectedStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
      </select>
    <?php endif; ?>
    <button class="uk-button uk-button-primary kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?>
      <a class="kontor-viewtoggle" href="<?= $e($clearFiltersUrl) ?>">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
    <a class="kontor-viewtoggle" href="<?= $e($viewUrl) ?>">
      <i class="fa fa-<?= $showArchived ? 'address-book' : 'archive' ?>"></i>
      <?= $showArchived ? 'Active contacts' : 'Archive' ?>
    </a>
    <span class="kontor-secondary"><?= $e($totalRecords) ?> total · <?= $e(count($contacts)) ?> shown</span>
  </form>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap kontor-directorytable">
    <?php if ($contacts): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead>
          <tr>
            <th>Contact</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Status</th>
            <th><span class="kontor-visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contacts as $contact): ?>
            <tr>
              <td>
                <div class="kontor-identity">
                  <span class="kontor-avatar"><?= $e(mb_substr($contact->displayName, 0, 2)) ?></span>
                  <span>
                    <a href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($contact->uid->toString())) ?>">
                      <?= $e($contact->displayName) ?>
                    </a>
                    <span class="kontor-secondary"><?= $e($contact->email ?: 'No email') ?></span>
                  </span>
                </div>
              </td>
              <td><?= $e($contact->jobTitle ?: '—') ?></td>
              <td><?= $e($contact->mobile ?: $contact->phone ?: '—') ?></td>
              <td>
                <?php if ($showArchived): ?>
                  <span class="uk-label kontor-pill kontor-pill--inactive">archived</span>
                <?php else: ?>
                  <a
                    class="uk-label kontor-pill<?= $contact->status === 'active' ? '' : ' kontor-pill--inactive' ?>"
                    href="<?= $e($statusUrl($contact->status)) ?>"
                    <?= $selectedStatus === $contact->status ? 'aria-current="page"' : '' ?>
                  ><?= $e($contact->status) ?></a>
                <?php endif; ?>
              </td>
              <td class="kontor-rowaction">
                <form method="post" action="<?= $e($adminUrl) ?>contact-status/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="id" value="<?= $e($contact->uid->toString()) ?>">
                  <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
                  <button type="submit" title="<?= $showArchived ? 'Restore contact' : 'Archive contact' ?>">
                    <i class="fa fa-<?= $showArchived ? 'undo' : 'archive' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty">
        <i class="fa fa-address-book"></i>
        <h3><?= $query !== '' ? 'No matching contacts' : ($showArchived ? 'The archive is empty' : 'Your contact list is empty') ?></h3>
        <p><?= $query !== '' ? 'Try a broader search.' : ($showArchived ? 'Archived contacts will appear here.' : 'Create the first person in your directory.') ?></p>
      </div>
    <?php endif; ?>
  </section>
  <?php if ($totalPages > 1): ?>
    <nav class="kontor-pagination" aria-label="Contact pages">
      <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
      <div>
        <?php if ($page > 1): ?>
          <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page - 1)) ?>">
            <i class="fa fa-chevron-left"></i> Previous
          </a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($pageUrl($page + 1)) ?>">
            Next <i class="fa fa-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  <?php endif; ?>
</div>
