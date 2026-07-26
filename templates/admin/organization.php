<?php

/** @var \Kontor\Core\Domain\Organization $organization */
/** @var \ProcessWire\InputfieldForm $form */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <p class="kontor-eyebrow">Workspace settings</p>
    <h2>Organization</h2>
    <p>Defaults used by business documents, reporting, dates, and localized components.</p>
  </header>

  <section class="kontor-organization-meta">
    <div>
      <span class="kontor-organization-meta__icon"><i class="fa fa-briefcase"></i></span>
      <span>
        <strong><?= $e($organization->name) ?></strong>
        <small><?= $e($organization->uid->toString()) ?></small>
      </span>
    </div>
    <span class="kontor-pill"><?= $e($organization->status) ?></span>
  </section>

  <?= $form->render() ?>
</div>
