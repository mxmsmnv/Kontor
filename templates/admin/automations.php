<?php

/** @var \Kontor\Automation\Domain\AutomationRule[] $rules */
/** @var \Kontor\Automation\Domain\ExecutionLog[] $logs */
/** @var bool $canManage */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead"><div><p class="kontor-eyebrow">Extensibility · Event pipeline</p><h2>Automations</h2><p>Evaluate real Kontor events through conditions and registered actions.</p></div><?php if ($canManage): ?><div class="kontor-pagehead__actions"><a class="kontor-button" href="<?= $e($adminUrl) ?>automation/"><i class="fa fa-plus"></i> New rule</a></div><?php endif; ?></header>
  <section class="kontor-card kontor-tablewrap"><?php if ($rules !== []): ?><table class="kontor-table"><thead><tr><th>Rule</th><th>Trigger event</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach ($rules as $rule): ?><tr><td><strong><a href="<?= $e($adminUrl) ?>automation/?id=<?= $e(rawurlencode($rule->uid->toString())) ?>"><?= $e($rule->name) ?></a></strong></td><td><?= $e($rule->triggerEvent) ?></td><td><span class="kontor-pill"><?= $e($rule->status) ?></span></td><td><?= $e($rule->updatedAt->format('Y-m-d H:i')) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><i class="fa fa-bolt"></i><h3>No automation rules</h3><p>Create a trigger → conditions → actions pipeline.</p></div><?php endif; ?></section>
  <section class="kontor-card kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Observability</p><h3>Recent executions</h3></div></header><?php if ($logs !== []): ?><table class="kontor-table"><thead><tr><th>When</th><th>Event</th><th>Mode</th><th>Matched</th><th>Result</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= $e($log->occurredAt->format('Y-m-d H:i:s')) ?></td><td><?= $e($log->triggerEvent) ?></td><td><?= $log->dryRun ? 'dry run' : 'live' ?></td><td><?= $log->matched ? 'yes' : 'no' ?></td><td><?= $e($log->recursionBlocked ? 'recursion blocked' : ($log->error ?? (string) count($log->actionsResult) . ' action(s)')) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><p>No executions recorded yet.</p></div><?php endif; ?></section>
</div>
