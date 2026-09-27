<?php

/** @var \Kontor\Projects\Domain\Project|null $project */
/** @var array{code: string, name: string, customer: string, hourlyRate: string, currencyCode: string} $values */
/** @var string $error */
/** @var array<string, string> $customers */
/** @var string $customerLabel */
/** @var string|null $customerRoute */
/** @var \Kontor\Projects\Domain\ProjectMilestone[] $milestones */
/** @var \Kontor\Projects\Domain\TimeEntry[] $timeEntries */
/** @var \Kontor\Projects\Domain\BillableItem[] $billableItems */
/** @var bool $canManageMilestones */
/** @var bool $canTrackTime */
/** @var bool $canManageBillable */
/** @var bool $canGenerateInvoice */
/** @var bool $canViewTasks */
/** @var bool $canViewInvoices */
/** @var bool $canViewContacts */
/** @var bool $canViewCompanies */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$milestoneLabels = [];
$completedMilestones = 0;
$overdueMilestones = 0;
$nextMilestone = null;
$today = new DateTimeImmutable('today');
foreach ($milestones as $milestone) {
    $milestoneLabels[$milestone->uid->toString()] = $milestone->name;
    if ($milestone->isCompleted()) {
        ++$completedMilestones;
        continue;
    }
    if ($nextMilestone === null) {
        $nextMilestone = $milestone;
    }
    if ($milestone->dueDate !== null && $milestone->dueDate < $today) {
        ++$overdueMilestones;
    }
}
$progress = $milestones !== [] ? (int) round(($completedMilestones / count($milestones)) * 100) : 0;
$trackedMinutes = 0;
$unbilledMinutes = 0;
$runningTimers = 0;
$unbilledByCurrency = [];
if ($project !== null) {
    foreach ($timeEntries as $entry) {
        $trackedMinutes += $entry->durationMinutes ?? 0;
        if ($entry->isRunning()) {
            ++$runningTimers;
        }
        if (!$entry->billable || $entry->isInvoiced() || $entry->durationMinutes === null) {
            continue;
        }
        $unbilledMinutes += $entry->durationMinutes;
        $rate = $entry->hourlyRateMinor ?? $project->defaultHourlyRateMinor;
        $currency = $entry->currencyCode ?? $project->currencyCode;
        if ($rate !== null && $currency !== null) {
            $unbilledByCurrency[$currency] = ($unbilledByCurrency[$currency] ?? 0)
                + (int) round(($entry->durationMinutes / 60) * $rate);
        }
    }
    foreach ($billableItems as $item) {
        if ($item->isInvoiced()) {
            continue;
        }
        $total = $item->total();
        $currency = $total->currencyCode();
        $unbilledByCurrency[$currency] = ($unbilledByCurrency[$currency] ?? 0) + $total->amountMinor();
    }
}
ksort($unbilledByCurrency);
$hours = static fn (int $minutes): string => $minutes === 0
    ? '0 h'
    : number_format($minutes / 60, $minutes % 60 === 0 ? 0 : 1, '.', ',') . ' h';
$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$moneySummary = static function (array $amounts): string {
    if ($amounts === []) {
        return '—';
    }
    $currency = array_key_first($amounts);
    $summary = number_format($amounts[$currency] / 100, 2, '.', ',') . ' ' . $currency;

    return count($amounts) > 1 ? $summary . ' +' . (count($amounts) - 1) : $summary;
};
$statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$invoiceReady = $unbilledByCurrency !== [];
$projectUid = $project?->uid->toString() ?? '';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>projects/"><i class="fa fa-arrow-left"></i> Back to projects</a>
    <p class="kontor-eyebrow">Projects · Strategy & delivery</p>
    <h2><?= $e($project?->name ?? 'Create project') ?></h2>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-warning" uk-alert><p><strong><?= $e($error) ?></strong></p></div><?php endif; ?>

  <?php if ($project === null): ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <form class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-form-stacked" method="post" action="./">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Project foundation</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Create a delivery strategy</h3><p class="uk-text-muted uk-margin-small-top">Start with the customer outcome and commercial agreement. Detailed planning comes next.</p></div><div><span class="uk-label"><?= $e((string) count($customers)) ?> customer<?= count($customers) === 1 ? '' : 's' ?> available</span></div></div>

          <fieldset class="uk-fieldset uk-margin-medium-top">
            <legend class="uk-legend">1. Define the outcome</legend>
            <p class="uk-text-muted uk-margin-small-top">Use language the customer and delivery team will both understand.</p>
            <div class="uk-grid-small" uk-grid>
              <label class="kontor-nativefield uk-width-1-1 uk-width-2-3@m"><span>Project name *</span><input name="name" value="<?= $e($values['name']) ?>" maxlength="191" placeholder="Modernize the customer onboarding process" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">The outcome teammates and the customer will recognize throughout delivery.</span><span class="kontor-field-note"><strong>Note:</strong> Describe the result, not the internal activity.</span></span></label>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-3@m"><span>Project code *</span><input name="code" value="<?= $e($values['code']) ?>" maxlength="50" pattern="[A-Za-z0-9_-]{1,50}" placeholder="ONBOARDING-2026" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">A stable short reference used in lists, exports and integrations.</span><span class="kontor-field-note"><strong>Note:</strong> Letters, numbers, hyphens and underscores only.</span></span></label>
            </div>
          </fieldset>

          <hr class="uk-margin-medium">
          <fieldset class="uk-fieldset">
            <legend class="uk-legend">2. Connect the customer</legend>
            <p class="uk-text-muted uk-margin-small-top">The project will inherit its customer context for delivery and future invoicing.</p>
            <?php if ($customers !== []): ?><label class="kontor-nativefield"><span>Customer *</span><select name="customer" aria-label="Customer" required><option value="">Select customer</option><?php foreach ($customers as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['customer'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><span class="kontor-field-guidance"><span class="kontor-field-description">The contact or company that owns the commercial relationship.</span><span class="kontor-field-note"><strong>Note:</strong> Confirm the correct record before creating delivery and billing history.</span></span></label><?php else: ?><div class="uk-alert-warning" uk-alert><p><strong>A customer is required before a project can begin.</strong><br>Create a contact or company, then return here to connect the project.</p></div><?php endif; ?>
          </fieldset>

          <hr class="uk-margin-medium">
          <fieldset class="uk-fieldset">
            <legend class="uk-legend">3. Set commercial defaults</legend>
            <p class="uk-text-muted uk-margin-small-top">These values price tracked time and keep future draft invoices consistent.</p>
            <div class="uk-grid-small" uk-grid>
              <label class="kontor-nativefield uk-width-1-1 uk-width-2-3@s"><span>Default hourly rate *</span><div class="uk-inline uk-width-1-1"><span class="uk-form-icon"><i class="fa fa-money"></i></span><input name="hourly_rate" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= $e($values['hourlyRate']) ?>" placeholder="125.00" required></div><span class="kontor-field-guidance"><span class="kontor-field-description">The default price applied to billable time unless an entry overrides it.</span><span class="kontor-field-note"><strong>Note:</strong> Enter the amount without a currency symbol.</span></span></label>
              <label class="kontor-nativefield uk-width-1-1 uk-width-1-3@s"><span>Currency *</span><input name="currency_code" value="<?= $e($values['currencyCode']) ?>" maxlength="3" pattern="[A-Za-z]{3}" placeholder="EUR" autocomplete="off" required><span class="kontor-field-guidance"><span class="kontor-field-description">The currency used for project time, charges and draft invoices.</span><span class="kontor-field-note"><strong>Note:</strong> Use a three-letter code such as EUR or USD.</span></span></label>
            </div>
          </fieldset>

          <div class="uk-alert-primary uk-margin-medium-top" uk-alert><p><i class="fa fa-info-circle"></i> The project starts as <strong>Active</strong>. After creation, add dates and milestones before recording delivery.</p></div>
          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>projects/"><i class="fa fa-arrow-left"></i> Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"<?= $customers === [] ? ' disabled' : '' ?>>Create project <i class="fa fa-angle-right"></i></button></div></div>
        </form>
      </div>
      <div class="uk-width-1-1 uk-width-1-3@l">
        <aside class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">What happens next</p><h3 class="uk-card-title uk-margin-small-top">From strategy to delivery</h3><p class="uk-text-muted">Creation establishes the project shell. No invoice is created and nothing is sent to the customer.</p><ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Plan milestones</strong><br><span class="uk-text-meta">Turn the desired outcome into reviewable stages and dates.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Capture delivery</strong><br><span class="uk-text-meta">Record billable time and customer-approved charges.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Review billing</strong><br><span class="uk-text-meta">Prepare a draft invoice only when the work is ready.</span></span></div></li></ol></aside>
        <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Customer records</p><h3 class="uk-card-title uk-margin-small-top"><?= $customers !== [] ? 'Need another customer?' : 'Create the first customer' ?></h3><p class="uk-text-muted"><?= $customers !== [] ? 'Create or review the commercial party before starting the project when the right record is not listed.' : 'Projects require a contact or company so delivery and invoicing stay connected.' ?></p><div class="uk-grid-small uk-child-width-1-1" uk-grid><?php if ($canViewCompanies): ?><div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>companies/"><i class="fa fa-building-o"></i> Companies</a></div><?php endif; ?><?php if ($canViewContacts): ?><div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>contacts/"><i class="fa fa-address-book-o"></i> Contacts</a></div><?php endif; ?></div></aside>
      </div>
    </div>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m"><div class="uk-flex uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><span class="uk-label uk-label-success"><?= $e($statusLabel($project->status)) ?></span></div><div><span class="uk-text-meta"><?= $e($project->code) ?></span></div></div><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Deliver the agreed outcome</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Keep the customer promise, delivery evidence and billable work together from plan to invoice.</p></div>
        <div class="pw-module-actions kontor-pagehead__actions"><?php if ($canViewTasks): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-check-square-o"></i> Tasks</a><?php endif; ?><?php if ($canViewInvoices): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>invoices/"><i class="fa fa-file-text-o"></i> Invoices</a><?php endif; ?></div>
      </div>
      <hr>
      <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s" uk-grid>
        <div><span class="uk-text-meta">Customer</span><div class="uk-margin-small-top"><?php if ($customerRoute !== null): ?><a class="uk-button uk-button-text uk-link-reset" href="<?= $e($adminUrl . $customerRoute) ?>"><strong><?= $e($customerLabel) ?></strong> <i class="fa fa-angle-right"></i></a><?php else: ?><strong><?= $e($customerLabel) ?></strong><?php endif; ?></div></div>
        <div><span class="uk-text-meta">Commercial basis</span><div class="uk-margin-small-top"><strong><?= $project->defaultHourlyRateMinor !== null ? $e(number_format($project->defaultHourlyRateMinor / 100, 2, '.', ',') . ' ' . ($project->currencyCode ?? '')) . ' / hour' : 'Rate not set' ?></strong></div></div>
        <div><span class="uk-text-meta">Schedule</span><div class="uk-margin-small-top"><strong><?= $project->startDate !== null || $project->endDate !== null ? $e(($project->startDate?->format('M j, Y') ?? 'Start not set') . ' – ' . ($project->endDate?->format('M j, Y') ?? 'Ongoing')) : 'Dates not planned' ?></strong></div></div>
      </div>
    </section>

    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $progress === 100 ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-flag-checkered"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $progress) ?>%</strong><span class="kontor-stat__label"><?= $e((string) $completedMilestones) ?>/<?= $e((string) count($milestones)) ?> milestones complete</span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $overdueMilestones > 0 ? ' kontor-stat__icon--danger' : '' ?>"><i class="fa fa-calendar"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $overdueMilestones) ?></strong><span class="kontor-stat__label"><?= $overdueMilestones > 0 ? 'Overdue milestones' : ($nextMilestone !== null ? 'Next: ' . $e($nextMilestone->name) : 'Plan is on track') ?></span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-clock-o"></i></span><span><strong class="kontor-stat__value"><?= $e($hours($trackedMinutes)) ?></strong><span class="kontor-stat__label"><?= $unbilledMinutes > 0 ? $e($hours($unbilledMinutes)) . ' ready to bill' : 'Tracked delivery time' ?><?= $runningTimers > 0 ? ' · timer running' : '' ?></span></span></div></div>
      <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $invoiceReady ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($moneySummary($unbilledByCurrency)) ?></strong><span class="kontor-stat__label"><?= $invoiceReady ? 'Ready for invoice review' : 'Nothing waiting to bill' ?></span></span></div></div>
    </div>

    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body" id="project-plan">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">1 · Strategic plan</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Milestones</h3><p class="uk-text-muted uk-margin-small-top">Define reviewable outcomes, owners and dates before work begins.</p></div><div><span class="uk-label"><?= $e((string) $completedMilestones) ?>/<?= $e((string) count($milestones)) ?> complete</span></div></div>
          <?php if ($milestones !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($milestones as $milestone): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><i class="fa fa-<?= $milestone->isCompleted() ? 'check-circle uk-text-success' : 'circle-o' ?> uk-margin-small-right"></i><?= $e($milestone->name) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $milestone->dueDate !== null ? 'Due ' . $e($milestone->dueDate->format('M j, Y')) : 'No due date' ?><?= !$milestone->isCompleted() && $milestone->dueDate !== null && $milestone->dueDate < $today ? ' · Overdue' : '' ?></div></div><div class="uk-flex uk-flex-middle uk-grid-small" uk-grid><div><span class="uk-label<?= $milestone->isCompleted() ? ' uk-label-success' : '' ?>"><?= $milestone->isCompleted() ? 'Complete' : 'Pending' ?></span></div><?php if ($canManageMilestones): ?><div><form method="post" action="<?= $e($adminUrl) ?>project-milestone-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($projectUid) ?>"><input type="hidden" name="milestone_uid" value="<?= $e($milestone->uid->toString()) ?>"><input type="hidden" name="action" value="<?= $milestone->isCompleted() ? 'reopen' : 'complete' ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><?= $milestone->isCompleted() ? 'Reopen' : 'Complete' ?></button></form></div><?php endif; ?></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-flag-checkered fa-2x uk-text-muted"></i><h4>No milestones yet</h4><p class="uk-text-muted">Break the desired outcome into reviewable delivery stages.</p></div><?php endif; ?>
          <?php if ($canManageMilestones): ?><ul class="uk-margin-medium-top" uk-accordion><li><a class="uk-accordion-title uk-link-reset" href>Add a milestone</a><div class="uk-accordion-content"><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-milestone/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($projectUid) ?>"><label class="kontor-nativefield"><span>Outcome *</span><input name="name" placeholder="Customer can review the delivered workflow" required></label><label class="kontor-nativefield"><span>Due date</span><input name="due_date" type="date"></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary" type="submit">Add milestone</button></div></form></div></li></ul><?php endif; ?>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top" id="project-time">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">2 · Delivery evidence</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Time entries</h3><p class="uk-text-muted uk-margin-small-top">Capture the work behind each milestone so progress and billing remain explainable.</p></div><div><strong><?= $e($hours($trackedMinutes)) ?></strong><div class="uk-text-meta">tracked</div></div></div>
          <?php if ($timeEntries !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($timeEntries as $entry): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($entry->description ?? 'Project work') ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($milestoneLabels[$entry->milestoneUid ?? ''] ?? 'General project work') ?></div></div><div class="uk-text-right@m"><strong><?= $e($hours($entry->durationMinutes ?? 0)) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $entry->isInvoiced() ? ' uk-label-success' : '' ?>"><?= $entry->isInvoiced() ? 'Invoiced' : 'Unbilled' ?></span></div></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><p class="uk-text-muted">No delivery time has been recorded.</p></div><?php endif; ?>
          <?php if ($canTrackTime): ?><ul class="uk-margin-medium-top" uk-accordion><li><a class="uk-accordion-title uk-link-reset" href>Log billable time</a><div class="uk-accordion-content"><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-time/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($projectUid) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Work performed *</span><input name="description" placeholder="What was delivered or advanced?" required></label><label class="kontor-nativefield"><span>Duration in minutes *</span><input name="minutes" type="number" min="1" max="14400" value="60" required></label><label class="kontor-nativefield"><span>Milestone</span><select name="milestone_uid"><option value="">General project work</option><?php foreach ($milestones as $milestone): ?><option value="<?= $e($milestone->uid->toString()) ?>"><?= $e($milestone->name) ?></option><?php endforeach; ?></select></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary" type="submit">Log time</button></div></form></div></li></ul><?php endif; ?>
        </section>

        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top" id="project-charges">
          <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">3 · Commercial additions</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Billable items</h3><p class="uk-text-muted uk-margin-small-top">Record fixed fees, materials and other customer-approved charges outside tracked time.</p></div><div><strong><?= $e((string) count($billableItems)) ?></strong><div class="uk-text-meta">items</div></div></div>
          <?php if ($billableItems !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($billableItems as $item): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($item->description) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e((string) $item->quantity) ?> × <?= $e($money($item->unitPrice)) ?><?= isset($milestoneLabels[$item->milestoneUid ?? '']) ? ' · ' . $e($milestoneLabels[$item->milestoneUid]) : '' ?></div></div><div class="uk-text-right@m"><strong><?= $e($money($item->total())) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $item->isInvoiced() ? ' uk-label-success' : '' ?>"><?= $item->isInvoiced() ? 'Invoiced' : 'Unbilled' ?></span></div></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><p class="uk-text-muted">No additional charges have been recorded.</p></div><?php endif; ?>
          <?php if ($canManageBillable): ?><ul class="uk-margin-medium-top" uk-accordion><li><a class="uk-accordion-title uk-link-reset" href>Add a billable item</a><div class="uk-accordion-content"><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>project-billable-item/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($projectUid) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Charge description *</span><input name="description" placeholder="Approved workshop or delivery package" required></label><label class="kontor-nativefield"><span>Quantity *</span><input name="quantity" type="number" min="0.01" step="0.01" value="1" required></label><label class="kontor-nativefield"><span>Unit price in <?= $e($project->currencyCode ?? 'project currency') ?> *</span><input name="unit_price" type="number" min="0" step="0.01" required></label><label class="kontor-nativefield"><span>Milestone</span><select name="milestone_uid"><option value="">General project charge</option><?php foreach ($milestones as $milestone): ?><option value="<?= $e($milestone->uid->toString()) ?>"><?= $e($milestone->name) ?></option><?php endforeach; ?></select></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary" type="submit">Add charge</button></div></form></div></li></ul><?php endif; ?>
        </section>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <aside class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Project strategy</p><h3 class="uk-card-title uk-margin-small-top">The next decision</h3>
          <?php if ($overdueMilestones > 0): ?><div class="uk-alert-danger" uk-alert><p><strong>Recover the delivery plan.</strong><br><?= $e((string) $overdueMilestones) ?> milestone<?= $overdueMilestones === 1 ? ' is' : 's are' ?> overdue. Review scope and dates before adding more work.</p></div>
          <?php elseif ($nextMilestone !== null): ?><div class="uk-alert-primary" uk-alert><p><strong>Advance the next outcome.</strong><br><?= $e($nextMilestone->name) ?><?= $nextMilestone->dueDate !== null ? ' is due ' . $e($nextMilestone->dueDate->format('M j, Y')) : ' has no due date yet' ?>.</p></div>
          <?php elseif ($invoiceReady): ?><div class="uk-alert-success" uk-alert><p><strong>Delivery is ready for billing review.</strong><br>All milestones are complete and <?= $e($moneySummary($unbilledByCurrency)) ?> is waiting to be prepared.</p></div>
          <?php else: ?><div class="uk-alert-primary" uk-alert><p><strong>Define the next outcome.</strong><br>Add a milestone or new billable work to continue this project.</p></div><?php endif; ?>
          <ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Agree the outcome</strong><br><span class="uk-text-meta">Milestones make the strategy reviewable.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Prove delivery</strong><br><span class="uk-text-meta">Time and charges explain the work performed.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Review before billing</strong><br><span class="uk-text-meta">Draft invoices keep the commercial decision reversible.</span></span></div></li></ol>
          <?php if ($canGenerateInvoice && $invoiceReady): ?><form class="uk-margin-top" method="post" action="<?= $e($adminUrl) ?>project-invoice/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="project_uid" value="<?= $e($projectUid) ?>"><button class="uk-button uk-button-primary uk-width-1-1" type="submit"><i class="fa fa-file-text-o"></i> Prepare draft invoice</button></form><?php elseif (!$invoiceReady): ?><button class="uk-button uk-button-default uk-width-1-1 uk-margin-top" type="button" disabled>Nothing ready to invoice</button><?php endif; ?>
          <p class="uk-text-meta uk-margin-small-top">A draft remains editable and is not sent to the customer automatically.</p>
        </aside>

        <aside class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Quick navigation</p><h3 class="uk-card-title uk-margin-small-top">Work areas</h3>
          <ul class="uk-nav uk-nav-default uk-link-reset"><li><a href="#project-plan"><i class="fa fa-flag-checkered uk-margin-small-right"></i> Strategic plan</a></li><li><a href="#project-time"><i class="fa fa-clock-o uk-margin-small-right"></i> Delivery time</a></li><li><a href="#project-charges"><i class="fa fa-money uk-margin-small-right"></i> Additional charges</a></li></ul>
        </aside>
      </div>
    </div>
  <?php endif; ?>
</div>
