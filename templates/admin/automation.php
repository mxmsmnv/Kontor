<?php

/** @var \Kontor\Automation\Domain\AutomationRule|null $rule */
/** @var array{name: string, triggerEvent: string} $values */
/** @var string $error */
/** @var \Kontor\Automation\Domain\RuleCondition[] $conditions */
/** @var \Kontor\Automation\Domain\RuleAction[] $actions */
/** @var \Kontor\Automation\Domain\ExecutionLog[] $logs */
/** @var string[] $actionHandlers */
/** @var array<string, string> $triggerEvents */
/** @var bool $canManage */
/** @var bool $canDryRun */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static fn (string $value): string =>
    ucwords(str_replace(['.', '_', '-'], ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'active' => ' uk-label-success',
    'paused' => ' uk-label-warning',
    default => '',
};
$actionLabel = static fn (string $key): string => match ($key) {
    'tasks.create' => 'Create a task',
    'log' => 'Record an audit entry',
    default => $humanize($key),
};
$actionDescription = static function ($action) use ($humanize): string {
    if ($action->actionKey === 'tasks.create') {
        $title = trim((string) ($action->params['title'] ?? 'Follow-up task'));
        $parts = [$title, $humanize((string) ($action->params['priority'] ?? 'normal')) . ' priority'];
        if (isset($action->params['dueInMinutes'])) {
            $parts[] = 'due in ' . (int) $action->params['dueInMinutes'] . ' minutes';
        }
        if (($action->params['linkToTrigger'] ?? false) === true) {
            $parts[] = 'linked to the source record';
        }

        return implode(' · ', $parts);
    }
    if ($action->actionKey === 'log') {
        return 'Keep a trace of the event and its data in the automation history.';
    }

    return 'Run the configured ' . $humanize($action->actionKey) . ' action.';
};
$logOutcome = static function ($log): array {
    if ($log->error !== null) {
        return ['Failed', 'uk-label-danger', $log->error];
    }
    if ($log->recursionBlocked) {
        return ['Blocked', 'uk-label-warning', 'A repeated event was safely prevented.'];
    }
    if (!$log->matched) {
        return ['Skipped', '', 'The event did not meet every condition.'];
    }

    return ['Completed', 'uk-label-success', count($log->actionsResult) . ' action' . (count($log->actionsResult) === 1 ? '' : 's') . ' completed.'];
};
$otherHandlers = array_values(array_diff($actionHandlers, ['tasks.create', 'log']));
$lastRun = $logs[0] ?? null;
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Automation · Rule builder</p><h2><?= $e($rule?->name ?? 'New automation') ?></h2><p><?= $rule === null ? 'Choose a business event and give the automation a clear purpose.' : 'Build the rule from its starting event through conditions, actions and safe testing.' ?></p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>automations/"><i class="fa fa-arrow-left"></i> All automations</a></div>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Automation could not be created.</strong> <?= $e($error) ?></p></div><?php endif; ?>

  <?php if ($rule === null): ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="./">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Purpose</p><h3 class="uk-card-title uk-margin-small-top">What should this automation achieve?</h3>
          <div class="uk-margin"><label class="uk-form-label" for="automation-name">Automation name</label><input class="uk-input uk-margin-small-top" id="automation-name" name="name" value="<?= $e($values['name']) ?>" placeholder="Create a follow-up for won deals" maxlength="191" autofocus required><div class="uk-text-meta uk-margin-small-top">Use an outcome-focused name your team will recognize in activity history.</div></div>
          <hr>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Starting event</p><h3 class="uk-card-title uk-margin-small-top">When should it run?</h3>
          <?php if ($triggerEvents !== []): ?><div class="uk-margin"><label class="uk-form-label" for="automation-trigger">Business event</label><select class="uk-select uk-margin-small-top" id="automation-trigger" name="trigger_event" required><?php foreach ($triggerEvents as $event => $label): ?><option value="<?= $e($event) ?>"<?= $values['triggerEvent'] === $event ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Only events from installed Kontor components are suggested here.</div></div><details class="uk-margin"><summary class="uk-button uk-button-text">Advanced: custom event</summary><div class="uk-margin-small-top"><label class="uk-form-label" for="automation-custom-trigger">Custom event name</label><input class="uk-input uk-margin-small-top" id="automation-custom-trigger" name="custom_trigger_event" value="<?= array_key_exists($values['triggerEvent'], $triggerEvents) ? '' : $e($values['triggerEvent']) ?>" placeholder="integration.record.changed" pattern="[a-z][a-z0-9_.-]{2,190}"><div class="uk-text-meta uk-margin-small-top">Use this only for an integration that documents its own dot-separated event name. A custom value overrides the selection above.</div></div></details>
          <?php else: ?><div class="uk-margin"><label class="uk-form-label" for="automation-trigger">Business event</label><input class="uk-input uk-margin-small-top" id="automation-trigger" name="trigger_event" value="<?= $e($values['triggerEvent']) ?>" placeholder="integration.record.changed" pattern="[a-z][a-z0-9_.-]{2,190}" required><div class="uk-text-meta uk-margin-small-top">Enter the dot-separated event name supplied by the integration.</div></div><?php endif; ?>
          <div class="uk-flex uk-flex-right uk-flex-middle uk-grid-small uk-margin" uk-grid><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>automations/">Cancel</a></div><div><button class="uk-button uk-button-primary" type="submit" name="submit_save" value="1"><i class="fa fa-arrow-right"></i> Create and continue</button></div></div>
        </form>
      </div>
      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Next</p><h3 class="uk-card-title uk-margin-small-top">Build the workflow</h3><p class="uk-text-muted">After creating the rule, you can add:</p><ul class="uk-list uk-list-divider"><li><strong><i class="fa fa-filter"></i> Conditions</strong><div class="uk-text-meta uk-margin-small-top">Optional checks that decide whether an event should continue.</div></li><li><strong><i class="fa fa-bolt"></i> Actions</strong><div class="uk-text-meta uk-margin-small-top">Tasks or other follow-up work Kontor completes.</div></li><li><strong><i class="fa fa-flask"></i> Safe test</strong><div class="uk-text-meta uk-margin-small-top">Evaluate a sample event before relying on the rule.</div></li></ul><div class="uk-alert-primary uk-margin-remove-bottom" uk-alert><p>New rules start active. Add at least one action before depending on the result.</p></div></section>
      </div>
    </div>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-grid-medium uk-flex-middle" uk-grid><div class="uk-width-expand"><span class="uk-label<?= $statusClass($rule->status) ?>"><?= $e($humanize($rule->status)) ?></span><h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($humanize($rule->triggerEvent)) ?></h3><p class="uk-text-muted uk-margin-remove">The automation evaluates every matching event against all conditions below.</p></div><div class="uk-width-auto@m uk-text-right@m"><div class="uk-text-meta">Last run</div><strong><?= $lastRun !== null ? $e($lastRun->occurredAt->format('M j, Y · H:i')) : 'Never' ?></strong></div></div>
      <hr><div class="uk-grid-small uk-grid-divider uk-child-width-1-3" uk-grid><div><div class="uk-text-meta">When</div><strong><?= $e($humanize($rule->triggerEvent)) ?></strong></div><div><div class="uk-text-meta">If</div><strong><?= $e((string) count($conditions)) ?> condition<?= count($conditions) === 1 ? '' : 's' ?></strong></div><div><div class="uk-text-meta">Then</div><strong><?= $e((string) count($actions)) ?> action<?= count($actions) === 1 ? '' : 's' ?></strong></div></div>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Step 1 · Decision</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Conditions</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Every condition must match. Leave this step empty to accept every event.</p></div></div>
      <?php if ($conditions !== []): ?><ul class="uk-list uk-list-divider uk-margin"><?php foreach ($conditions as $condition): ?><li><div class="uk-grid-small uk-flex-middle" uk-grid><div class="uk-width-expand"><strong><?= $e($humanize($condition->field)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($humanize($condition->operator)) ?><?= $condition->value !== null ? ' “' . $e($condition->value) . '”' : '' ?></div></div><div><span class="uk-label">Required</span></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-alert-primary uk-margin" uk-alert><p class="uk-margin-remove"><strong>Every event will continue.</strong> Add a condition only when this automation should be selective.</p></div><?php endif; ?>
      <?php if ($canManage): ?><details class="uk-margin-top"><summary class="uk-button uk-button-default"><i class="fa fa-plus"></i> Add condition</summary><form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>automation-condition/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="rule_uid" value="<?= $e($rule->uid->toString()) ?>"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-1-1 uk-width-expand@m"><label class="uk-form-label" for="condition-field">Event field</label><input class="uk-input uk-margin-small-top" id="condition-field" name="field" placeholder="priority or customer.country" required><div class="uk-text-meta uk-margin-small-top">Use the field name supplied by the starting event. Nested fields use dots.</div></div><div class="uk-width-1-1 uk-width-1-4@m"><label class="uk-form-label" for="condition-operator">Comparison</label><select class="uk-select uk-margin-small-top" id="condition-operator" name="operator"><?php foreach (\Kontor\Automation\Domain\RuleCondition::OPERATORS as $operator): ?><option value="<?= $e($operator) ?>"><?= $e($humanize($operator)) ?></option><?php endforeach; ?></select></div><div class="uk-width-1-1 uk-width-1-4@m"><label class="uk-form-label" for="condition-value">Expected value</label><input class="uk-input uk-margin-small-top" id="condition-value" name="value" placeholder="high"><div class="uk-text-meta uk-margin-small-top">Leave empty only for comparisons that permit it.</div></div><div class="uk-width-1-1 uk-width-auto@m"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Add</button></div></div></form></details><?php endif; ?>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Step 2 · Outcome</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Actions</h3><p class="uk-text-muted uk-margin-small-top">Actions run in order after every condition matches.</p></div>
      <?php if ($actions !== []): ?><ul class="uk-list uk-list-divider uk-margin"><?php foreach ($actions as $index => $action): ?><li><div class="uk-flex uk-flex-top uk-grid-small" uk-grid><div><span class="kontor-stat__icon"><strong><?= $e((string) ($index + 1)) ?></strong></span></div><div class="uk-width-expand"><strong><?= $e($actionLabel($action->actionKey)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($actionDescription($action)) ?></div></div></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-alert-warning uk-margin" uk-alert><p class="uk-margin-remove"><strong>No action is configured yet.</strong> The rule can evaluate events but will not produce an outcome.</p></div><?php endif; ?>
      <?php if ($canManage): ?>
        <?php if (in_array('tasks.create', $actionHandlers, true)): ?><details class="uk-margin-top"><summary class="uk-button uk-button-default"><i class="fa fa-check-square-o"></i> Add task action</summary><form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>automation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="rule_uid" value="<?= $e($rule->uid->toString()) ?>"><input type="hidden" name="action_key" value="tasks.create"><div class="uk-grid-small" uk-grid><div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="automation-task-title">Task title</label><input class="uk-input uk-margin-small-top" id="automation-task-title" name="task_title" placeholder="Follow up {{ customer.name }}" required><div class="uk-text-meta uk-margin-small-top">Use placeholders from the event when the title should include customer or record data.</div></div><div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="automation-task-priority">Priority</label><select class="uk-select uk-margin-small-top" id="automation-task-priority" name="task_priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div><div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="automation-task-description">Description <span class="uk-text-meta">Optional</span></label><textarea class="uk-textarea uk-margin-small-top" id="automation-task-description" name="task_description" rows="3" placeholder="Explain what the assignee should do next."></textarea><div class="uk-text-meta uk-margin-small-top">Give the assignee enough context to complete the follow-up.</div></div><div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="automation-task-due">Due in minutes <span class="uk-text-meta">Optional</span></label><input class="uk-input uk-margin-small-top" id="automation-task-due" name="due_in_minutes" type="number" min="0" step="1" placeholder="60"><div class="uk-text-meta uk-margin-small-top">Calculated from the event time.</div></div></div><label class="uk-display-block uk-margin"><input class="uk-checkbox" type="checkbox" name="link_to_trigger" value="1" checked> <span class="uk-margin-small-left"><strong>Connect task to the source record</strong></span><span class="uk-text-meta uk-display-block uk-margin-small-left">Available when the event belongs to a CRM, customer or other business record.</span></label><div class="uk-flex uk-flex-right"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add task action</button></div></form></details><?php endif; ?>
        <?php if (in_array('log', $actionHandlers, true)): ?><form class="uk-margin-top" method="post" action="<?= $e($adminUrl) ?>automation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="rule_uid" value="<?= $e($rule->uid->toString()) ?>"><input type="hidden" name="action_key" value="log"><button class="uk-button uk-button-default" type="submit"><i class="fa fa-history"></i> Add audit log action</button><div class="uk-text-meta uk-margin-small-top">Keeps a diagnostic trace without changing another business record.</div></form><?php endif; ?>
        <?php if ($otherHandlers !== []): ?><details class="uk-margin-top"><summary class="uk-button uk-button-text">Advanced actions</summary><form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>automation-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="rule_uid" value="<?= $e($rule->uid->toString()) ?>"><div class="uk-margin"><label class="uk-form-label" for="automation-action-handler">Action</label><select class="uk-select uk-margin-small-top" id="automation-action-handler" name="action_key"><?php foreach ($otherHandlers as $handler): ?><option value="<?= $e($handler) ?>"><?= $e($actionLabel($handler)) ?></option><?php endforeach; ?></select></div><div class="uk-margin"><label class="uk-form-label" for="automation-action-params">Configuration</label><textarea class="uk-textarea uk-margin-small-top" id="automation-action-params" name="params_json" rows="5">{}</textarea><div class="uk-text-meta uk-margin-small-top">Advanced handlers use a JSON object documented by the component that registered the action.</div></div><button class="uk-button uk-button-primary" type="submit">Add advanced action</button></form></details><?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($canDryRun): ?><section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Step 3 · Verification</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Test the automation</h3><p class="uk-text-muted uk-margin-small-top">Use representative event data to confirm conditions and actions before relying on the rule.</p></div><details><summary class="uk-button uk-button-default"><i class="fa fa-flask"></i> Open test bench</summary><form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>automation-run/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="rule_uid" value="<?= $e($rule->uid->toString()) ?>"><label class="uk-form-label" for="automation-payload">Sample event data</label><textarea class="uk-textarea uk-margin-small-top" id="automation-payload" name="payload_json" rows="7" required>{"priority":"high","customer":{"name":"Example customer"}}</textarea><div class="uk-text-meta uk-margin-small-top">Use a JSON object with the fields referenced by your conditions and action placeholders.</div><label class="uk-display-block uk-margin"><input class="uk-checkbox" name="dry_run" type="checkbox" value="1" checked> <span class="uk-margin-small-left"><strong>Test only — do not create real work</strong></span><span class="uk-text-meta uk-display-block uk-margin-small-left">Keep this checked while validating a rule. Unchecking it runs live actions.</span></label><div class="uk-flex uk-flex-right"><button class="uk-button uk-button-primary" type="submit" data-kontor-confirm="Run this automation with the sample event data?"><i class="fa fa-play"></i> Run test</button></div></form></details></section><?php endif; ?>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top"><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Activity</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Run history</h3><p class="uk-text-muted uk-margin-small-top">See whether this rule matched and what happened next.</p></div>
      <?php if ($logs !== []): ?><div class="uk-overflow-auto uk-visible@m uk-margin-top"><table class="uk-table uk-table-divider uk-table-middle uk-table-small uk-margin-remove-bottom"><thead><tr><th>When</th><th>Mode</th><th>Outcome</th><th>Details</th></tr></thead><tbody><?php foreach (array_slice($logs, 0, 25) as $log): $outcome = $logOutcome($log); ?><tr><td><?= $e($log->occurredAt->format('M j, Y · H:i')) ?></td><td><?= $e($log->dryRun ? 'Test' : 'Live') ?></td><td><span class="uk-label <?= $e($outcome[1]) ?>"><?= $e($outcome[0]) ?></span></td><td><?= $e($outcome[2]) ?></td></tr><?php endforeach; ?></tbody></table></div><ul class="uk-list uk-list-divider uk-hidden@m uk-margin-top uk-margin-remove-bottom"><?php foreach (array_slice($logs, 0, 25) as $log): $outcome = $logOutcome($log); ?><li><div class="uk-flex uk-flex-between"><strong><?= $e($log->occurredAt->format('M j · H:i')) ?></strong><span class="uk-label <?= $e($outcome[1]) ?>"><?= $e($outcome[0]) ?></span></div><div class="uk-text-meta uk-margin-small-top"><?= $e($log->dryRun ? 'Test' : 'Live') ?> · <?= $e($outcome[2]) ?></div></li><?php endforeach; ?></ul><?php else: ?><div class="uk-placeholder uk-text-center uk-margin-top"><span class="fa fa-history fa-2x uk-text-muted"></span><h3 class="uk-margin-small-top uk-margin-small-bottom">No runs yet</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Run a safe test or wait for the next matching business event.</p></div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
