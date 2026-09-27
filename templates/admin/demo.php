<?php
/** @var ?\Kontor\Demo\Domain\DemoScenario $scenario */
$states = [
    'intake' => ['Intake', 'Contacts · CRM · Tasks · Collaboration'],
    'proposal' => ['Proposal', 'Catalog · Sales · Files'],
    'approval' => ['Approved', 'Workflow · Mail · Sales order'],
    'delivery' => ['Delivery', 'Projects · Time · Collaboration'],
    'billing' => ['Billing', 'Invoices · Ledger · Germany'],
    'completed' => ['Settled', 'Payments · Ledger · Task completion'],
];
$actions = [
    'prepare_proposal' => ['Prepare proposal', 'file-text-o'],
    'request_approval' => ['Request approval', 'paper-plane'],
    'start_delivery' => ['Start delivery', 'play'],
    'issue_invoice' => ['Issue invoice', 'file-text'],
    'settle' => ['Record full payment', 'money'],
];
$entityLabels = [
    'company' => 'Company',
    'contact' => 'Contact',
    'lead' => 'Lead',
    'deal' => 'Deal',
    'task' => 'Task',
    'comment' => 'Intake comment',
    'catalog_item' => 'Catalog service',
    'quotation' => 'Quotation',
    'quotation_line' => 'Quotation line',
    'file' => 'Proposal brief',
    'order' => 'Sales order',
    'mail_message' => 'Mail history',
    'project' => 'Project',
    'milestone' => 'Milestone',
    'time_entry' => 'Time entry',
    'billable_item' => 'Billable item',
    'delivery_comment' => 'Delivery comment',
    'invoice' => 'Invoice',
    'invoice_ledger_entry' => 'Invoice ledger entry',
    'payment' => 'Payment',
    'payment_allocation' => 'Payment allocation',
];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Executable reference vertical</p>
      <h2>Connected Kontor demo</h2>
      <p>Run a real customer journey from intake through approval, delivery, invoicing and settlement.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>health/">
        <i class="fa fa-heartbeat"></i> Full health
      </a>
      <?php if ($canRun): ?>
        <form method="post" action="<?= $e($adminUrl) ?>demo-start/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <button class="uk-button uk-button-primary kontor-button" type="submit">
            <i class="fa fa-plus"></i> New scenario
          </button>
        </form>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($scenarios !== []): ?>
    <nav class="uk-margin-bottom" aria-label="Demo scenarios">
      <ul class="uk-subnav uk-subnav-pill">
        <?php foreach ($scenarios as $item): ?>
          <li<?= $scenario?->uid->toString() === $item->uid->toString() ? ' class="uk-active"' : '' ?>>
            <a href="<?= $e($adminUrl) ?>demo/?id=<?= $e(rawurlencode($item->uid->toString())) ?>">
              <?= $e($item->createdAt->format('H:i:s')) ?> · <?= $e($states[$item->currentState][0] ?? $item->currentState) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  <?php endif; ?>

  <?php if ($scenario === null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty">
        <i class="fa fa-play-circle"></i>
        <h3>No demo scenario yet</h3>
        <p>Create one to seed connected records and start the workflow.</p>
      </div>
    </section>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-bottom">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
        <div>
          <p class="kontor-eyebrow">Scenario <?= $e($scenario->uid->toString()) ?></p>
          <h3 class="uk-card-title uk-margin-remove"><?= $e($scenario->name) ?></h3>
          <p class="uk-text-meta uk-margin-small-top">
            Updated <?= $e($scenario->updatedAt->format('Y-m-d H:i:s')) ?>
          </p>
        </div>
        <span class="uk-label kontor-pill<?= $scenario->status === 'failed' ? ' kontor-pill--danger' : '' ?>">
          <?= $e(str_replace('_', ' ', $scenario->status)) ?>
        </span>
      </div>

      <div class="kontor-demo-flow uk-margin">
        <?php foreach ($states as $key => [$label, $components]): ?>
          <?php
          $keys = array_keys($states);
          $currentIndex = array_search($scenario->currentState, $keys, true);
          $stateIndex = array_search($key, $keys, true);
          $done = $stateIndex < $currentIndex || $scenario->currentState === 'completed';
          $current = $key === $scenario->currentState;
          ?>
          <article class="kontor-demo-stage<?= $done ? ' kontor-demo-stage--done' : '' ?><?= $current ? ' kontor-demo-stage--current' : '' ?>">
            <span class="kontor-demo-stage__marker">
              <i class="fa <?= $done ? 'fa-check' : ($current ? 'fa-play' : 'fa-circle-o') ?>"></i>
            </span>
            <strong><?= $e($label) ?></strong>
            <small><?= $e($components) ?></small>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($scenario->lastError !== null): ?>
        <div class="uk-alert uk-alert-warning kontor-warning">
          <i class="fa fa-exclamation-triangle"></i>
          <div><strong>Workflow note</strong><p><?= $e($scenario->lastError) ?></p></div>
        </div>
      <?php endif; ?>

      <?php if ($scenario->status === 'awaiting_approval' && $pendingApproval !== null): ?>
        <div class="uk-alert uk-alert-primary kontor-warning">
          <i class="fa fa-gavel"></i>
          <div>
            <strong>Proposal approval required</strong>
            <p>The workflow remains in proposal until this request is approved.</p>
          </div>
        </div>
        <?php if ($canApprove): ?>
          <div class="uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid>
            <form method="post" action="<?= $e($adminUrl) ?>demo-action/">
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
              <input type="hidden" name="scenario_uid" value="<?= $e($scenario->uid->toString()) ?>">
              <button class="uk-button uk-button-primary kontor-button" name="action" value="approve" type="submit">
                <i class="fa fa-check"></i> Approve proposal
              </button>
            </form>
            <form class="uk-flex uk-flex-middle uk-grid-small" method="post" action="<?= $e($adminUrl) ?>demo-action/" uk-grid>
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
              <input type="hidden" name="scenario_uid" value="<?= $e($scenario->uid->toString()) ?>">
              <div><input class="uk-input" name="reason" value="Needs revision" aria-label="Rejection reason" required></div>
              <div>
                <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" name="action" value="reject" type="submit">
                  Reject
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      <?php elseif ($nextAction !== null && $canRun): ?>
        <form method="post" action="<?= $e($adminUrl) ?>demo-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="scenario_uid" value="<?= $e($scenario->uid->toString()) ?>">
          <button class="uk-button uk-button-primary kontor-button" name="action" value="<?= $e($nextAction) ?>" type="submit">
            <i class="fa fa-<?= $e($actions[$nextAction][1]) ?>"></i>
            <?= $e($actions[$nextAction][0]) ?>
          </button>
        </form>
      <?php elseif ($scenario->isComplete()): ?>
        <div class="uk-alert uk-alert-success kontor-warning">
          <i class="fa fa-check-circle"></i>
          <div><strong>Order-to-cash completed</strong><p>The invoice is paid and all linked work is closed.</p></div>
        </div>
      <?php endif; ?>
    </section>

    <div class="uk-grid-small uk-child-width-1-2@l" uk-grid>
      <section>
        <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
          <header class="kontor-sectionhead">
            <div><p class="kontor-eyebrow">Traceability</p><h3>Connected records</h3></div>
            <span class="uk-label"><?= $e((string) count($scenario->entities)) ?></span>
          </header>
          <?php if ($scenario->entities !== []): ?>
            <dl class="uk-description-list uk-description-list-divider">
              <?php foreach ($scenario->entities as $type => $uid): ?>
                <dt><?= $e($entityLabels[$type] ?? str_replace('_', ' ', ucfirst($type))) ?></dt>
                <dd>
                  <?php if (isset($entityLinks[$type])): ?>
                    <a href="<?= $e($entityLinks[$type]) ?>"><?= $e($uid) ?></a>
                  <?php else: ?>
                    <code><?= $e($uid) ?></code>
                  <?php endif; ?>
                </dd>
              <?php endforeach; ?>
            </dl>
          <?php else: ?>
            <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty">No linked records.</div>
          <?php endif; ?>
        </div>
      </section>

      <section>
        <div class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
          <header class="kontor-sectionhead">
            <div><p class="kontor-eyebrow">Workflow engine</p><h3>Transition history</h3></div>
            <?php if ($scenario->workflowInstanceUid !== null): ?>
              <a href="<?= $e($adminUrl) ?>workflow-instance/?id=<?= $e(rawurlencode($scenario->workflowInstanceUid)) ?>">Open engine</a>
            <?php endif; ?>
          </header>
          <?php if ($history !== []): ?>
            <table class="uk-table uk-table-divider uk-table-small kontor-table">
              <thead><tr><th>Action</th><th>Transition</th><th>When</th></tr></thead>
              <tbody>
                <?php foreach (array_reverse($history) as $entry): ?>
                  <tr>
                    <td><strong><?= $e(str_replace('_', ' ', $entry->actionKey)) ?></strong></td>
                    <td><?= $e($entry->fromState) ?> → <?= $e($entry->toState) ?></td>
                    <td><?= $e($entry->occurredAt->format('H:i:s')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty">The first transition has not run yet.</div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-top pw-table-panel uk-overflow-auto">
    <header class="kontor-sectionhead">
      <div>
        <p class="kontor-eyebrow">Live smoke suite</p>
        <h3>All component checks</h3>
      </div>
      <span class="uk-label"><?= $e((string) count($componentChecks)) ?> components</span>
    </header>
    <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
      <thead><tr><th>Component</th><th>Status</th><th>Result</th></tr></thead>
      <tbody>
        <?php foreach ($componentChecks as $check): ?>
          <tr>
            <td><strong><?= $e($check['title']) ?></strong><br><code><?= $e($check['module']) ?></code></td>
            <td>
              <span class="uk-label kontor-pill<?= $check['status'] === 'critical' ? ' kontor-pill--danger' : ($check['status'] === 'warning' ? ' kontor-pill--warning' : '') ?>">
                <?= $e($check['status']) ?>
              </span>
            </td>
            <td><?= $e($check['message']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
