<?php

/** @var \Kontor\Workflow\Domain\WorkflowDefinition[] $definitions */
/** @var \Kontor\Workflow\Domain\ApprovalRequest[] $pendingApprovals */
/** @var array<string, \Kontor\Workflow\Domain\WorkflowInstance> $approvalInstances */
/** @var bool $canManage */
/** @var bool $canApprove */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static fn (?string $value): string => $value === null || trim($value) === ''
    ? 'Not set'
    : ucfirst(str_replace(['_', '-', '.'], ' ', trim($value)));
$activeCount = count(array_filter(
    $definitions,
    static fn ($definition): bool => $definition->status === 'active'
));
$statusClass = static fn (string $status): string => match ($status) {
    'active' => ' uk-label-success',
    'draft' => ' uk-label-warning',
    default => '',
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Operations · Process control</p>
      <h2>Workflows</h2>
      <p>Define how work moves between business stages and review decisions that need approval.</p>
    </div>
    <?php if ($canManage): ?>
      <div class="pw-module-actions kontor-pagehead__actions">
        <a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>workflow/"><i class="fa fa-plus"></i> New workflow</a>
      </div>
    <?php endif; ?>
  </header>

  <div class="uk-grid-small uk-child-width-1-3 uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon uk-visible@s"><i class="fa fa-sitemap"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($definitions)) ?></strong><span class="kontor-stat__label">Defined workflows</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon uk-visible@s"><i class="fa fa-play-circle-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $activeCount) ?></strong><span class="kontor-stat__label">Active workflows</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon uk-visible@s<?= $pendingApprovals !== [] ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-check-square-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($pendingApprovals)) ?></strong><span class="kontor-stat__label">Awaiting approval</span></span></div></div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-3@m uk-grid-divider" uk-grid>
      <div><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Define the stages</strong><span class="uk-text-meta uk-display-block uk-margin-small-top">Describe the business states a record can move through.</span></span></div></div>
      <div><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Control transitions</strong><span class="uk-text-meta uk-display-block uk-margin-small-top">Choose which actions move work forward and where approval is required.</span></span></div></div>
      <div><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Run and review</strong><span class="uk-text-meta uk-display-block uk-margin-small-top">Use live instances to track progress and keep an auditable decision history.</span></span></div></div>
    </div>
  </section>

  <?php if ($pendingApprovals !== []): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Approval inbox</p>
      <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Decisions waiting for review</h3>
      <p class="uk-text-muted uk-margin-small-top">Review the requested move and add a clear reason when rejecting it.</p>
      <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@l uk-margin-medium-top" uk-grid>
        <?php foreach ($pendingApprovals as $request): ?>
          <?php $instance = $approvalInstances[$request->uid->toString()]; ?>
          <div>
            <article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid>
                <div class="uk-width-expand"><span class="uk-label">Pending</span><h4 class="uk-margin-small-top uk-margin-remove-bottom"><?= $e($humanize($instance->entityType)) ?></h4></div>
                <div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($instance->uid->toString())) ?>">Review <i class="fa fa-angle-right"></i></a></div>
              </div>
              <dl class="uk-description-list uk-margin">
                <dt>Requested change</dt><dd><strong><?= $e($humanize($request->fromState)) ?></strong> <i class="fa fa-long-arrow-right uk-margin-small-left uk-margin-small-right"></i> <strong><?= $e($humanize($request->toState)) ?></strong></dd>
                <dt>Action</dt><dd><?= $e($humanize($request->actionKey)) ?></dd>
                <dt>Requested</dt><dd><?= $e($request->createdAt->format('M j, Y · H:i')) ?></dd>
              </dl>
              <?php if ($canApprove): ?>
                <div class="uk-grid-small" uk-grid>
                  <div class="uk-width-auto@m"><form method="post" action="<?= $e($adminUrl) ?>workflow-approval-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="request_uid" value="<?= $e($request->uid->toString()) ?>"><input type="hidden" name="action" value="approve"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-check"></i> Approve</button></form></div>
                  <div class="uk-width-expand@m"><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>workflow-approval-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="request_uid" value="<?= $e($request->uid->toString()) ?>"><input type="hidden" name="action" value="reject"><label class="uk-form-label" for="rejection-reason-<?= $e($request->uid->toString()) ?>">Reason for rejection</label><div class="uk-grid-small uk-flex-bottom uk-margin-small-top" uk-grid><div class="uk-width-expand"><input class="uk-input" id="rejection-reason-<?= $e($request->uid->toString()) ?>" name="reason" placeholder="What needs to change before approval?" required><div class="uk-text-meta uk-margin-small-top">Required so the requester knows what to correct.</div></div><div class="uk-width-auto@m"><button class="uk-button uk-button-default" type="submit"><i class="fa fa-times"></i> Reject</button></div></div></form></div>
                </div>
              <?php else: ?>
                <div class="uk-alert uk-alert-primary uk-margin-remove-bottom" role="note">Waiting for an authorized approver.</div>
              <?php endif; ?>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section>
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid>
      <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Process library</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Available workflows</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Open a workflow to manage its transitions, approvals and live instances.</p></div>
      <div><span class="uk-label"><?= $e((string) count($definitions)) ?> total</span></div>
    </div>

    <?php if ($definitions !== []): ?>
      <div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@l" uk-grid>
        <?php foreach ($definitions as $definition): ?>
          <div>
            <article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid>
                <div class="uk-width-expand"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $e($humanize($definition->entityType)) ?> workflow</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($definition->name) ?></h3></div>
                <div><span class="uk-label<?= $statusClass($definition->status) ?>"><?= $e($humanize($definition->status)) ?></span></div>
              </div>
              <div class="uk-grid-small uk-child-width-1-2 uk-margin" uk-grid><div><div class="uk-text-meta">Starts at</div><strong><?= $e($humanize($definition->initialState)) ?></strong></div><div><div class="uk-text-meta">Stages</div><strong><?= $e((string) count($definition->states)) ?></strong></div></div>
              <div class="uk-text-meta uk-margin-small-bottom">Process stages</div>
              <ul class="uk-subnav uk-subnav-divider uk-flex-wrap uk-margin-remove-top"><?php foreach ($definition->states as $state): ?><li><span><?= $e($humanize($state)) ?></span></li><?php endforeach; ?></ul>
              <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-top" uk-grid><div><span class="uk-text-meta">Updated <?= $e($definition->updatedAt->format('M j, Y')) ?></span></div><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>workflow/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>">Open workflow <i class="fa fa-angle-right"></i></a></div></div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><i class="fa fa-sitemap fa-2x uk-text-muted"></i><h3>No workflows yet</h3><p class="uk-text-muted">Create a workflow to define a repeatable business process and its allowed decisions.</p><?php if ($canManage): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>workflow/"><i class="fa fa-plus"></i> Create first workflow</a><?php endif; ?></div></div>
    <?php endif; ?>
  </section>
</div>
