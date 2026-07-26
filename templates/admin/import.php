<?php

/** @var string $entityType */
/** @var \Kontor\Core\Domain\ImportBatchResult|null $result */
/** @var string|null $filename */
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
          <option value="contact"<?= $entityType === 'contact' ? ' selected' : '' ?>>Contacts</option>
          <option value="company"<?= $entityType === 'company' ? ' selected' : '' ?>>Companies</option>
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
      Preview mode is read-only. Live import remains disabled until a verified Contacts backup provider is available.
    </p>
  </section>

  <?php if ($result !== null): ?>
    <section class="kontor-import-result">
      <div class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Preview complete</p>
          <h3><?= $e($filename ?: 'Uploaded file') ?></h3>
        </div>
        <span class="kontor-pill<?= $result->failed > 0 ? ' kontor-pill--inactive' : '' ?>">
          <?= $result->failed > 0 ? $e($result->failed) . ' issues' : 'ready' ?>
        </span>
      </div>

      <div class="kontor-previewstats">
        <article><strong><?= $e($result->totalRows) ?></strong><span>Total rows</span></article>
        <article><strong><?= $e($result->created) ?></strong><span>Would create</span></article>
        <article><strong><?= $e($result->updated) ?></strong><span>Would update</span></article>
        <article><strong><?= $e($result->failed) ?></strong><span>Invalid rows</span></article>
      </div>

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
