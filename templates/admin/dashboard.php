<?php

/** @var array $components */
/** @var bool $contactsReady */
/** @var int $contactCount */
/** @var int $companyCount */
/** @var array $recentContacts */
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
    <?php if ($contactsReady): ?>
      <div class="kontor-hero__actions">
        <a class="kontor-button kontor-button--light" href="<?= $e($adminUrl) ?>contact/">
          <i class="fa fa-plus"></i> New contact
        </a>
        <a class="kontor-button" href="<?= $e($adminUrl) ?>company/">
          <i class="fa fa-building"></i> New company
        </a>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!$contactsReady): ?>
    <div class="kontor-setup">
      <strong>Contacts is not installed yet.</strong>
      Install the Contacts component to activate the customer workspace.
    </div>
  <?php endif; ?>

  <section class="kontor-statgrid">
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
        <a class="kontor-quicklink" href="<?= $e($adminUrl) ?>components/">
          <i class="fa fa-cubes"></i><span>Component status</span>
        </a>
      </div>
    </aside>
  </section>
</div>
