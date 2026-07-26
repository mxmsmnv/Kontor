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
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Extensibility · State machines</p><h2>Workflows</h2><p>Design reusable states and transitions, then run and approve real instances.</p></div>
    <?php if ($canManage): ?><div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>workflow/"><i class="fa fa-plus"></i> New workflow</a></div><?php endif; ?>
  </header>
  <?php if ($pendingApprovals !== []): ?><section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Inbox</p><h3>Pending approvals</h3></div></header><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Entity</th><th>Transition</th><th>Requested</th><th>Decision</th></tr></thead><tbody><?php foreach ($pendingApprovals as $request): $instance = $approvalInstances[$request->uid->toString()]; ?><tr><td><a href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($instance->uid->toString())) ?>"><?= $e($instance->entityUid) ?></a></td><td><strong><?= $e($request->actionKey) ?></strong> · <?= $e($request->fromState . ' → ' . $request->toState) ?></td><td><?= $e($request->createdAt->format('Y-m-d H:i')) ?></td><td><?php if ($canApprove): ?><div class="pw-module-actions kontor-pagehead__actions"><form method="post" action="<?= $e($adminUrl) ?>workflow-approval-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="request_uid" value="<?= $e($request->uid->toString()) ?>"><input type="hidden" name="action" value="approve"><button class="uk-button uk-button-primary kontor-button" type="submit">Approve</button></form><form method="post" action="<?= $e($adminUrl) ?>workflow-approval-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="request_uid" value="<?= $e($request->uid->toString()) ?>"><input type="hidden" name="action" value="reject"><input name="reason" aria-label="Rejection reason" placeholder="Reason" required><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Reject</button></form></div><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <?php if ($definitions !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Key</th><th>Workflow</th><th>Entity type</th><th>States</th><th>Status</th></tr></thead><tbody><?php foreach ($definitions as $definition): ?><tr><td><strong><?= $e($definition->workflowKey) ?></strong></td><td><a href="<?= $e($adminUrl) ?>workflow/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>"><?= $e($definition->name) ?></a></td><td><?= $e($definition->entityType) ?></td><td><?= $e(implode(' → ', $definition->states)) ?></td><td><span class="uk-label kontor-pill"><?= $e($definition->status) ?></span></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-sitemap"></i><h3>No workflow definitions</h3><p>Create a state machine and its first transition.</p></div><?php endif; ?>
  </section>
</div>
