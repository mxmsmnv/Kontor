<?php

/** @var \Kontor\Projects\Domain\Project[] $projects */
/** @var array<string, string> $customerLabels */
/** @var bool $canCreate */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Delivery</p><h2>Projects</h2><p>Turn milestones, time, and billable work into reviewable draft invoices.</p></div>
    <?php if ($canCreate): ?><div class="kontor-pagehead__actions"><a class="kontor-button" href="<?= $e($adminUrl) ?>project/"><i class="fa fa-plus"></i> New project</a></div><?php endif; ?>
  </header>
  <section class="kontor-card kontor-tablewrap">
    <?php if ($projects !== []): ?>
      <table class="kontor-table"><thead><tr><th>Code</th><th>Project</th><th>Customer</th><th>Rate</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($projects as $project): ?><tr>
          <td><strong><?= $e($project->code) ?></strong></td>
          <td><a href="<?= $e($adminUrl) ?>project/?id=<?= $e(rawurlencode($project->uid->toString())) ?>"><?= $e($project->name) ?></a></td>
          <td><?= $e($customerLabels[($project->customerType ?? '') . ':' . ($project->customerUid ?? '')] ?? '—') ?></td>
          <td><?= $project->defaultHourlyRateMinor !== null ? $e(number_format($project->defaultHourlyRateMinor / 100, 2, '.', '') . ' ' . ($project->currencyCode ?? '')) : '—' ?></td>
          <td><span class="kontor-pill<?= $project->isActive() ? '' : ' kontor-pill--inactive' ?>"><?= $e($project->status) ?></span></td>
        </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><div class="kontor-empty"><i class="fa fa-tasks"></i><h3>No projects yet</h3><p>Create the first delivery workspace.</p></div><?php endif; ?>
  </section>
</div>
