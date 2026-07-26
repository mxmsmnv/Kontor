<?php

/** @var \ProcessWire\InputfieldForm $form */
/** @var string $backUrl */
/** @var string $backLabel */
/** @var string $eyebrow */
/** @var string $title */
/** @var string $description */
/** @var object|null $entity */
/** @var string $entityType */
/** @var array $relationships */
/** @var array $availableCompanies */
/** @var array $addresses */
/** @var array $duplicates */
/** @var array $tags */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($backUrl) ?>">
      <i class="fa fa-arrow-left"></i> <?= $e($backLabel) ?>
    </a>
    <p class="kontor-eyebrow"><?= $e($eyebrow) ?></p>
    <h2><?= $e($title) ?></h2>
    <p><?= $e($description) ?></p>
  </header>
  <?php if ($duplicates): ?>
    <aside class="kontor-warning" role="alert">
      <i class="fa fa-exclamation-triangle"></i>
      <div>
        <strong>Possible duplicate contact</strong>
        <p>
          The email or phone already belongs to
          <?php foreach ($duplicates as $index => $duplicate): ?>
            <?= $index > 0 ? ', ' : '' ?><a href="<?= $e($adminUrl) ?>contact/?id=<?= $e(rawurlencode($duplicate->uid->toString())) ?>"><?= $e($duplicate->displayName) ?></a>
          <?php endforeach; ?>.
          Review the existing record or confirm creation below.
        </p>
      </div>
    </aside>
  <?php endif; ?>
  <?= $form->render() ?>

  <?php if ($entity !== null): ?>
    <section class="kontor-card kontor-tags">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Classification</p>
          <h3>Tags</h3>
        </div>
        <div class="kontor-taglist">
          <?php foreach ($tags as $tag): ?>
            <span class="kontor-tag"><?= $e($tag) ?></span>
          <?php endforeach; ?>
          <?php if (!$tags): ?><span class="kontor-secondary">No tags</span><?php endif; ?>
        </div>
      </div>
      <form class="kontor-tagform" method="post" action="<?= $e($adminUrl) ?>tags/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="owner_type" value="<?= $e($entityType) ?>">
        <input type="hidden" name="owner_uid" value="<?= $e($entity->uid->toString()) ?>">
        <input type="text" name="tags" value="<?= $e(implode(', ', $tags)) ?>" placeholder="customer, partner, vip">
        <button class="kontor-button kontor-button--ghost" type="submit">
          <i class="fa fa-tags"></i> Update tags
        </button>
      </form>
    </section>

    <section class="kontor-card kontor-relations">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow"><?= $entityType === 'contact' ? 'Affiliations' : 'People' ?></p>
          <h3><?= $entityType === 'contact' ? 'Company relationships' : 'Company contacts' ?></h3>
        </div>
        <span class="kontor-secondary"><?= $e(count($relationships)) ?> linked</span>
      </div>

      <?php if ($relationships): ?>
        <div class="kontor-relationlist">
          <?php foreach ($relationships as $relationship): ?>
            <?php $membership = $relationship['membership']; $related = $relationship['entity']; ?>
            <article class="kontor-relation">
              <span class="kontor-avatar"><?= $e(mb_substr($entityType === 'contact' ? $related->legalName : $related->displayName, 0, 2)) ?></span>
              <div class="kontor-relation__body">
                <a href="<?= $e($adminUrl) ?><?= $entityType === 'contact' ? 'company' : 'contact' ?>/?id=<?= $e(rawurlencode($related->uid->toString())) ?>">
                  <?= $e($entityType === 'contact' ? $related->legalName : $related->displayName) ?>
                </a>
                <span class="kontor-secondary">
                  <?= $e($membership->role ?: 'Member') ?>
                  <?= $membership->department ? ' · ' . $e($membership->department) : '' ?>
                </span>
              </div>
              <span class="kontor-pill<?= $membership->endedAt ? ' kontor-pill--inactive' : '' ?>">
                <?= $membership->endedAt ? 'ended ' . $e($membership->endedAt->format('M Y')) : 'current' ?>
              </span>
              <?php if ($entityType === 'contact' && $membership->endedAt === null): ?>
                <form class="kontor-inline-action" method="post" action="<?= $e($adminUrl) ?>relationship/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="action" value="end">
                  <input type="hidden" name="contact_id" value="<?= $e($entity->uid->toString()) ?>">
                  <input type="hidden" name="company_id" value="<?= $e($related->uid->toString()) ?>">
                  <button type="submit" title="End relationship"><i class="fa fa-unlink"></i></button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="kontor-relations__empty">
          <?= $entityType === 'contact' ? 'This contact is not linked to a company yet.' : 'No contacts are linked to this company yet.' ?>
        </p>
      <?php endif; ?>

      <?php if ($entityType === 'contact' && $availableCompanies): ?>
        <form class="kontor-relationform" method="post" action="<?= $e($adminUrl) ?>relationship/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="contact_id" value="<?= $e($entity->uid->toString()) ?>">
          <label>
            <span>Company</span>
            <select name="company_id" required>
              <option value="">Choose a company</option>
              <?php foreach ($availableCompanies as $company): ?>
                <option value="<?= $e($company->uid->toString()) ?>"><?= $e($company->legalName) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span>Role</span>
            <input type="text" name="role" placeholder="e.g. Decision maker">
          </label>
          <label>
            <span>Department</span>
            <input type="text" name="department" placeholder="e.g. Operations">
          </label>
          <button class="kontor-button kontor-button--ghost" type="submit">
            <i class="fa fa-link"></i> Link company
          </button>
        </form>
      <?php elseif ($entityType === 'contact'): ?>
        <p class="kontor-relations__empty">
          <a href="<?= $e($adminUrl) ?>company/">Create a company</a> before adding an affiliation.
        </p>
      <?php endif; ?>
    </section>

    <section class="kontor-card kontor-addresses">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Locations</p>
          <h3>Addresses</h3>
        </div>
        <span class="kontor-secondary"><?= $e(count($addresses)) ?> saved</span>
      </div>

      <?php if ($addresses): ?>
        <div class="kontor-addressgrid">
          <?php foreach ($addresses as $address): ?>
            <article class="kontor-address">
              <div class="kontor-address__top">
                <span class="kontor-pill<?= $address->isPrimary ? '' : ' kontor-pill--inactive' ?>">
                  <?= $e($address->isPrimary ? 'primary ' . $address->addressType : $address->addressType) ?>
                </span>
                <form class="kontor-inline-action" method="post" action="<?= $e($adminUrl) ?>address/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="owner_type" value="<?= $e($entityType) ?>">
                  <input type="hidden" name="owner_uid" value="<?= $e($entity->uid->toString()) ?>">
                  <input type="hidden" name="address_uid" value="<?= $e($address->uid->toString()) ?>">
                  <button type="submit" title="Remove address" onclick="return confirm('Remove this address?')"><i class="fa fa-trash"></i></button>
                </form>
              </div>
              <address>
                <strong><?= $e($address->line1) ?></strong>
                <?php if ($address->line2): ?><span><?= $e($address->line2) ?></span><?php endif; ?>
                <span>
                  <?= $e(implode(', ', array_filter([$address->city, $address->region, $address->postalCode]))) ?>
                </span>
                <span><?= $e($address->countryCode) ?></span>
              </address>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="kontor-relations__empty">No addresses have been added yet.</p>
      <?php endif; ?>

      <form class="kontor-addressform" method="post" action="<?= $e($adminUrl) ?>address/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="owner_type" value="<?= $e($entityType) ?>">
        <input type="hidden" name="owner_uid" value="<?= $e($entity->uid->toString()) ?>">
        <label>
          <span>Type</span>
          <select name="address_type">
            <option value="billing">Billing</option>
            <option value="shipping">Shipping</option>
            <option value="<?= $entityType === 'contact' ? 'home' : 'work' ?>"><?= $entityType === 'contact' ? 'Home' : 'Work' ?></option>
            <option value="other">Other</option>
          </select>
        </label>
        <label class="kontor-addressform__wide">
          <span>Street address</span>
          <input type="text" name="line1" required placeholder="Street and number">
        </label>
        <label>
          <span>Address line 2</span>
          <input type="text" name="line2" placeholder="Suite, floor">
        </label>
        <label>
          <span>City</span>
          <input type="text" name="city" required>
        </label>
        <label>
          <span>Region</span>
          <input type="text" name="region">
        </label>
        <label>
          <span>Postal code</span>
          <input type="text" name="postal_code">
        </label>
        <label>
          <span>Country</span>
          <input type="text" name="country_code" required maxlength="2" placeholder="US">
        </label>
        <label class="kontor-check">
          <input type="checkbox" name="is_primary" value="1">
          <span>Primary address</span>
        </label>
        <button class="kontor-button kontor-button--ghost" type="submit">
          <i class="fa fa-map-marker"></i> Add address
        </button>
      </form>
    </section>
  <?php endif; ?>
</div>
