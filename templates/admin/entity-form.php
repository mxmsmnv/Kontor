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
  <?= $form->render() ?>

  <?php if ($entity !== null): ?>
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
  <?php endif; ?>
</div>
