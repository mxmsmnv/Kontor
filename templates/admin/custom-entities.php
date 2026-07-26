<?php

/** @var \Kontor\Entities\Domain\EntityDefinition[] $definitions */
/** @var array<string, int> $recordCounts */
/** @var bool $canManage */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead"><div><p class="kontor-eyebrow">Extensibility · Data model</p><h2>Custom entities</h2><p>Build schemas, capture records, save views, and connect them to the rest of Kontor.</p></div><?php if ($canManage): ?><div class="kontor-pagehead__actions"><a class="kontor-button" href="<?= $e($adminUrl) ?>custom-entity/"><i class="fa fa-plus"></i> New entity</a></div><?php endif; ?></header>
  <section class="kontor-card kontor-tablewrap"><?php if ($definitions !== []): ?><table class="kontor-table"><thead><tr><th>Entity</th><th>Key</th><th>Records</th><th>API</th><th>Status</th></tr></thead><tbody><?php foreach ($definitions as $definition): ?><tr><td><strong><a href="<?= $e($adminUrl) ?>custom-entity/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>"><?= $e($definition->name) ?></a></strong></td><td><code><?= $e($definition->entityKey) ?></code></td><td><?= $e((string) ($recordCounts[$definition->uid->toString()] ?? 0)) ?></td><td><?= $definition->apiExposed ? 'exposed' : 'private' ?></td><td><span class="kontor-pill"><?= $e($definition->status) ?></span></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="kontor-empty"><i class="fa fa-cube"></i><h3>No custom entities</h3><p>Create a schema, add fields, and start capturing records.</p></div><?php endif; ?></section>
</div>
