<?php

/** @var \Kontor\Workflow\Domain\WorkflowInstance $instance */
/** @var \Kontor\Workflow\Domain\WorkflowDefinition $definition */
/** @var \Kontor\Workflow\Domain\WorkflowTransition[] $transitions */
/** @var \Kontor\Workflow\Domain\HistoryEntry[] $history */
/** @var bool $canTransition */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>workflow/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>"><i class="fa fa-arrow-left"></i> Back to workflow</a><p class="kontor-eyebrow"><?= $e($definition->name) ?> · Runtime</p><h2><?= $e($instance->entityUid) ?></h2></header>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><div class="kontor-detailgrid"><div><span>Entity type</span><strong><?= $e($instance->entityType) ?></strong></div><div><span>Current state</span><strong><?= $e($instance->currentState) ?></strong></div><div><span>Started</span><strong><?= $e($instance->createdAt->format('Y-m-d H:i')) ?></strong></div><div><span>Updated</span><strong><?= $e($instance->updatedAt->format('Y-m-d H:i')) ?></strong></div></div>
    <?php if ($canTransition && $transitions !== []): ?><div class="pw-module-actions kontor-pagehead__actions"><?php foreach ($transitions as $transition): ?><form method="post" action="<?= $e($adminUrl) ?>workflow-instance-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="instance_uid" value="<?= $e($instance->uid->toString()) ?>"><input type="hidden" name="action_key" value="<?= $e($transition->actionKey) ?>"><button class="uk-button <?= $transition->requiresApproval ? 'uk-button-secondary kontor-button--ghost' : 'uk-button-primary' ?> kontor-button" type="submit"><?= $e(ucfirst(str_replace('_', ' ', $transition->actionKey))) ?><?= $transition->requiresApproval ? ' · approval' : '' ?></button></form><?php endforeach; ?></div><?php elseif ($transitions === []): ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>This is a terminal state.</p></div><?php endif; ?>
  </section>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Ledger</p><h3>History</h3></div></header><?php if ($history !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>When</th><th>Action</th><th>Transition</th><th>Actor</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?= $e($entry->occurredAt->format('Y-m-d H:i')) ?></td><td><strong><?= $e($entry->actionKey) ?></strong></td><td><?= $e($entry->fromState . ' → ' . $entry->toState) ?></td><td><?= $e($entry->actorUserId !== null ? (string) $entry->actorUserId : 'system') ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No completed transitions yet.</p></div><?php endif; ?></section>
</div>
