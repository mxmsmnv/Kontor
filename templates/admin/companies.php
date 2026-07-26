<?php

/** @var array $companies */
/** @var string $query */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Commercial directory</p>
      <h2>Companies</h2>
      <p>Customers, partners and the organizations behind your work.</p>
    </div>
    <div class="kontor-pagehead__actions">
      <a class="kontor-button" href="<?= $e($adminUrl) ?>company/">
        <i class="fa fa-plus"></i> New company
      </a>
    </div>
  </header>

  <div class="kontor-toolbar">
    <form class="kontor-search" method="get" action="./">
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Search company, email or registration">
      <button class="kontor-button kontor-button--ghost" type="submit">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <span class="kontor-secondary"><?= $e(count($companies)) ?> shown</span>
  </div>

  <section class="kontor-card kontor-tablewrap">
    <?php if ($companies): ?>
      <table class="kontor-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Contact</th>
            <th>Registration</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($companies as $company): ?>
            <tr>
              <td>
                <div class="kontor-identity">
                  <span class="kontor-avatar"><?= $e(mb_substr($company->legalName, 0, 2)) ?></span>
                  <span>
                    <a href="<?= $e($adminUrl) ?>company/?id=<?= $e(rawurlencode($company->uid->toString())) ?>">
                      <?= $e($company->legalName) ?>
                    </a>
                    <span class="kontor-secondary"><?= $e($company->tradingName ?: $company->website ?: 'Company') ?></span>
                  </span>
                </div>
              </td>
              <td>
                <?= $e($company->email ?: '—') ?>
                <?php if ($company->phone): ?><span class="kontor-secondary"><?= $e($company->phone) ?></span><?php endif; ?>
              </td>
              <td><?= $e($company->registrationNumber ?: $company->vatNumber ?: '—') ?></td>
              <td>
                <span class="kontor-pill<?= $company->status === 'active' ? '' : ' kontor-pill--inactive' ?>">
                  <?= $e($company->status) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty">
        <i class="fa fa-building"></i>
        <h3><?= $query !== '' ? 'No matching companies' : 'No companies yet' ?></h3>
        <p><?= $query !== '' ? 'Try a broader search.' : 'Create your first customer or partner company.' ?></p>
      </div>
    <?php endif; ?>
  </section>
</div>
