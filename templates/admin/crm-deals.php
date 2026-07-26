<?php

/** @var \Kontor\CRM\Domain\Pipeline[] $pipelines */
/** @var \Kontor\CRM\Domain\Pipeline|null $pipeline */
/** @var array<int, array{stage: \Kontor\CRM\Domain\Stage, deals: \Kontor\CRM\Domain\Deal[]}> $columns */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$money = static function (?\Kontor\SDK\ValueObjects\Money $value): string {
    if ($value === null) {
        return '—';
    }

    return number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Revenue pipeline</p>
      <h2>CRM · Deals</h2>
      <p>Move opportunities through stages and close them as won or lost.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>crm/">
        <i class="fa fa-list"></i> Leads
      </a>
      <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>crm-pipeline/">
        <i class="fa fa-plus-square"></i> Pipeline
      </a>
      <?php if ($pipeline !== null): ?>
        <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>crm-deal/?pipeline=<?= $e(rawurlencode($pipeline->uid->toString())) ?>">
          <i class="fa fa-plus"></i> New deal
        </a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($pipelines !== []): ?>
    <nav class="kontor-pipelinenav" aria-label="Deal pipelines">
      <?php foreach ($pipelines as $item): ?>
        <a href="./?pipeline=<?= $e(rawurlencode($item->uid->toString())) ?>"<?= $pipeline?->uid->toString() === $item->uid->toString() ? ' aria-current="page"' : '' ?>>
          <?= $e($item->name) ?><?= $item->isDefault ? ' · default' : '' ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($pipeline === null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-empty-state uk-placeholder uk-text-center kontor-empty">
      <i class="fa fa-columns"></i>
      <h3>No pipeline yet</h3>
      <p>Create the first pipeline to start tracking deals.</p>
      <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>crm-pipeline/">Create pipeline</a>
    </section>
  <?php else: ?>
    <section class="kontor-kanban" aria-label="<?= $e($pipeline->name) ?> pipeline">
      <?php foreach ($columns as $column): ?>
        <?php $stage = $column['stage']; ?>
        <article class="kontor-kanban__column">
          <header>
            <strong><?= $e($stage->displayNameIn('en') ?? $stage->nameKey) ?></strong>
            <span><?= $e(count($column['deals'])) ?></span>
          </header>
          <div class="kontor-kanban__stack">
            <?php foreach ($column['deals'] as $deal): ?>
              <div class="kontor-dealcard">
                <strong><a href="<?= $e($adminUrl) ?>crm-deal/?id=<?= $e(rawurlencode($deal->uid->toString())) ?>"><?= $e($deal->title) ?></a></strong>
                <span><?= $e($money($deal->value)) ?> · <?= $e($deal->probability ?? $stage->probability) ?>%</span>
                <?php if ($deal->isOpen()): ?>
                  <form method="post" action="<?= $e($adminUrl) ?>crm-deal-action/">
                    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                    <input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>">
                    <input type="hidden" name="action" value="move">
                    <select name="stage_uid" aria-label="Move <?= $e($deal->title) ?> to stage">
                      <?php foreach ($columns as $target): ?>
                        <?php if ($target['stage']->stateType !== 'open') { continue; } ?>
                        <option value="<?= $e($target['stage']->uid->toString()) ?>"<?= $target['stage']->uid->toString() === $deal->stageUid ? ' selected' : '' ?>>
                          <?= $e($target['stage']->displayNameIn('en') ?? $target['stage']->nameKey) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" title="Move deal" aria-label="Move deal"><i class="fa fa-arrow-right"></i></button>
                  </form>
                  <div class="kontor-dealcard__actions">
                    <form method="post" action="<?= $e($adminUrl) ?>crm-deal-action/">
                      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                      <input type="hidden" name="id" value="<?= $e($deal->uid->toString()) ?>">
                      <button name="action" value="won" type="submit">Won</button>
                      <button name="action" value="lost" type="submit">Lost</button>
                      <button name="action" value="archive" type="submit" aria-label="Archive deal"><i class="fa fa-archive"></i></button>
                    </form>
                  </div>
                <?php else: ?>
                  <span class="uk-label kontor-pill kontor-pill--inactive"><?= $e($deal->status) ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if ($column['deals'] === []): ?><p class="kontor-kanban__empty">No deals</p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
