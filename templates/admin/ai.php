<?php

/** @var \Kontor\SDK\Contracts\KontorAIProviderInterface[] $providers */
/** @var \Kontor\AI\Domain\PendingAIAction[] $actions */
/** @var \Kontor\AI\Domain\PendingAIAction|null $selected */
/** @var array<string, mixed>|null $result */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$defaultContext = '{"subject":"QA customer request"}';
$defaultSchema = '{"invoiceNumber":"string","amount":"decimal"}';
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Advanced capabilities · Human in the loop</p>
      <h2>AI</h2>
      <p>Provider-routed summaries, drafting, extraction, and an explicit approval queue for critical output.</p>
    </div>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Provider boundary</p><h3>Runtime status</h3></div>
      <div><strong><?= $e((string) count($providers)) ?> registered</strong></div>
    </header>
    <p>The local preview provider is available only when explicitly selected below. It makes no network request and is never registered as a production provider.</p>
    <?php if ($providers !== []): ?>
      <ul>
        <?php foreach ($providers as $provider): ?><li><code><?= $e($provider::class) ?></code></li><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No production AI provider is registered.</p></div>
    <?php endif; ?>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Capability bench</p><h3>Run AI request</h3></div></header>
    <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>ai-execute/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Capability *</span>
        <select name="capability" required>
          <option value="summarize">Summarize</option>
          <option value="draft">Draft</option>
          <option value="extract">Extract</option>
        </select>
      </label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Source text *</span><textarea name="text" rows="7" required>Customer asks for a concise reply about invoice 123 for 1,250 USD.</textarea></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Draft instructions</span><textarea name="instructions" rows="3">Write a clear, courteous reply confirming receipt.</textarea></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Context object (JSON)</span><textarea name="context_json" rows="4"><?= $e($defaultContext) ?></textarea></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Extraction schema (JSON)</span><textarea name="schema_json" rows="4"><?= $e($defaultSchema) ?></textarea></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span><input type="checkbox" name="simulate" value="1" checked> Use local preview provider (no network)</span></label>
      <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Run AI request</button></div>
    </form>
  </section>

  <?php if ($result !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead">
        <div><p class="kontor-eyebrow"><?= $e($result['simulated'] ? 'Local preview' : 'Production provider') ?></p><h3>Workbench result</h3></div>
        <div><strong><?= $e($result['pending'] ? 'approval required' : ($result['success'] ? 'completed' : 'failed')) ?></strong></div>
      </header>
      <?php if ($result['pending']): ?>
        <p><strong>Output withheld.</strong> A human must approve or reject this draft before its generated output is exposed.</p>
        <p><a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>ai/?id=<?= $e(rawurlencode((string) $result['pendingUid'])) ?>">Review pending action</a></p>
      <?php elseif ($result['success']): ?>
        <pre><code><?= $e(json_encode($result['output'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
      <?php else: ?>
        <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p><?= $e((string) ($result['error'] ?? 'Provider request failed.')) ?></p></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Human approval</p><h3>Action queue</h3></div></header>
    <?php if ($actions !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Created</th><th>Capability</th><th>Status</th><th>Requested by</th><th>Decided</th></tr></thead>
        <tbody><?php foreach ($actions as $action): ?><tr>
          <td><strong><a href="<?= $e($adminUrl) ?>ai/?id=<?= $e(rawurlencode($action->uid->toString())) ?>"><?= $e($action->createdAt->format('Y-m-d H:i:s')) ?></a></strong></td>
          <td><?= $e($action->capability) ?></td>
          <td><?= $e($action->status) ?></td>
          <td><?= $e((string) ($action->requestedBy ?? 'system')) ?></td>
          <td><?= $e($action->decidedAt?->format('Y-m-d H:i:s') ?? '—') ?></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No AI actions have entered the approval queue.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow"><?= $e($selected->capability) ?> · <?= $e($selected->status) ?></p><h3>Approval detail</h3></div></header>
      <div class="kontor-detailgrid">
        <div><span>Action UID</span><strong><?= $e($selected->uid->toString()) ?></strong></div>
        <div><span>Requested by</span><strong><?= $e((string) ($selected->requestedBy ?? 'system')) ?></strong></div>
        <div><span>Created</span><strong><?= $e($selected->createdAt->format('Y-m-d H:i:s')) ?></strong></div>
        <div><span>Decided by</span><strong><?= $e((string) ($selected->decidedBy ?? '—')) ?></strong></div>
      </div>
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Request</p><h3>Input</h3></div></header>
      <pre><code><?= $e(json_encode($selected->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Generated content</p><h3><?= $e($selected->isPending() ? 'Withheld until decision' : 'Output') ?></h3></div></header>
      <?php if ($selected->isPending()): ?>
        <p>The generated payload remains hidden while this action is pending.</p>
        <div class="kontor-nativeform__actions">
          <form method="post" action="<?= $e($adminUrl) ?>ai-decide/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="action_uid" value="<?= $e($selected->uid->toString()) ?>">
            <input type="hidden" name="decision" value="approve">
            <button class="uk-button uk-button-primary kontor-button" type="submit">Approve action</button>
          </form>
          <form method="post" action="<?= $e($adminUrl) ?>ai-decide/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="action_uid" value="<?= $e($selected->uid->toString()) ?>">
            <input type="hidden" name="decision" value="reject">
            <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Reject action</button>
          </form>
        </div>
      <?php elseif ($selected->status === 'approved'): ?>
        <pre><code><?= $e(json_encode($selected->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
      <?php else: ?>
        <p>This action was rejected. Its generated payload remains hidden.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
