<?php

/** @var array $contacts */
/** @var string $query */
/** @var bool $showArchived */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Customer directory</p>
      <h2>Contacts</h2>
      <p>People, communication details and relationship context.</p>
    </div>
    <div class="kontor-pagehead__actions">
      <a class="kontor-button" href="<?= $e($adminUrl) ?>contact/">
        <i class="fa fa-plus"></i> New contact
      </a>
    </div>
  </header>

  <div class="kontor-toolbar">
    <form class="kontor-search" method="get" action="./">
      <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Search name, email, phone or role">
      <button class="kontor-button kontor-button--ghost" type="submit">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <div class="kontor-toolbar__meta">
      <a class="kontor-viewtoggle" href="./<?= $showArchived ? '' : '?archived=1' ?>">
        <i class="fa fa-<?= $showArchived ? 'address-book' : 'archive' ?>"></i>
        <?= $showArchived ? 'Active contacts' : 'Archive' ?>
      </a>
      <span class="kontor-secondary"><?= $e(count($contacts)) ?> shown</span>
    </div>
  </div>

  <section class="kontor-card kontor-tablewrap">
    <?php if ($contacts): ?>
      <table class="kontor-table">
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
                <span class="kontor-pill<?= $contact->status === 'active' ? '' : ' kontor-pill--inactive' ?>">
                  <?= $e($showArchived ? 'archived' : $contact->status) ?>
                </span>
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
      <div class="kontor-empty">
        <i class="fa fa-address-book"></i>
        <h3><?= $query !== '' ? 'No matching contacts' : ($showArchived ? 'The archive is empty' : 'Your contact list is empty') ?></h3>
        <p><?= $query !== '' ? 'Try a broader search.' : ($showArchived ? 'Archived contacts will appear here.' : 'Create the first person in your directory.') ?></p>
      </div>
    <?php endif; ?>
  </section>
</div>
