<?php

/** @var \Kontor\SDK\Contracts\KontorAIProviderInterface[] $providers */
/** @var \Kontor\AI\Domain\PendingAIAction[] $actions */
/** @var \Kontor\AI\Domain\PendingAIAction|null $selected */
/** @var array<string, string> $actionRequesters */
/** @var array<string, mixed>|null $result */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$capabilityLabels = ['summarize' => 'Summary', 'draft' => 'Draft', 'extract' => 'Data extraction'];
$statusLabels = ['pending' => 'Needs review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$pendingCount = count(array_filter($actions, static fn ($action): bool => $action->isPending()));
$displayOutput = static function (mixed $output) use ($e): string {
    if (!is_array($output)) {
        return '<p class="uk-margin-remove">' . $e((string) $output) . '</p>';
    }

    $items = [];
    foreach ($output as $key => $value) {
        if (in_array((string) $key, ['mode', 'characters', 'provider', 'usage', 'tokens', 'metadata'], true)) {
            continue;
        }
        $label = ucwords(trim((string) preg_replace('/(?<!^)[A-Z]|[_-]+/', ' $0', (string) $key)));
        $display = is_scalar($value) || $value === null
            ? (string) ($value ?? '—')
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $items[] = '<div><dt class="uk-text-meta">' . $e($label) . '</dt><dd class="uk-margin-small-top">'
            . nl2br($e((string) $display)) . '</dd></div>';
    }

    return $items !== []
        ? '<dl class="uk-description-list uk-margin-remove">' . implode('', $items) . '</dl>'
        : '<p class="uk-margin-remove">Task completed successfully.</p>';
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">AI workspace</p>
      <h2>AI assistant</h2>
      <p>Summarize information, prepare drafts and extract structured details while keeping sensitive actions under human control.</p>
    </div>
  </header>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <header class="uk-margin-bottom">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Create</p>
          <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Start an AI task</h3>
          <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Choose an outcome and provide only the information needed for this task.</p>
        </header>

        <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>ai-execute/" data-kontor-ai-workbench>
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="context_json" value="{}">

          <div class="uk-margin">
            <label class="uk-form-label" for="kontor-ai-capability">What do you need?</label>
            <div class="uk-form-controls uk-margin-small-top">
              <select class="uk-select" id="kontor-ai-capability" name="capability" required data-kontor-ai-capability>
                <option value="summarize">Summarize information</option>
                <option value="draft">Prepare a draft for review</option>
                <option value="extract">Extract specific details</option>
              </select>
            </div>
            <div class="uk-text-meta uk-margin-small-top">The form will show only the fields needed for the selected outcome.</div>
          </div>

          <div class="uk-margin">
            <label class="uk-form-label" for="kontor-ai-source">Source information</label>
            <div class="uk-form-controls uk-margin-small-top">
              <textarea class="uk-textarea" id="kontor-ai-source" name="text" rows="6" placeholder="Paste the note, email or business context to work with…" required></textarea>
            </div>
            <div class="uk-text-meta uk-margin-small-top">Include the relevant context, but leave out passwords, secrets and unnecessary personal information.</div>
          </div>

          <div class="uk-margin" data-kontor-ai-fields="draft" hidden>
            <label class="uk-form-label" for="kontor-ai-instructions">What should the draft achieve?</label>
            <div class="uk-form-controls uk-margin-small-top">
              <textarea class="uk-textarea" id="kontor-ai-instructions" name="instructions" rows="4" placeholder="For example: reply politely, confirm receipt and explain the next step."></textarea>
            </div>
            <div class="uk-text-meta uk-margin-small-top">The generated draft enters the review queue before it can be used.</div>
          </div>

          <div class="uk-margin" data-kontor-ai-fields="extract" hidden>
            <label class="uk-form-label" for="kontor-ai-schema">Details to extract</label>
            <div class="uk-form-controls uk-margin-small-top">
              <textarea class="uk-textarea" id="kontor-ai-schema" name="schema_fields" rows="4" placeholder="Invoice number: text&#10;Amount: number&#10;Due date: date"></textarea>
            </div>
            <div class="uk-text-meta uk-margin-small-top">Enter one detail per line as “Name: type”. Available types: text, number, date and yes/no.</div>
          </div>

          <div class="uk-margin uk-padding-small uk-background-muted">
            <label><input class="uk-checkbox" type="checkbox" name="simulate" value="1" checked> Use private local preview</label>
            <div class="uk-text-meta uk-margin-small-top">Runs entirely on this installation without sending the source information to an external provider.</div>
          </div>

          <div class="uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid>
            <div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-magic"></i> Run task</button></div>
            <div class="uk-text-meta">Drafts always require a person to approve them.</div>
          </div>
        </form>
      </section>
    </div>

    <aside class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Availability</p>
        <h3 class="uk-card-title uk-margin-small-top">AI service</h3>
        <span class="uk-label<?= $providers !== [] ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $providers !== [] ? 'Ready' : 'Local preview only' ?></span>
        <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">
          <?= $providers !== []
              ? 'A production provider is connected. You can still choose the private local preview for testing.'
              : 'No external provider is connected. Private local preview remains available for safe testing.' ?>
        </p>
      </section>

      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Human review</p>
        <h3 class="uk-card-title uk-margin-small-top">Approval queue</h3>
        <div class="uk-grid-small uk-child-width-1-2 uk-margin-small-bottom" uk-grid>
          <div><strong class="uk-display-block uk-text-large"><?= $e((string) $pendingCount) ?></strong><span class="uk-text-meta">waiting</span></div>
          <div><strong class="uk-display-block uk-text-large"><?= $e((string) count($actions)) ?></strong><span class="uk-text-meta">total</span></div>
        </div>
        <p class="uk-text-muted uk-margin-remove-bottom">Generated drafts remain unavailable until an authorized teammate approves or rejects them.</p>
      </section>
    </aside>
  </div>

  <?php if ($result !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <header class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
        <div>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom"><?= $e($result['simulated'] ? 'Private preview' : 'AI service') ?></p>
          <h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Task result</h3>
        </div>
        <span class="uk-label<?= $result['success'] ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e($result['pending'] ? 'Needs review' : ($result['success'] ? 'Completed' : 'Could not complete')) ?></span>
      </header>
      <?php if ($result['pending']): ?>
        <div class="uk-alert-primary" uk-alert>
          <p><strong>The draft is ready for review.</strong> Its content stays protected until a person makes a decision.</p>
          <p><a class="uk-button uk-button-primary" href="<?= $e($adminUrl) ?>ai/?id=<?= $e(rawurlencode((string) $result['pendingUid'])) ?>">Review draft</a></p>
        </div>
      <?php elseif ($result['success']): ?>
        <div class="uk-padding-small uk-background-muted"><?= $displayOutput($result['output']) ?></div>
      <?php else: ?>
        <div class="uk-alert-warning" uk-alert><p><?= $e((string) ($result['error'] ?? 'The AI service could not complete this task.')) ?></p></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <header class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
      <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Human review</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Recent AI tasks</h3></div>
      <?php if ($pendingCount > 0): ?><span class="uk-badge"><?= $e((string) $pendingCount) ?> waiting</span><?php endif; ?>
    </header>
    <?php if ($actions !== []): ?>
      <div class="uk-overflow-auto">
        <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
          <thead><tr><th>Task</th><th>Requested by</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody><?php foreach ($actions as $action):
            $uid = $action->uid->toString();
            $capability = $capabilityLabels[$action->capability] ?? ucwords($action->capability);
            $status = $statusLabels[$action->status] ?? ucwords($action->status);
          ?><tr>
            <td><a href="<?= $e($adminUrl) ?>ai/?id=<?= $e(rawurlencode($uid)) ?>"><strong><?= $e($capability) ?></strong><span class="uk-display-block uk-text-meta"><?= $e($action->createdAt->format('M j, Y · H:i')) ?></span></a></td>
            <td><?= $e($actionRequesters[$uid] ?? 'Automation') ?></td>
            <td><span class="uk-label<?= $action->isPending() ? ' uk-label-warning' : ($action->status === 'approved' ? ' uk-label-success' : '') ?>"><?= $e($status) ?></span></td>
            <td><?= $e(($action->decidedAt ?? $action->createdAt)->format('M j, Y · H:i')) ?></td>
          </tr><?php endforeach; ?></tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="uk-placeholder uk-text-center"><span uk-icon="icon: check; ratio: 1.4"></span><h4 class="uk-margin-small-top uk-margin-small-bottom">Nothing is waiting for review</h4><p class="uk-text-muted uk-margin-remove">Draft tasks will appear here when they need a decision.</p></div>
    <?php endif; ?>
  </section>

  <?php if ($selected !== null):
    $selectedUid = $selected->uid->toString();
    $sourceText = (string) ($selected->input['context']['sourceText'] ?? '');
    $instructions = (string) ($selected->input['instructions'] ?? '');
  ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <header class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
        <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Approval</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Review <?= $e(strtolower($capabilityLabels[$selected->capability] ?? $selected->capability)) ?></h3></div>
        <span class="uk-label<?= $selected->isPending() ? ' uk-label-warning' : ($selected->status === 'approved' ? ' uk-label-success' : '') ?>"><?= $e($statusLabels[$selected->status] ?? ucwords($selected->status)) ?></span>
      </header>

      <dl class="uk-description-list">
        <dt>Requested by</dt><dd><?= $e($actionRequesters[$selectedUid] ?? 'Automation') ?> · <?= $e($selected->createdAt->format('M j, Y · H:i')) ?></dd>
        <?php if ($sourceText !== ''): ?><dt>Source information</dt><dd><?= nl2br($e($sourceText)) ?></dd><?php endif; ?>
        <?php if ($instructions !== ''): ?><dt>Requested outcome</dt><dd><?= nl2br($e($instructions)) ?></dd><?php endif; ?>
      </dl>

      <?php if ($selected->isPending()): ?>
        <div class="uk-alert-primary" uk-alert><p>The generated draft is protected until you approve or reject it.</p></div>
        <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
          <div><form method="post" action="<?= $e($adminUrl) ?>ai-decide/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="action_uid" value="<?= $e($selectedUid) ?>"><input type="hidden" name="decision" value="approve">
            <button class="uk-button uk-button-primary" type="submit"><i class="fa fa-check"></i> Approve draft</button>
          </form></div>
          <div><form method="post" action="<?= $e($adminUrl) ?>ai-decide/" data-kontor-confirm="Reject this draft? It will remain unavailable.">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="action_uid" value="<?= $e($selectedUid) ?>"><input type="hidden" name="decision" value="reject">
            <button class="uk-button uk-button-default" type="submit"><i class="fa fa-times"></i> Reject draft</button>
          </form></div>
        </div>
      <?php elseif ($selected->status === 'approved'): ?>
        <div class="uk-padding-small uk-background-muted"><?= $displayOutput($selected->output) ?></div>
      <?php else: ?>
        <div class="uk-alert-warning" uk-alert><p>This draft was rejected and remains unavailable.</p></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
