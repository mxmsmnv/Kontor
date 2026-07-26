<?php

/** @var array<int, array{key: string, result: \Kontor\SDK\DTO\HealthCheckResult}> $checks */
/** @var array{ok: int, warning: int, critical: int} $counts */
/** @var string $overall */
/** @var string $query */
/** @var string $selectedStatus */
/** @var DateTimeImmutable $checkedAt */
/** @var string $adminUrl */
/** @var callable $e */

$overallLabel = [
    'ok' => 'All systems ready',
    'warning' => 'Attention recommended',
    'critical' => 'Action required',
][$overall];
$hasFilters = $query !== '' || $selectedStatus !== '';
$healthySelected = $query === '' && $selectedStatus === 'ok';
$warningSelected = $query === '' && $selectedStatus === 'warning';
$criticalSelected = $query === '' && $selectedStatus === 'critical';
$refreshQuery = http_build_query(array_filter([
    'q' => $query,
    'status' => $selectedStatus,
], static fn (string $value): bool => $value !== ''));
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
    <a class="kontor-button" href="./<?= $refreshQuery !== '' ? '?' . $e($refreshQuery) : '' ?>">
      <i class="fa fa-refresh"></i> Run checks again
    </a>
  </header>

  <section class="kontor-healthsummary kontor-healthsummary--<?= $e($overall) ?>">
    <span><i class="fa fa-<?= $overall === 'ok' ? 'check-circle' : ($overall === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i></span>
    <div>
      <strong><?= $e($overallLabel) ?></strong>
      <p>
        <a class="kontor-healthsummary__filter<?= $healthySelected ? ' kontor-healthsummary__filter--selected' : '' ?>" href="./?status=ok"<?= $healthySelected ? ' aria-current="page"' : '' ?>><?= $e($counts['ok']) ?> healthy</a>
        · <a class="kontor-healthsummary__filter<?= $warningSelected ? ' kontor-healthsummary__filter--selected' : '' ?>" href="./?status=warning"<?= $warningSelected ? ' aria-current="page"' : '' ?>><?= $e($counts['warning']) ?> warning</a>
        · <a class="kontor-healthsummary__filter<?= $criticalSelected ? ' kontor-healthsummary__filter--selected' : '' ?>" href="./?status=critical"<?= $criticalSelected ? ' aria-current="page"' : '' ?>><?= $e($counts['critical']) ?> critical</a>
      </p>
    </div>
    <time datetime="<?= $e($checkedAt->format(DATE_ATOM)) ?>">
      Checked <?= $e($checkedAt->format('M j, Y H:i:s')) ?>
    </time>
  </section>

  <form class="kontor-toolbar kontor-healthfilters" method="get" action="./">
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Component, message or detail">
    </label>
    <select name="status" aria-label="Health status">
      <option value="">All results</option>
      <option value="ok"<?= $selectedStatus === 'ok' ? ' selected' : '' ?>>Healthy</option>
      <option value="warning"<?= $selectedStatus === 'warning' ? ' selected' : '' ?>>Warning</option>
      <option value="critical"<?= $selectedStatus === 'critical' ? ' selected' : '' ?>>Critical</option>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($hasFilters): ?><a class="kontor-button kontor-button--ghost" href="./">Clear</a><?php endif; ?>
    <span class="kontor-secondary kontor-filtercount"><?= $e(count($checks)) ?> matching check<?= count($checks) === 1 ? '' : 's' ?></span>
  </form>

  <?php if ($checks): ?>
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
              <h3>
                <a href="<?= $e($adminUrl) ?>components/?q=<?= $e(rawurlencode($check['key'])) ?>">
                  <?= $e(ucwords(str_replace(['-', '_'], ' ', $check['key']))) ?>
                </a>
              </h3>
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
  <?php else: ?>
    <div class="kontor-card kontor-empty">
      <i class="fa fa-heartbeat"></i>
      <h3>No matching checks</h3>
      <p>The overall summary still reflects every health check from this run.</p>
    </div>
  <?php endif; ?>
</div>
