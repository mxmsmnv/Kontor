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
$metadata = $selected !== null && is_string($selected['metadata_json'])
    ? json_decode($selected['metadata_json'], true)
    : [];
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Infrastructure · Private storage</p>
      <h2>Files</h2>
      <p>Upload business files, inspect immutable checksums, manage versions, and issue short-lived signed downloads.</p>
    </div>
    <span class="kontor-pill<?= $storageHealth->status === 'ok' ? '' : ' kontor-pill--inactive' ?>">
      Storage <?= $e($storageHealth->status) ?>
    </span>
  </header>

  <?php if ($storageHealth->status !== 'ok'): ?>
    <section class="kontor-card"><p><?= $e($storageHealth->message) ?></p></section>
  <?php endif; ?>

  <?php if ($canUpload): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Private by default · 25 MB max</p><h3>Upload file</h3></div></header>
      <form class="kontor-nativeform" method="post" enctype="multipart/form-data" action="<?= $e($adminUrl) ?>files-upload/">
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
        <label class="kontor-nativefield"><span>Entity type</span><input name="entity_type" maxlength="50" placeholder="document"></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Entity UID</span><input name="entity_uid" maxlength="26" placeholder="ULID; required with entity type"></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Metadata JSON</span><textarea name="metadata_json" rows="4" placeholder='{"source":"contract"}'></textarea></label>
        <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Upload file</button></div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($shareResult !== null && $selected !== null && $shareResult['uid'] === $selected['uid']): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Expires <?= $e($shareResult['expiresAt']) ?></p><h3>Signed download ready</h3></div></header>
      <?php if ($canDownload): ?>
        <p><a class="kontor-button" href="<?= $e($shareResult['url']) ?>">Download signed file</a></p>
      <?php else: ?><p>The link is signed, but your role cannot download files.</p><?php endif; ?>
      <p class="kontor-secondary">The URL grants access only until expiry and is validated against this organization before bytes are streamed.</p>
    </section>
  <?php endif; ?>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Metadata index</p><h3>Stored files</h3></div></header>
    <?php if ($files !== []): ?>
      <table class="kontor-table">
        <thead><tr><th>Name</th><th>Version</th><th>Size</th><th>Classification</th><th>Entity</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($files as $file): ?><tr>
          <td><strong><a href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $file['uid'])) ?>"><?= $e($file['original_name']) ?></a></strong><span class="kontor-secondary"><?= $e($file['mime_type'] ?? 'unknown') ?></span></td>
          <td><?= $e($file['version_number']) ?></td>
          <td><?= $e($formatBytes((int) $file['size_bytes'])) ?></td>
          <td><?= $e($file['classification'] ?? '—') ?></td>
          <td><?= $e($file['entity_type'] !== null ? $file['entity_type'] . ' · ' . $file['entity_uid'] : 'Unattached') ?></td>
          <td><span class="kontor-pill<?= $file['archived_at'] !== null ? ' kontor-pill--inactive' : '' ?>"><?= $e($file['archived_at'] !== null ? 'archived' : 'active') ?></span></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    <?php else: ?><div class="kontor-empty"><i class="fa fa-folder-open"></i><h3>No files yet</h3><p>Upload the first private file to establish its metadata and checksum.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Version <?= $e($selected['version_number']) ?> · <?= $e($selected['visibility']) ?></p><h3><?= $e($selected['original_name']) ?></h3></div></header>
      <div class="kontor-detailgrid">
        <div><span>File UID</span><strong><?= $e($selected['uid']) ?></strong></div>
        <div><span>Storage</span><strong><?= $e($selected['storage']) ?></strong></div>
        <div><span>MIME type</span><strong><?= $e($selected['mime_type'] ?? 'unknown') ?></strong></div>
        <div><span>Size</span><strong><?= $e($formatBytes((int) $selected['size_bytes'])) ?></strong></div>
        <div><span>Classification</span><strong><?= $e($selected['classification'] ?? '—') ?></strong></div>
        <div><span>Created</span><strong><?= $e($selected['created_at']) ?></strong></div>
        <div><span>Entity type</span><strong><?= $e($selected['entity_type'] ?? '—') ?></strong></div>
        <div><span>Entity UID</span><strong><?= $e($selected['entity_uid'] ?? '—') ?></strong></div>
      </div>
      <p><strong>SHA-256</strong><br><code><?= $e($selected['checksum']) ?></code></p>
      <p><strong>Private storage path</strong><br><code><?= $e($selected['path']) ?></code></p>
      <?php if (is_array($metadata) && $metadata !== []): ?><p><strong>Metadata</strong><br><code><?= $e(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></p><?php endif; ?>
      <div class="kontor-actions">
        <?php if ($selected['archived_at'] === null && $canShare): ?>
          <form method="post" action="<?= $e($adminUrl) ?>files-share/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="file_uid" value="<?= $e($selected['uid']) ?>">
            <button class="kontor-button" type="submit">Create 15-minute download</button>
          </form>
        <?php endif; ?>
        <?php if ($canDelete): ?>
          <form method="post" action="<?= $e($adminUrl) ?>files-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="file_uid" value="<?= $e($selected['uid']) ?>">
            <input type="hidden" name="action" value="<?= $e($selected['archived_at'] === null ? 'archive' : 'restore') ?>">
            <button class="kontor-button kontor-button--ghost" type="submit"><?= $e($selected['archived_at'] === null ? 'Archive' : 'Restore') ?></button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($versions !== []): ?>
      <section class="kontor-card kontor-tablewrap">
        <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Same entity and filename</p><h3>Version history</h3></div></header>
        <table class="kontor-table">
          <thead><tr><th>Version</th><th>Checksum</th><th>Created</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($versions as $version): ?><tr>
            <td><strong><a href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $version['uid'])) ?>">Version <?= $e($version['version_number']) ?></a></strong></td>
            <td><code><?= $e(substr((string) $version['checksum'], 0, 16)) ?>…</code></td>
            <td><?= $e($version['created_at']) ?></td>
            <td><span class="kontor-pill<?= $version['archived_at'] !== null ? ' kontor-pill--inactive' : '' ?>"><?= $e($version['archived_at'] !== null ? 'archived' : 'active') ?></span></td>
          </tr><?php endforeach; ?></tbody>
        </table>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
