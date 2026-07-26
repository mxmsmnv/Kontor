<?php

/** @var string $entityType */
/** @var \Kontor\Core\Domain\ImportBatchResult|null $result */
/** @var string|null $filename */
/** @var string|null $previewToken */
/** @var string|null $backupId */
/** @var array<string, string> $availableEntityTypes */
/** @var string $backupLabel */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Data exchange</p>
      <h2>Import preview</h2>
      <p>Validate a CSV, JSON, JSON Lines or XLSX file before any records are changed.</p>
    </div>
  </header>

  <section class="kontor-card kontor-import">
    <form method="post" action="./" enctype="multipart/form-data">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label>
        <span>Record type</span>
        <select name="entity">
          <?php foreach ($availableEntityTypes as $value => $label): ?>
            <option value="<?= $e($value) ?>"<?= $entityType === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="kontor-import__file">
        <span>Source file</span>
        <input type="file" name="import_file" required accept=".csv,.json,.jsonl,.ndjson,.xlsx">
        <small>Maximum 10 MB. Column names must use Kontor machine field names.</small>
      </label>
      <button class="kontor-button" type="submit">
        <i class="fa fa-search"></i> Preview import
      </button>
    </form>
    <p class="kontor-import__notice">
      <i class="fa fa-shield"></i>
      Preview mode is read-only. A live import can start only after Kontor creates and verifies a <?= $e($backupLabel) ?> snapshot.
    </p>
  </section>

  <?php if ($result !== null): ?>
    <section class="kontor-import-result">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow"><?= $result->dryRun ? 'Preview complete' : 'Import complete' ?></p>
          <h3><?= $e($filename ?: 'Uploaded file') ?></h3>
        </div>
        <span class="kontor-pill<?= $result->failed > 0 ? ' kontor-pill--inactive' : '' ?>">
          <?= $result->failed > 0 ? $e($result->failed) . ' issues' : ($result->dryRun ? 'ready' : 'imported') ?>
        </span>
      </div>

      <div class="kontor-previewstats">
        <article><strong><?= $e($result->totalRows) ?></strong><span>Total rows</span></article>
        <article><strong><?= $e($result->created) ?></strong><span><?= $result->dryRun ? 'Would create' : 'Created' ?></span></article>
        <article><strong><?= $e($result->updated) ?></strong><span><?= $result->dryRun ? 'Would update' : 'Updated' ?></span></article>
        <article><strong><?= $e($result->failed) ?></strong><span>Invalid rows</span></article>
      </div>

      <?php if ($result->dryRun && $previewToken !== null): ?>
        <form class="kontor-import-confirm" method="post" action="./">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="entity" value="<?= $e($entityType) ?>">
          <input type="hidden" name="preview_token" value="<?= $e($previewToken) ?>">
          <input type="hidden" name="commit_import" value="1">
          <div>
            <strong>Ready to import <?= $e($result->totalRows) ?> rows</strong>
            <span>Kontor will create and verify a complete <?= $e($backupLabel) ?> snapshot before changing any data.</span>
          </div>
          <button class="kontor-button" type="submit" onclick="return confirm('Create a verified backup and import these rows?')">
            <i class="fa fa-shield"></i> Back up and import
          </button>
        </form>
      <?php elseif (!$result->dryRun && $backupId !== null): ?>
        <p class="kontor-import-success">
          <i class="fa fa-check-circle"></i>
          Import completed after verified backup <code><?= $e($backupId) ?></code>.
        </p>
      <?php elseif ($result->dryRun && $result->failed > 0): ?>
        <p class="kontor-import__notice">
          Fix every invalid row and preview the file again before importing.
        </p>
      <?php endif; ?>

      <?php if ($result->rows): ?>
        <div class="kontor-card kontor-tablewrap">
          <table class="kontor-table">
            <thead>
              <tr>
                <th>Row</th>
                <th>Outcome</th>
                <th>Existing record</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($result->rows, 0, 100) as $row): ?>
                <tr>
                  <td><?= $e($row->rowNumber) ?></td>
                  <td><span class="kontor-pill<?= $row->outcome === 'failed' ? ' kontor-pill--inactive' : '' ?>"><?= $e(str_replace('_', ' ', $row->outcome)) ?></span></td>
                  <td><?= $e($row->entityUid ?: '—') ?></td>
                  <td><?= $e($row->errorMessage ?: 'Validated') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($result->rows) > 100): ?>
          <p class="kontor-secondary">Showing the first 100 preview rows.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
