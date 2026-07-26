<?php

/** @var array<string, \Kontor\SDK\Contracts\ReportProviderInterface> $providers */
/** @var string $providerKey */
/** @var \Kontor\SDK\Contracts\ReportProviderInterface|null $provider */
/** @var array<string, string> $filters */
/** @var string[] $groupBy */
/** @var \Kontor\SDK\DTO\ReportResult|null $result */
/** @var bool $canExport */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$schema = $provider?->schema();
$formatValue = static function (mixed $value, ?string $type): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if ($type === 'money' && is_numeric($value)) {
        return number_format(((int) $value) / 100, 2, '.', '');
    }

    return (string) $value;
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Operational intelligence</p>
      <h2>Reports</h2>
      <p>Run organization-scoped reports provided by installed Kontor components.</p>
    </div>
  </header>

  <?php if ($providers === []): ?>
    <section class="kontor-card">
      <div class="kontor-empty">
        <i class="fa fa-bar-chart"></i>
        <h3>No report providers</h3>
        <p>Install or enable a component that contributes reports.</p>
      </div>
    </section>
  <?php else: ?>
    <form class="kontor-card kontor-nativeform" method="get" action="./">
      <label class="kontor-nativefield">
        <span>Report</span>
        <select name="provider" aria-label="Report">
          <?php foreach ($providers as $key => $candidate): ?>
            <option value="<?= $e($key) ?>"<?= $key === $providerKey ? ' selected' : '' ?>>
              <?= $e($candidate->title()) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <?php if ($schema !== null): ?>
        <?php foreach ($schema->filterableFields as $field): ?>
          <label class="kontor-nativefield">
            <span><?= $e(ucwords(str_replace('_', ' ', $field))) ?></span>
            <input name="filter_<?= $e($field) ?>" value="<?= $e($filters[$field] ?? '') ?>" placeholder="Any">
          </label>
        <?php endforeach; ?>

        <?php if ($schema->groupableFields !== []): ?>
          <label class="kontor-nativefield">
            <span>Group by</span>
            <select name="group_by" aria-label="Group by">
              <option value="">Provider default</option>
              <?php foreach ($schema->groupableFields as $field): ?>
                <option value="<?= $e($field) ?>"<?= in_array($field, $groupBy, true) ? ' selected' : '' ?>>
                  <?= $e(ucwords(str_replace('_', ' ', $field))) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
      <?php endif; ?>

      <div class="kontor-nativeform__actions">
        <button class="kontor-button" type="submit" name="run" value="1">
          <i class="fa fa-play"></i> Run report
        </button>
      </div>
    </form>

    <?php if ($result !== null && $schema !== null): ?>
      <section class="kontor-card kontor-tablewrap">
        <header class="kontor-sectionhead">
          <div>
            <p class="kontor-eyebrow">Report result</p>
            <h3><?= $e($provider?->title() ?? $providerKey) ?></h3>
            <p><?= $e(count($result->rows)) ?> row(s)</p>
          </div>
          <?php if ($canExport): ?>
            <form method="post" action="<?= $e($adminUrl) ?>reports-export/">
              <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
              <input type="hidden" name="provider" value="<?= $e($providerKey) ?>">
              <input type="hidden" name="filters_json" value="<?= $e(json_encode($filters, JSON_THROW_ON_ERROR)) ?>">
              <input type="hidden" name="group_by_json" value="<?= $e(json_encode($groupBy, JSON_THROW_ON_ERROR)) ?>">
              <button class="kontor-button kontor-button--ghost" type="submit">
                <i class="fa fa-download"></i> Export CSV
              </button>
            </form>
          <?php endif; ?>
        </header>

        <?php if ($result->rows !== []): ?>
          <table class="kontor-table">
            <thead>
              <tr>
                <?php foreach ($schema->fields as $field => $type): ?>
                  <th><?= $e(ucwords(str_replace('_', ' ', $field))) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result->rows as $row): ?>
                <tr>
                  <?php foreach ($schema->fields as $field => $type): ?>
                    <td><?= $e($formatValue($row[$field] ?? null, $type)) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <?php if ($result->totals !== []): ?>
              <tfoot>
                <tr>
                  <?php foreach ($schema->fields as $field => $type): ?>
                    <td>
                      <?php if (array_key_exists($field, $result->totals)): ?>
                        <?= $e($formatValue($result->totals[$field], $type)) ?>
                      <?php elseif ($field === array_key_first($schema->fields)): ?>
                        <strong>Total</strong>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        <?php else: ?>
          <div class="kontor-empty">
            <i class="fa fa-check-circle"></i>
            <h3>Report ran successfully</h3>
            <p>No rows matched the current organization and filters.</p>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
