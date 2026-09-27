<?php

/** @var \Kontor\SDK\DTO\HealthCheckResult $health */
/** @var array<string, mixed> $state */
/** @var string $effectiveNamespacePrefix */
/** @var array<int, array{name: string, status: string, namespace: string, policy: string}> $consumers */
/** @var bool $canManage */
/** @var bool $canFlush */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$result = is_array($state['result'] ?? null) ? $state['result'] : null;
$resultJson = $result !== null && array_key_exists('value', $result)
    ? json_encode($result['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : null;
$ready = $health->status === 'ok';
$connectedCount = count(array_filter(
    $consumers,
    static fn (array $consumer): bool => $consumer['status'] === 'connected',
));
$statusClass = static fn (string $status): string => match ($status) {
    'connected', 'ok' => ' uk-label-success',
    'warning' => ' uk-label-warning',
    'critical' => ' uk-label-danger',
    default => '',
};
$statusLabel = static fn (string $status): string => match ($status) {
    'connected' => 'Active',
    'unavailable' => 'Not installed',
    'ok' => 'Ready',
    default => ucfirst($status),
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Performance · Cache</p><h2>Cache</h2><p>Keep frequently used views responsive and safely refresh derived data when business records change.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><span class="uk-label<?= $statusClass($health->status) ?>"><i class="fa fa-<?= $ready ? 'check-circle' : 'exclamation-triangle' ?>"></i> <?= $e($statusLabel($health->status)) ?></span></div>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
    <div class="uk-grid-medium uk-flex-middle" uk-grid><div class="uk-width-expand"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Service status</p><h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $ready ? 'Cache is ready' : 'Cache needs attention' ?></h3><p class="uk-text-muted uk-margin-remove"><?= $e($health->message) ?></p></div><div class="uk-width-auto"><span class="kontor-stat__icon<?= $ready ? ' kontor-stat__icon--success' : ' kontor-stat__icon--danger' ?>"><i class="fa fa-<?= $ready ? 'shield' : 'exclamation-triangle' ?>"></i></span></div></div>
    <hr>
    <div class="uk-grid-small uk-grid-divider uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>
      <div><div class="uk-text-meta">Storage</div><strong>ProcessWire cache</strong><div class="uk-text-meta uk-margin-small-top">Uses the site's configured cache storage.</div></div>
      <div><div class="uk-text-meta">Data safety</div><strong>Derived data only</strong><div class="uk-text-meta uk-margin-small-top">Business records remain the source of truth.</div></div>
      <div><div class="uk-text-meta">Isolation</div><strong>Organization protected</strong><div class="uk-text-meta uk-margin-small-top">Workbench entries remain separated by organization.</div></div>
    </div>
  </section>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $ready ? ' kontor-stat__icon--success' : ' kontor-stat__icon--danger' ?>"><i class="fa fa-heartbeat"></i></span><span><strong class="kontor-stat__value"><?= $e($ready ? 'Ready' : 'Check') ?></strong><span class="kontor-stat__label">Storage health</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-plug"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $connectedCount) ?></strong><span class="kontor-stat__label">Connected components</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-lock"></i></span><span><strong class="kontor-stat__value">Safe</strong><span class="kontor-stat__label">Source records unaffected</span></span></div></div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body">
    <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected functionality</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Where cache improves Kontor</h3><p class="uk-text-muted uk-margin-small-top">Each component owns its cached results and refresh policy independently.</p></div>
    <?php if ($consumers !== []): ?><ul class="uk-list uk-list-divider uk-margin-remove-bottom"><?php foreach ($consumers as $consumer): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($consumer['name']) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($consumer['policy']) ?></div></div><div><span class="uk-label<?= $statusClass($consumer['status']) ?>"><?= $e($statusLabel($consumer['status'])) ?></span></div></div><details class="uk-margin-small-top"><summary class="uk-button uk-button-text uk-text-meta">Technical details</summary><div class="uk-text-meta uk-margin-small-top">Internal namespace: <?= $e($consumer['namespace']) ?></div></details></li><?php endforeach; ?></ul>
    <?php else: ?><div class="uk-placeholder uk-text-center"><span class="fa fa-plug fa-2x uk-text-muted"></span><h3 class="uk-margin-small-top uk-margin-small-bottom">No connected components</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Components that use shared caching will appear here.</p></div><?php endif; ?>
  </section>

  <?php if ($result !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Last operation</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom"><?= $e($result['message']) ?></h3></div><div><span class="uk-label<?= !empty($result['hit']) ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $e(!empty($result['hit']) ? 'Found' : 'Not found') ?></span></div></div>
      <?php if (!empty($result['hit']) && $resultJson !== null): ?><details class="uk-margin-top"><summary class="uk-button uk-button-default">View stored value</summary><pre class="uk-margin-small-top"><code><?= $e($resultJson) ?></code></pre></details><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canManage || $canFlush): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Administration</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Cache tools</h3><p class="uk-text-muted uk-margin-small-top">Inspect or refresh a known cache entry. These tools are intended for diagnostics and recovery.</p></div>
      <details class="uk-margin-top">
        <summary class="uk-button uk-button-default"><i class="fa fa-wrench"></i> Open advanced tools</summary>
        <form class="uk-form-stacked uk-margin" method="post" action="<?= $e($adminUrl) ?>cache-operate/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Target</p><h4 class="uk-margin-small-top">Choose the cache entry</h4>
          <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="cache-namespace">Namespace</label><input class="uk-input uk-margin-small-top" id="cache-namespace" name="namespace" maxlength="40" value="<?= $e($state['namespace']) ?>" required><div class="uk-text-meta uk-margin-small-top">Logical area that owns the entry, such as operations or search.</div></div>
            <div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="cache-key">Entry key</label><input class="uk-input uk-margin-small-top" id="cache-key" name="key" maxlength="128" value="<?= $e($state['key']) ?>" required><div class="uk-text-meta uk-margin-small-top">Exact key supplied by the component or diagnostic procedure.</div></div>
            <div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="cache-tags">Tags <span class="uk-text-meta">Optional</span></label><input class="uk-input uk-margin-small-top" id="cache-tags" name="tags" maxlength="409" value="<?= $e($state['tags']) ?>" placeholder="customer, search-results"><div class="uk-text-meta uk-margin-small-top">Comma-separated groups. Reading requires the same tags used when storing.</div></div>
            <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="cache-ttl">Expires after (seconds)</label><input class="uk-input uk-margin-small-top" id="cache-ttl" name="ttl" type="number" min="0" max="86400" value="<?= $e($state['ttl']) ?>"><div class="uk-text-meta uk-margin-small-top">Use 0 only when the entry should not expire automatically.</div></div>
          </div>

          <?php if ($canManage): ?><hr><div class="uk-grid-medium" uk-grid><div class="uk-width-1-1 uk-width-2-3@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Stored value</p><h4 class="uk-margin-small-top">Inspect or update</h4><label class="uk-form-label" for="cache-value">Structured value</label><textarea class="uk-textarea uk-margin-small-top" id="cache-value" name="value_json" rows="7"><?= $e($state['valueJson']) ?></textarea><div class="uk-text-meta uk-margin-small-top">Use valid JSON. Store replaces the selected entry; Inspect does not change it.</div></div><div class="uk-width-1-1 uk-width-1-3@m"><div class="uk-flex uk-flex-column uk-margin-medium-top"><button class="uk-button uk-button-primary uk-margin-small-bottom" type="submit" name="action" value="get"><i class="fa fa-search"></i> Inspect entry</button><button class="uk-button uk-button-default" type="submit" name="action" value="set" data-kontor-confirm="Store this value in the selected cache entry?"><i class="fa fa-save"></i> Store value</button></div></div></div><?php else: ?><textarea name="value_json" hidden><?= $e($state['valueJson']) ?></textarea><?php endif; ?>

          <?php if ($canManage || $canFlush): ?><details class="uk-margin-large-top"><summary class="uk-button uk-button-text uk-text-danger">Danger zone</summary><div class="uk-alert-warning uk-margin" uk-alert><p><strong>Invalidation may temporarily slow affected views.</strong> Source business records are not deleted.</p></div><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid><?php if ($canManage): ?><div><button class="uk-button uk-button-danger" type="submit" name="action" value="delete" data-kontor-confirm="Delete this cache entry? It will be rebuilt when requested again.">Delete entry</button></div><?php endif; ?><?php if ($canFlush): ?><div><button class="uk-button uk-button-danger" type="submit" name="action" value="flush-tag" formaction="<?= $e($adminUrl) ?>cache-flush/" data-kontor-confirm="Refresh every cache entry with this tag?">Refresh one tag</button></div><div><button class="uk-button uk-button-danger" type="submit" name="action" value="flush-namespace" formaction="<?= $e($adminUrl) ?>cache-flush/" data-kontor-confirm="Refresh the entire selected namespace?">Refresh namespace</button></div><?php endif; ?></div><div class="uk-text-meta uk-margin-small-top">Refreshing a tag requires exactly one value in the Tags field.</div></details><?php endif; ?>

          <details class="uk-margin-top"><summary class="uk-button uk-button-text uk-text-meta">Resolved technical target</summary><div class="uk-text-meta uk-margin-small-top"><?= $e($effectiveNamespacePrefix) ?><span data-kontor-cache-namespace><?= $e($state['namespace']) ?></span></div></details>
        </form>
      </details>
    </section>
  <?php endif; ?>
</div>
