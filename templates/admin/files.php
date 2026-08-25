<?php

/** @var array<int, array<string, mixed>> $files */
/** @var array<string, mixed>|null $selected */
/** @var array<int, array<string, mixed>> $versions */
/** @var array{uid: string, url: string, expiresAt: string}|null $shareResult */
/** @var \Kontor\SDK\DTO\HealthCheckResult $storageHealth */
/** @var bool $canUpload */
/** @var bool $canDownload */
/** @var bool $canShare */
/** @var bool $canDelete */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / 1024 / 1024, 1) . ' MB';
};
$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y · H:i');
    } catch (Throwable) {
        return $value;
    }
};
$metadata = $selected !== null && is_string($selected['metadata_json'])
    ? json_decode($selected['metadata_json'], true)
    : [];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Infrastructure · Private storage</p>
      <h2>Files</h2>
      <p>Keep business documents private, manage their versions, and create short-lived downloads when needed.</p>
    </div>
    <span class="uk-label kontor-pill<?= $storageHealth->status === 'ok' ? '' : ' kontor-pill--inactive' ?>">
      Storage <?= $e($storageHealth->status) ?>
    </span>
  </header>

  <?php if ($storageHealth->status !== 'ok'): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><p><?= $e($storageHealth->message) ?></p></section>
  <?php endif; ?>

  <?php if ($canUpload): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Private by default · 25 MB max</p><h3>Upload file</h3></div></header>
      <form class="uk-form-stacked kontor-nativeform" method="post" enctype="multipart/form-data" action="<?= $e($adminUrl) ?>files-upload/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <label class="kontor-nativefield kontor-nativefield--wide"><span>File *</span><input type="file" name="file_upload" required></label>
        <label class="kontor-nativefield"><span>Visibility *</span>
          <select name="visibility" required><option value="private">Private</option><option value="internal">Internal</option></select>
        </label>
        <label class="kontor-nativefield"><span>Classification *</span>
          <select name="classification" required>
            <option value="general">General</option>
            <option value="financial">Financial</option>
            <option value="confidential">Confidential</option>
            <option value="restricted">Restricted</option>
          </select>
        </label>
        <div class="kontor-nativefield--wide">
          <ul uk-accordion>
            <li>
              <a class="uk-accordion-title" href>Advanced record connection</a>
              <div class="uk-accordion-content uk-form-stacked kontor-nativeform">
                <p class="uk-text-meta">Use this only when another component provides a record type and identifier.</p>
                <label class="kontor-nativefield"><span>Record type</span><input name="entity_type" maxlength="50" placeholder="document"></label>
                <label class="kontor-nativefield kontor-nativefield--wide"><span>Record identifier</span><input name="entity_uid" maxlength="26"></label>
                <label class="kontor-nativefield kontor-nativefield--wide"><span>Structured metadata</span><textarea name="metadata_json" rows="4" placeholder='{"source":"contract"}'></textarea></label>
              </div>
            </li>
          </ul>
        </div>
        <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Upload file</button></div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($shareResult !== null && $selected !== null && $shareResult['uid'] === $selected['uid']): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Expires <?= $e($shareResult['expiresAt']) ?></p><h3>Signed download ready</h3></div></header>
      <?php if ($canDownload): ?>
        <p><a class="uk-button uk-button-primary kontor-button" href="<?= $e($shareResult['url']) ?>">Download signed file</a></p>
      <?php else: ?><p>The link is signed, but your role cannot download files.</p><?php endif; ?>
      <p class="kontor-secondary">The URL grants access only until expiry and is validated against this organization before bytes are streamed.</p>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Document library</p><h3>Stored files</h3></div></header>
    <?php if ($files !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Name</th><th>Version</th><th class="uk-visible@m">Size</th><th class="uk-visible@m">Classification</th><th class="uk-visible@m">Entity</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($files as $file): ?><tr>
          <td><strong><a href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $file['uid'])) ?>"><?= $e($file['original_name']) ?></a></strong><span class="kontor-secondary"><?= $e($file['mime_type'] ?? 'unknown') ?></span></td>
          <td><?= $e($file['version_number']) ?></td>
          <td class="uk-visible@m"><?= $e($formatBytes((int) $file['size_bytes'])) ?></td>
          <td class="uk-visible@m"><?= $e($file['classification'] ?? '—') ?></td>
          <td class="uk-visible@m"><?= $e($file['entity_type'] !== null ? ucfirst(str_replace('_', ' ', (string) $file['entity_type'])) : 'Unattached') ?></td>
          <td><span class="uk-label kontor-pill<?= $file['archived_at'] !== null ? ' kontor-pill--inactive' : '' ?>"><?= $e($file['archived_at'] !== null ? 'archived' : 'active') ?></span></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-folder-open"></i><h3>No files yet</h3><p>Upload the first private file to establish its metadata and checksum.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m"><p class="kontor-eyebrow">Version <?= $e($selected['version_number']) ?> · <?= $e($selected['visibility']) ?></p><h3><?= $e($selected['original_name']) ?></h3></div>
        <div class="uk-width-auto@m"><div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
          <?php if ($selected['archived_at'] === null && $canShare): ?><div><form method="post" action="<?= $e($adminUrl) ?>files-share/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="file_uid" value="<?= $e($selected['uid']) ?>"><button class="uk-button uk-button-primary kontor-button" type="submit"><i class="fa fa-download"></i> Create download</button></form></div><?php endif; ?>
          <?php if ($canDelete): ?><div><form method="post" action="<?= $e($adminUrl) ?>files-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="file_uid" value="<?= $e($selected['uid']) ?>"><input type="hidden" name="action" value="<?= $e($selected['archived_at'] === null ? 'archive' : 'restore') ?>"><button class="uk-button uk-button-default" type="submit"><?= $e($selected['archived_at'] === null ? 'Archive' : 'Restore') ?></button></form></div><?php endif; ?>
        </div></div>
      </header>
      <div class="kontor-detailgrid">
        <div><span>Format</span><strong><?= $e($selected['mime_type'] ?? 'Unknown') ?></strong></div>
        <div><span>Size</span><strong><?= $e($formatBytes((int) $selected['size_bytes'])) ?></strong></div>
        <div><span>Classification</span><strong><?= $e($selected['classification'] ?? '—') ?></strong></div>
        <div><span>Added</span><strong><?= $e($formatDate((string) $selected['created_at'])) ?></strong></div>
        <div><span>Connected to</span><strong><?= $e($selected['entity_type'] !== null ? ucfirst(str_replace('_', ' ', (string) $selected['entity_type'])) : 'No record') ?></strong></div>
      </div>
      <ul class="uk-margin-top" uk-accordion><li><a class="uk-accordion-title" href>Technical details</a><div class="uk-accordion-content">
        <dl class="uk-description-list uk-description-list-divider">
          <dt>File identifier</dt><dd><code><?= $e($selected['uid']) ?></code></dd>
          <dt>Checksum</dt><dd><code><?= $e($selected['checksum']) ?></code></dd>
          <dt>Private storage path</dt><dd><code><?= $e($selected['path']) ?></code></dd>
          <?php if ($selected['entity_uid'] !== null): ?><dt>Connected record identifier</dt><dd><code><?= $e($selected['entity_uid']) ?></code></dd><?php endif; ?>
          <?php if (is_array($metadata) && $metadata !== []): ?><dt>Structured metadata</dt><dd><code><?= $e(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></dd><?php endif; ?>
        </dl>
      </div></li></ul>
    </section>

    <?php if ($versions !== []): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
        <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Same entity and filename</p><h3>Version history</h3></div></header>
        <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
          <thead><tr><th>Version</th><th>Created</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($versions as $version): ?><tr>
            <td><strong><a href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $version['uid'])) ?>">Version <?= $e($version['version_number']) ?></a></strong></td>
            <td><?= $e($formatDate((string) $version['created_at'])) ?></td>
            <td><span class="uk-label kontor-pill<?= $version['archived_at'] !== null ? ' kontor-pill--inactive' : '' ?>"><?= $e($version['archived_at'] !== null ? 'archived' : 'active') ?></span></td>
          </tr><?php endforeach; ?></tbody>
        </table>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
