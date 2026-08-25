<?php

/** @var \Kontor\Workflow\Domain\WorkflowDefinition|null $definition */
/** @var array{workflowKey: string, entityType: string, entityTypeChoice: string, customEntityType: string, name: string, initialState: string, states: string} $values */
/** @var string $error */
/** @var \Kontor\Workflow\Domain\WorkflowTransition[] $transitions */
/** @var \Kontor\Workflow\Domain\WorkflowInstance[] $instances */
/** @var bool $canManage */
/** @var bool $canTransition */
/** @var array<string, string> $entityTypeOptions */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow"><?= $definition === null ? 'New business process' : 'Workflow designer' ?></p><h2><?= $e($definition?->name ?? 'Create workflow') ?></h2><p><?= $definition === null ? 'Describe the records and stages this process controls. Transitions and approvals are added after creation.' : 'Manage the stages, allowed actions and live work controlled by this process.' ?></p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>workflows/"><i class="fa fa-arrow-left"></i> Workflows</a></div>
  </header>
  <?php if ($error !== ''): ?><div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Workflow could not be created.</strong> <?= $e($error) ?></p></div><?php endif; ?>
  <?php if ($definition === null): ?>
    <form class="uk-form-stacked" method="post" action="./">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <div class="uk-grid-medium" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@l">
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Business purpose</p>
            <h3 class="uk-card-title uk-margin-small-top">What should this workflow control?</h3>
            <p class="uk-text-muted">Use a recognizable name and connect the workflow to the kind of record that will move through it.</p>
            <div class="uk-margin">
              <label class="uk-form-label" for="workflow-name">Workflow name <span class="uk-text-danger">*</span></label>
              <input class="uk-input uk-margin-small-top" id="workflow-name" name="name" value="<?= $e($values['name']) ?>" placeholder="For example: Customer onboarding approval" required autofocus>
              <div class="uk-text-meta uk-margin-small-top">Required. Name the business outcome or process teammates will recognize.</div>
            </div>
            <div class="uk-margin">
              <label class="uk-form-label" for="workflow-entity-type">Business record <span class="uk-text-danger">*</span></label>
              <select class="uk-select uk-margin-small-top" id="workflow-entity-type" name="entity_type" required><?php foreach ($entityTypeOptions as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['entityTypeChoice'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
              <div class="uk-text-meta uk-margin-small-top">Only records from installed Kontor components are offered here.</div>
            </div>
            <ul class="uk-accordion uk-margin" uk-accordion>
              <li<?= $values['customEntityType'] !== '' ? ' class="uk-open"' : '' ?>>
                <a class="uk-accordion-title uk-link-reset" href>Advanced integration</a>
                <div class="uk-accordion-content"><label class="uk-form-label" for="workflow-custom-entity-type">Custom record type</label><input class="uk-input uk-margin-small-top" id="workflow-custom-entity-type" name="custom_entity_type" value="<?= $e($values['customEntityType']) ?>" placeholder="For example: content request"><div class="uk-text-meta uk-margin-small-top">Use only with a custom integration. Select “Other business record” above and enter its record type here.</div></div>
              </li>
            </ul>
            <div class="uk-alert uk-alert-primary uk-margin-remove-bottom" role="note"><div class="uk-flex uk-flex-top"><i class="fa fa-magic uk-margin-small-right uk-margin-small-top"></i><span><strong>No technical key required.</strong><span class="uk-display-block uk-text-small uk-margin-small-top">Kontor generates the internal workflow key from the name.</span></span></div></div>
          </section>
        </div>

        <div class="uk-width-1-1 uk-width-1-2@l">
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Process stages</p>
            <h3 class="uk-card-title uk-margin-small-top">How does the work progress?</h3>
            <p class="uk-text-muted">List the meaningful business stages in the order teammates usually encounter them.</p>
            <div class="uk-margin">
              <label class="uk-form-label" for="workflow-states">Stages <span class="uk-text-danger">*</span></label>
              <textarea class="uk-textarea uk-margin-small-top" id="workflow-states" name="states" rows="8" placeholder="Draft&#10;In review&#10;Approved&#10;Rejected" required><?= $e($values['states']) ?></textarea>
              <div class="uk-text-meta uk-margin-small-top">Required. Enter one stage per line. Plain-language names such as “In review” are accepted.</div>
            </div>
            <div class="uk-margin-remove-bottom">
              <label class="uk-form-label" for="workflow-initial-state">Starting stage <span class="uk-text-danger">*</span></label>
              <input class="uk-input uk-margin-small-top" id="workflow-initial-state" name="initial_state" value="<?= $e($values['initialState']) ?>" placeholder="Draft" required>
              <div class="uk-text-meta uk-margin-small-top">Required. This must match one of the stages above and is assigned when a process starts.</div>
            </div>
          </section>
        </div>
      </div>

      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
          <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next step</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Create the process definition</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">After creation, add the actions that move records between stages and decide which transitions require approval.</p></div>
          <div class="uk-width-auto@m"><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>workflows/">Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"><i class="fa fa-plus"></i> Create workflow</button></div></div></div>
        </div>
      </section>
    </form>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><div class="kontor-detailgrid"><div><span>Key</span><strong><?= $e($definition->workflowKey) ?></strong></div><div><span>Entity type</span><strong><?= $e($definition->entityType) ?></strong></div><div><span>Initial state</span><strong><?= $e($definition->initialState) ?></strong></div><div><span>States</span><strong><?= $e(implode(' · ', $definition->states)) ?></strong></div></div></section>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Visual editor</p><h3>Transitions</h3></div></header>
      <?php if ($canManage): ?><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>workflow-transition/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield"><span>Action key *</span><input name="action_key" placeholder="submit" required></label><label class="kontor-nativefield"><span>From *</span><select name="from_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($state) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>To *</span><select name="to_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($state) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>Required permission</span><input name="required_permission" placeholder="kontor-workflow-transition"></label><label class="kontor-nativefield"><span><input name="requires_approval" type="checkbox" value="1"> Requires approval</span></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Add transition</button></div></form><?php endif; ?>
      <?php if ($transitions !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Action</th><th>Path</th><th>Permission</th><th>Approval</th></tr></thead><tbody><?php foreach ($transitions as $transition): ?><tr><td><strong><?= $e($transition->actionKey) ?></strong></td><td><?= $e($transition->fromState . ' → ' . $transition->toState) ?></td><td><?= $e($transition->requiredPermission ?? '—') ?></td><td><?= $transition->requiresApproval ? 'Required' : 'No' ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>Add the first transition between declared states.</p></div><?php endif; ?>
    </section>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Runtime</p><h3>Instances</h3></div></header>
      <?php if ($canTransition): ?><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>workflow-instance/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Entity UID *</span><input name="entity_uid" placeholder="request-001" required></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit" name="submit_start" value="1">Start instance</button></div></form><?php endif; ?>
      <?php if ($instances !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Entity</th><th>State</th><th>Updated</th></tr></thead><tbody><?php foreach ($instances as $instance): ?><tr><td><a href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($instance->uid->toString())) ?>"><?= $e($instance->entityUid) ?></a></td><td><span class="uk-label kontor-pill"><?= $e($instance->currentState) ?></span></td><td><?= $e($instance->updatedAt->format('Y-m-d H:i')) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>Start an instance to exercise this state machine.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
