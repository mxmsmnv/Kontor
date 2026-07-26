<?php

/** @var string $schema */
/** @var array<string, \Kontor\GraphQL\DTO\GraphQLObjectType> $types */
/** @var array{status: int, body: array<string, mixed>, query: string}|null $result */
/** @var string $sampleQuery */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead"><div><p class="kontor-eyebrow">External ecosystem · Read API</p><h2>GraphQL</h2><p>Explore the shared API schema and run authenticated, complexity-limited queries.</p></div><div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>api/">Manage API tokens</a></div></header>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Shared resource registry</p><h3>Component types</h3></div></header><?php if ($types !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Resource</th><th>GraphQL type</th><th>Fields</th><th>Required scope</th></tr></thead><tbody><?php foreach ($types as $resourceKey => $type): ?><tr><td><strong><?= $e($resourceKey) ?></strong></td><td><code><?= $e($type->name) ?></code></td><td><?= $e(implode(', ', array_keys($type->fields))) ?></td><td><code><?= $e($resourceKey) ?>:read</code></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No API resources registered.</p></div><?php endif; ?><details><summary>Schema SDL</summary><pre><code><?= $e($schema) ?></code></pre></details></section>
  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">POST /graphql · max complexity 1000</p><h3>Query bench</h3></div></header><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>graphql-run/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><label class="kontor-nativefield kontor-nativefield--wide"><span>Bearer token *</span><input name="token" type="password" autocomplete="off" required></label><label class="kontor-nativefield kontor-nativefield--wide"><span>Query *</span><textarea name="query" rows="12" required><?= $e($result['query'] ?? $sampleQuery) ?></textarea></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Run query</button></div></form></section>
  <?php if ($result !== null): ?><section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">HTTP <?= $e((string) $result['status']) ?></p><h3>Response</h3></div></header><pre data-testid="graphql-response"><code><?= $e(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre></section><?php endif; ?>
</div>
