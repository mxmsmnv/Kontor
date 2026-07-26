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
$resultJson = $result !== null
    ? json_encode($result['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : null;
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Infrastructure · Namespaced generations</p>
      <h2>Cache</h2>
      <p>Exercise the configured cache adapter, inspect hits and misses, and invalidate tag or namespace generations without enumerating keys.</p>
    </div>
    <span class="uk-label kontor-pill<?= $health->status === 'ok' ? '' : ' kontor-pill--inactive' ?>">Store <?= $e($health->status) ?></span>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Live adapter round-trip</p><h3>Store health</h3></div></header>
    <p><?= $e($health->message) ?></p>
    <div class="kontor-detailgrid">
      <div><span>Adapter</span><strong>ProcessWire WireCache</strong></div>
      <div><span>Persistence role</span><strong>Derived data only</strong></div>
      <div><span>External connection</span><strong>None</strong></div>
      <div><span>Namespace isolation</span><strong>Organization-scoped workbench</strong></div>
    </div>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Capability consumers</p><h3>Connected components</h3></div></header>
    <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
      <thead><tr><th>Component</th><th>Namespace</th><th>Policy</th><th>Status</th></tr></thead>
      <tbody><?php foreach ($consumers as $consumer): ?><tr>
        <td><strong><?= $e($consumer['name']) ?></strong></td>
        <td><code><?= $e($consumer['namespace']) ?></code></td>
        <td><?= $e($consumer['policy']) ?></td>
        <td><span class="uk-label kontor-pill<?= $consumer['status'] === 'connected' ? '' : ' kontor-pill--inactive' ?>"><?= $e($consumer['status']) ?></span></td>
      </tr><?php endforeach; ?></tbody>
    </table>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Operational workbench</p><h3>Read, write, and invalidate</h3></div></header>
    <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>cache-operate/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Namespace *</span><input name="namespace" maxlength="40" value="<?= $e($state['namespace']) ?>" required></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Key *</span><input name="key" maxlength="128" value="<?= $e($state['key']) ?>" required></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Tags</span><input name="tags" maxlength="409" value="<?= $e($state['tags']) ?>" placeholder="demo, customer-42"></label>
      <label class="kontor-nativefield"><span>TTL seconds</span><input name="ttl" type="number" min="0" max="86400" value="<?= $e($state['ttl']) ?>"></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>JSON value</span><textarea name="value_json" rows="7"><?= $e($state['valueJson']) ?></textarea></label>
      <p class="kontor-nativefield kontor-nativefield--wide">
        <span>Effective namespace</span>
        <code><?= $e($effectiveNamespacePrefix) ?><span data-kontor-cache-namespace><?= $e($state['namespace']) ?></span></code>
      </p>
      <div class="kontor-nativeform__actions">
        <?php if ($canManage): ?>
          <button class="uk-button uk-button-primary kontor-button" type="submit" name="action" value="set">Set value</button>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit" name="action" value="get">Get value</button>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit" name="action" value="delete">Delete key</button>
        <?php endif; ?>
        <?php if ($canFlush): ?>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit" name="action" value="flush-tag" formaction="<?= $e($adminUrl) ?>cache-flush/">Flush one tag</button>
          <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit" name="action" value="flush-namespace" formaction="<?= $e($adminUrl) ?>cache-flush/">Flush namespace</button>
        <?php endif; ?>
      </div>
    </form>
    <p class="kontor-secondary">Set and get must use the same tags. Invalidation advances a generation counter, making old entries unreachable while keeping the backend portable.</p>
  </section>

  <?php if ($result !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead">
        <div><p class="kontor-eyebrow"><?= $e($result['action']) ?></p><h3><?= $e($result['message']) ?></h3></div>
        <span class="uk-label kontor-pill<?= !empty($result['hit']) ? '' : ' kontor-pill--inactive' ?>"><?= $e(!empty($result['hit']) ? 'hit' : 'miss') ?></span>
      </header>
      <?php if (!empty($result['hit'])): ?><pre><code><?= $e($resultJson) ?></code></pre><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
