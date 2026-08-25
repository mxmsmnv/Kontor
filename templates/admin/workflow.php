<?php

/** @var \Kontor\Workflow\Domain\WorkflowDefinition|null $definition */
/** @var array{workflowKey: string, entityType: string, entityTypeChoice: string, customEntityType: string, name: string, initialState: string, states: string} $values */
/** @var string $error */
/** @var \Kontor\Workflow\Domain\WorkflowTransition[] $transitions */
/** @var \Kontor\Workflow\Domain\WorkflowInstance[] $instances */
/** @var bool $canManage */
/** @var bool $canTransition */
/** @var array<string, string> $entityTypeOptions */
/** @var string $recordTypeLabel */
/** @var array<string, array{label: string, route: string, available: bool}> $workflowTargets */
/** @var array<string, array{label: string, route: ?string}> $instanceViews */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static fn (?string $value): string => $value === null || trim($value) === ''
    ? 'Not set'
    : ucwords(str_replace(['_', '-', '.'], ' ', trim($value)));
$availableWorkflowTargets = array_filter(
    $workflowTargets,
    static fn (array $target): bool => $target['available'],
);
$componentRoute = $definition?->entityType === 'demo_scenario'
    && isset($entityTypeOptions['demo_scenario'])
    ? 'demo/'
    : null;
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
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Process overview</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($recordTypeLabel) ?> lifecycle</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">This workflow defines the allowed path for <?= $e(mb_strtolower($recordTypeLabel)) ?> records. Add actions below, then start or review live work.</p></div>
        <div><span class="uk-label<?= $definition->status === 'active' ? ' uk-label-success' : '' ?>"><?= $e($humanize($definition->status)) ?></span></div>
      </div>
      <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s uk-margin-medium-top" uk-grid>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-play"></i></span><span><strong class="kontor-stat__value"><?= $e($humanize($definition->initialState)) ?></strong><span class="kontor-stat__label">Starting stage</span></span></div></div>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-map-signs"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($definition->states)) ?></strong><span class="kontor-stat__label">Process stages</span></span></div></div>
        <div><div class="kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-tasks"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($instances)) ?></strong><span class="kontor-stat__label">Workflow runs</span></span></div></div>
      </div>
      <?php if ($componentRoute !== null): ?><div class="uk-margin-medium-top"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl . $componentRoute) ?>"><i class="fa fa-external-link"></i> Open <?= $e($recordTypeLabel) ?> workspace</a></div><?php endif; ?>
    </section>

    <section class="uk-margin-medium-bottom">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Process map</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Stages from start to finish</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Each action moves a record from one declared stage to another.</p></div><div><span class="uk-label"><?= $e((string) count($transitions)) ?> actions</span></div></div>
      <div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@l" uk-grid>
        <?php foreach ($definition->states as $index => $state): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><div class="uk-flex uk-flex-between uk-flex-top"><span class="uk-text-meta">Stage <?= $e((string) ($index + 1)) ?></span><?php if ($state === $definition->initialState): ?><span class="uk-label">Start</span><?php endif; ?></div><strong class="uk-display-block uk-margin-small-top"><?= $e($humanize($state)) ?></strong></div></div><?php endforeach; ?>
      </div>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-grid-medium" uk-grid>
        <?php if ($canManage): ?>
          <div class="uk-width-1-1 uk-width-2-5@l">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Add a business action</p><h3 class="uk-card-title uk-margin-small-top">Connect two stages</h3><p class="uk-text-muted">Name the decision or activity teammates perform. Kontor creates its internal action reference automatically.</p>
            <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>workflow-transition/">
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><input type="hidden" name="required_permission" value="">
              <div class="uk-margin"><label class="uk-form-label" for="workflow-action-name">Action name <span class="uk-text-danger">*</span></label><input class="uk-input uk-margin-small-top" id="workflow-action-name" name="action_key" placeholder="For example: Request approval" required><div class="uk-text-meta uk-margin-small-top">Required. Use the words teammates expect to see on the action button.</div></div>
              <div class="uk-grid-small uk-child-width-1-2@s" uk-grid><div><label class="uk-form-label" for="workflow-from-state">From stage <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="workflow-from-state" name="from_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($humanize($state)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Where this action becomes available.</div></div><div><label class="uk-form-label" for="workflow-to-state">To stage <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="workflow-to-state" name="to_state"><?php foreach ($definition->states as $state): ?><option value="<?= $e($state) ?>"><?= $e($humanize($state)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Where the record moves after the action.</div></div></div>
              <div class="uk-margin"><label><input class="uk-checkbox" name="requires_approval" type="checkbox" value="1"> <span class="uk-margin-small-left">Require approval before moving</span></label><div class="uk-text-meta uk-margin-small-top">When enabled, the record waits for an authorized reviewer instead of changing stage immediately.</div></div>
              <button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add action</button>
            </form>
          </div>
        <?php endif; ?>
        <div class="uk-width-1-1<?= $canManage ? ' uk-width-3-5@l' : '' ?>">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Available actions</p><h3 class="uk-card-title uk-margin-small-top">How work moves</h3><p class="uk-text-muted">Approval badges show which actions pause for a review decision.</p>
          <?php if ($transitions !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($transitions as $transition): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($humanize($transition->actionKey)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($humanize($transition->fromState)) ?> <i class="fa fa-long-arrow-right uk-margin-small-left uk-margin-small-right"></i> <?= $e($humanize($transition->toState)) ?></div></div><div><?php if ($transition->requiresApproval): ?><span class="uk-label uk-label-warning"><i class="fa fa-check-square-o"></i> Approval</span><?php else: ?><span class="uk-label"><i class="fa fa-bolt"></i> Direct</span><?php endif; ?></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-random fa-2x uk-text-muted"></i><h4>No actions yet</h4><p class="uk-text-muted">Add the first action to connect two stages.</p></div><?php endif; ?>
        </div>
      </div>
    </section>

    <section>
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-bottom" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Live work</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Records using this workflow</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Open a run to review its current stage, available actions and decision history.</p></div><div><span class="uk-label"><?= $e((string) count($instances)) ?> total</span></div></div>
      <?php if ($canTransition && $availableWorkflowTargets !== []): ?><section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-bottom"><form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>workflow-instance/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-expand@m"><label class="uk-form-label" for="workflow-record">Choose a <?= $e(mb_strtolower($recordTypeLabel)) ?> <span class="uk-text-danger">*</span></label><select class="uk-select uk-margin-small-top" id="workflow-record" name="entity_uid" required><option value="">Select a record…</option><?php foreach ($availableWorkflowTargets as $uid => $target): ?><option value="<?= $e($uid) ?>"><?= $e($target['label']) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Only records not already tracked by this workflow are shown.</div></div><div class="uk-width-auto@m"><button class="uk-button uk-button-primary" type="submit" name="submit_start" value="1"><i class="fa fa-play"></i> Start workflow</button></div></div></form></section><?php elseif ($canTransition && $workflowTargets !== []): ?><div class="uk-alert-primary uk-margin-bottom" uk-alert><p>Every available <?= $e(mb_strtolower($recordTypeLabel)) ?> is already tracked by this workflow.</p></div><?php endif; ?>
      <?php if ($instances !== []): ?><div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@l" uk-grid><?php foreach ($instances as $instance): ?><?php $instanceView = $instanceViews[$instance->uid->toString()]; ?><div><article class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1"><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $e($recordTypeLabel) ?></p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($instanceView['label']) ?></h3></div><div><span class="uk-label"><?= $e($humanize($instance->currentState)) ?></span></div></div><div class="uk-grid-small uk-child-width-1-2 uk-margin" uk-grid><div><div class="uk-text-meta">Started</div><strong><?= $e($instance->createdAt->format('M j, Y · H:i')) ?></strong></div><div><div class="uk-text-meta">Last activity</div><strong><?= $e($instance->updatedAt->format('M j, Y · H:i')) ?></strong></div></div><div class="uk-flex uk-flex-right uk-flex-wrap uk-grid-small" uk-grid><?php if ($instanceView['route'] !== null): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl . $instanceView['route']) ?>">Open record</a></div><?php endif; ?><div><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($instance->uid->toString())) ?>">Review workflow <i class="fa fa-angle-right"></i></a></div></div></article></div><?php endforeach; ?></div><?php else: ?><div class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-placeholder uk-text-center"><i class="fa fa-play-circle-o fa-2x uk-text-muted"></i><h3>No live work yet</h3><p class="uk-text-muted">Start the workflow for a business record to track its progress here.</p></div></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
