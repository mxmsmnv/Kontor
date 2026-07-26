<?php

/** @var \Kontor\Workflow\Domain\WorkflowDefinition|null $definition */
/** @var array{workflowKey: string, entityType: string, name: string, initialState: string, states: string} $values */
/** @var string $error */
/** @var \Kontor\Workflow\Domain\WorkflowTransition[] $transitions */
/** @var \Kontor\Workflow\Domain\WorkflowInstance[] $instances */
/** @var bool $canManage */
/** @var bool $canTransition */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>workflows/"><i class="fa fa-arrow-left"></i> Back to workflows</a><p class="kontor-eyebrow">Workflow · Designer</p><h2><?= $e($definition?->name ?? 'Create workflow') ?></h2></header>
  <?php if ($error !== ''): ?><div class="kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <?php if ($definition === null): ?>
    <form class="kontor-card kontor-nativeform" method="post" action="./"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><label class="kontor-nativefield"><span>Workflow key *</span><input name="workflow_key" value="<?= $e($values['workflowKey']) ?>" required></label><label class="kontor-nativefield"><span>Entity type *</span><input name="entity_type" value="<?= $e($values['entityType']) ?>" placeholder="content_request" required></label><label class="kontor-nativefield kontor-nativefield--wide"><span>Name *</span><input name="name" value="<?= $e($values['name']) ?>" required></label><label class="kontor-nativefield kontor-nativefield--wide"><span>States *</span><input name="states" value="<?= $e($values['states']) ?>" required><small>Comma-separated identifiers.</small></label><label class="kontor-nativefield"><span>Initial state *</span><input name="initial_state" value="<?= $e($values['initialState']) ?>" required></label><div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_save" value="1">Create workflow</button></div></form>
  <?php else: ?>
    <section class="kontor-card"><div class="kontor-detailgrid"><div><span>Key</span><strong><?= $e($definition->workflowKey) ?></strong></div><div><span>Entity type</span><strong><?= $e($definition->entityType) ?></strong></div><div><span>Initial state</span><strong><?= $e($definition->initialState) ?></strong></div><div><span>States</span><strong><?= $e(implode(' · ', $definition->states)) ?></strong></div></div></section>
    <section class="kontor-card kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Visual editor</p><h3>Transitions</h3></div></header>
      <?php if ($canManage): ?><form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>workflow-transition/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield"><span>Action key *</span><input name="action_key" placeholder="submit" required></label><label class="kontor-nativefield"><span>From *</span><select name="from_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($state) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>To *</span><select name="to_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($state) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>Required permission</span><input name="required_permission" placeholder="kontor-workflow-transition"></label><label class="kontor-nativefield"><span><input name="requires_approval" type="checkbox" value="1"> Requires approval</span></label><div class="kontor-nativeform__actions"><button class="kontor-button kontor-button--ghost" type="submit">Add transition</button></div></form><?php endif; ?>
      <?php if ($transitions !== []): ?><table class="kontor-table"><thead><tr><th>Action</th><th>Path</th><th>Permission</th><th>Approval</th></tr></thead><tbody><?php foreach ($transitions as $transition): ?><tr><td><strong><?= $e($transition->actionKey) ?></strong></td><td><?= $e($transition->fromState . ' → ' . $transition->toState) ?></td><td><?= $e($transition->requiredPermission ?? '—') ?></td><td><?= $transition->requiresApproval ? 'Required' : 'No' ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>Add the first transition between declared states.</p></div><?php endif; ?>
    </section>
    <section class="kontor-card kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Runtime</p><h3>Instances</h3></div></header>
      <?php if ($canTransition): ?><form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>workflow-instance/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Entity UID *</span><input name="entity_uid" placeholder="request-001" required></label><div class="kontor-nativeform__actions"><button class="kontor-button" type="submit" name="submit_start" value="1">Start instance</button></div></form><?php endif; ?>
      <?php if ($instances !== []): ?><table class="kontor-table"><thead><tr><th>Entity</th><th>State</th><th>Updated</th></tr></thead><tbody><?php foreach ($instances as $instance): ?><tr><td><a href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($instance->uid->toString())) ?>"><?= $e($instance->entityUid) ?></a></td><td><span class="kontor-pill"><?= $e($instance->currentState) ?></span></td><td><?= $e($instance->updatedAt->format('Y-m-d H:i')) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>Start an instance to exercise this state machine.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
