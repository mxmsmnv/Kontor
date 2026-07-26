<?php

/** @var array<int, array{key: string, result: \Kontor\SDK\DTO\HealthCheckResult}> $checks */
/** @var DateTimeImmutable $checkedAt */
/** @var callable $e */

$counts = ['ok' => 0, 'warning' => 0, 'critical' => 0];

foreach ($checks as $check) {
    $counts[$check['result']->status]++;
}

$overall = $counts['critical'] > 0 ? 'critical' : ($counts['warning'] > 0 ? 'warning' : 'ok');
$overallLabel = [
    'ok' => 'All systems ready',
    'warning' => 'Attention recommended',
    'critical' => 'Action required',
][$overall];
$displayValue = static function (mixed $value): string {
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_array($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return (string) $value;
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Diagnostics</p>
      <h2>System health</h2>
      <p>Live checks across Core and every installed component that exposes diagnostics.</p>
    </div>
    <a class="kontor-button" href="./"><i class="fa fa-refresh"></i> Run checks again</a>
  </header>

  <section class="kontor-healthsummary kontor-healthsummary--<?= $e($overall) ?>">
    <span><i class="fa fa-<?= $overall === 'ok' ? 'check-circle' : ($overall === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i></span>
    <div>
      <strong><?= $e($overallLabel) ?></strong>
      <p>
        <?= $e($counts['ok']) ?> healthy
        · <?= $e($counts['warning']) ?> warning
        · <?= $e($counts['critical']) ?> critical
      </p>
    </div>
    <time datetime="<?= $e($checkedAt->format(DATE_ATOM)) ?>">
      Checked <?= $e($checkedAt->format('M j, Y H:i:s')) ?>
    </time>
  </section>

  <section class="kontor-healthgrid">
    <?php foreach ($checks as $check): ?>
      <?php $result = $check['result']; ?>
      <article class="kontor-card kontor-healthcheck kontor-healthcheck--<?= $e($result->status) ?>">
        <header>
          <span class="kontor-healthcheck__icon">
            <i class="fa fa-<?= $result->status === 'ok' ? 'check' : ($result->status === 'warning' ? 'exclamation' : 'times') ?>"></i>
          </span>
          <div>
            <p class="kontor-eyebrow">Component check</p>
            <h3><?= $e(ucwords(str_replace(['-', '_'], ' ', $check['key']))) ?></h3>
          </div>
          <span class="kontor-pill kontor-pill--<?= $e($result->status) ?>"><?= $e($result->status) ?></span>
        </header>
        <p class="kontor-healthcheck__message"><?= $e($result->message) ?></p>

        <?php if ($result->details): ?>
          <dl>
            <?php foreach ($result->details as $name => $value): ?>
              <div>
                <dt><?= $e(ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $name) ?? (string) $name)) ?></dt>
                <dd><?= $e($displayValue($value)) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
</div>
