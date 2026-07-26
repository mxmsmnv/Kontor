<?php

/** @var \Kontor\Projects\Domain\Project|null $project */
/** @var array{code: string, name: string, customer: string, hourlyRate: string, currencyCode: string} $values */
/** @var string $error */
/** @var array<string, string> $customers */
/** @var string $customerLabel */
/** @var \Kontor\Projects\Domain\ProjectMilestone[] $milestones */
/** @var \Kontor\Projects\Domain\TimeEntry[] $timeEntries */
/** @var \Kontor\Projects\Domain\BillableItem[] $billableItems */
/** @var bool $canManageMilestones */
/** @var bool $canTrackTime */
/** @var bool $canManageBillable */
/** @var bool $canGenerateInvoice */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$milestoneLabels = [];
foreach ($milestones as $milestone) {
    $milestoneLabels[$milestone->uid->toString()] = $milestone->name;
}
?>
<div class="kontor-shell">
  <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>projects/"><i class="fa fa-arrow-left"></i> Back to projects</a><p class="kontor-eyebrow">Projects · Delivery</p><h2><?= $e($project?->name ?? 'Create project') ?></h2></header>
  <?php if ($error !== ''): ?><div class="kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <?php if ($project === null): ?>
    <form class="kontor-card kontor-nativeform" method="post" action="./">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Code *</span><input name="code" value="<?= $e($values['code']) ?>" maxlength="50" required></label>
      <label class="kontor-nativefield"><span>Name *</span><input name="name" value="<?= $e($values['name']) ?>" required></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Customer *</span><select name="customer" aria-label="Customer" required><option value="">Select customer</option><?php foreach ($customers as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['customer'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
      <label class="kontor-nativefield"><span>Hourly rate *</span><input name="hourly_rate" type="number" min="0.01" step="0.01" value="<?= $e($values['hourlyRate']) ?>" required></label>
      <label class="kontor-nativefield"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" required></label>
      <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_save" value="1">Create project</button><a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>projects/">Cancel</a></div>
    </form>
  <?php else: ?>
    <section class="kontor-card">
      <div class="kontor-detailgrid">
        <div><span>Code</span><strong><?= $e($project->code) ?></strong></div>
        <div><span>Customer</span><strong><?= $e($customerLabel) ?></strong></div>
        <div><span>Hourly rate</span><strong><?= $project->defaultHourlyRateMinor !== null ? $e(number_format($project->defaultHourlyRateMinor / 100, 2, '.', '') . ' ' . ($project->currencyCode ?? '')) : '—' ?></strong></div>
        <div><span>Status</span><strong><?= $e($project->status) ?></strong></div>
      </div>
      <?php if ($canGenerateInvoice): ?><div class="kontor-pagehead__actions"><form method="post" action="<?= $e($adminUrl) ?>project-invoice/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($project->uid->toString()) ?>"><button class="kontor-button" type="submit"><i class="fa fa-file-text"></i> Generate draft invoice</button></form></div><?php endif; ?>
    </section>

    <section class="kontor-card kontor-tablewrap">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Plan</p><h3>Milestones</h3></div></header>
      <?php if ($canManageMilestones): ?><form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-milestone/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($project->uid->toString()) ?>"><label class="kontor-nativefield"><span>Name *</span><input name="name" required></label><label class="kontor-nativefield"><span>Due date</span><input name="due_date" type="date"></label><div class="kontor-nativeform__actions"><button class="kontor-button kontor-button--ghost" type="submit">Add milestone</button></div></form><?php endif; ?>
      <?php if ($milestones !== []): ?><table class="kontor-table"><thead><tr><th>Milestone</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($milestones as $milestone): ?><tr><td><strong><?= $e($milestone->name) ?></strong></td><td><?= $e($milestone->dueDate?->format('Y-m-d') ?? '—') ?></td><td><?= $e($milestone->status) ?></td><td><?php if ($canManageMilestones): ?><form method="post" action="<?= $e($adminUrl) ?>project-milestone-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($project->uid->toString()) ?>"><input type="hidden" name="milestone_uid" value="<?= $e($milestone->uid->toString()) ?>"><input type="hidden" name="action" value="<?= $milestone->isCompleted() ? 'reopen' : 'complete' ?>"><button class="kontor-button kontor-button--ghost" type="submit"><?= $milestone->isCompleted() ? 'Reopen' : 'Complete' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>No milestones yet.</p></div><?php endif; ?>
    </section>

    <section class="kontor-card kontor-tablewrap">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Work</p><h3>Time entries</h3></div></header>
      <?php if ($canTrackTime): ?><form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-time/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($project->uid->toString()) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Description *</span><input name="description" required></label><label class="kontor-nativefield"><span>Minutes *</span><input name="minutes" type="number" min="1" max="14400" value="60" required></label><label class="kontor-nativefield"><span>Milestone</span><select name="milestone_uid"><option value="">No milestone</option><?php foreach ($milestones as $milestone): ?><option value="<?= $e($milestone->uid->toString()) ?>"><?= $e($milestone->name) ?></option><?php endforeach; ?></select></label><div class="kontor-nativeform__actions"><button class="kontor-button kontor-button--ghost" type="submit">Log billable time</button></div></form><?php endif; ?>
      <?php if ($timeEntries !== []): ?><table class="kontor-table"><thead><tr><th>Description</th><th>Milestone</th><th>Duration</th><th>Billing</th></tr></thead><tbody><?php foreach ($timeEntries as $entry): ?><tr><td><?= $e($entry->description ?? 'Time') ?></td><td><?= $e($milestoneLabels[$entry->milestoneUid ?? ''] ?? '—') ?></td><td><?= $e((string) ($entry->durationMinutes ?? 0)) ?> min</td><td><span class="kontor-pill<?= $entry->isInvoiced() ? '' : ' kontor-pill--inactive' ?>"><?= $entry->isInvoiced() ? 'invoiced' : 'unbilled' ?></span></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>No time logged yet.</p></div><?php endif; ?>
    </section>

    <section class="kontor-card kontor-tablewrap">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Charges</p><h3>Billable items</h3></div></header>
      <?php if ($canManageBillable): ?><form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-billable-item/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($project->uid->toString()) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Description *</span><input name="description" required></label><label class="kontor-nativefield"><span>Quantity *</span><input name="quantity" type="number" min="0.01" step="0.01" value="1" required></label><label class="kontor-nativefield"><span>Unit price *</span><input name="unit_price" type="number" min="0" step="0.01" required></label><label class="kontor-nativefield"><span>Milestone</span><select name="milestone_uid"><option value="">No milestone</option><?php foreach ($milestones as $milestone): ?><option value="<?= $e($milestone->uid->toString()) ?>"><?= $e($milestone->name) ?></option><?php endforeach; ?></select></label><div class="kontor-nativeform__actions"><button class="kontor-button kontor-button--ghost" type="submit">Add billable item</button></div></form><?php endif; ?>
      <?php if ($billableItems !== []): ?><table class="kontor-table"><thead><tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Total</th><th>Billing</th></tr></thead><tbody><?php foreach ($billableItems as $item): ?><tr><td><?= $e($item->description) ?></td><td><?= $e((string) $item->quantity) ?></td><td><?= $e(number_format($item->unitPrice->amountMinor() / 100, 2, '.', '') . ' ' . $item->unitPrice->currencyCode()) ?></td><td><?= $e(number_format($item->total()->amountMinor() / 100, 2, '.', '') . ' ' . $item->total()->currencyCode()) ?></td><td><span class="kontor-pill<?= $item->isInvoiced() ? '' : ' kontor-pill--inactive' ?>"><?= $item->isInvoiced() ? 'invoiced' : 'unbilled' ?></span></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>No billable items yet.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
