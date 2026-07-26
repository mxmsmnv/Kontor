<?php

/** @var \Kontor\Documents\Domain\DocumentTemplate[] $templates */
/** @var \Kontor\Documents\Domain\DocumentTemplate|null $selected */
/** @var array<string, mixed>|null $preview */
/** @var bool $canCreate */
/** @var bool $canEdit */
/** @var bool $canArchive */
/** @var bool $canRender */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$defaultBody = <<<'HTML'
<main>
  <h1>{{title}}</h1>
  <p>Customer: {{customer.name}}</p>
  <ul>{{#each lines}}<li>{{description}} — {{amount}}</li>{{/each}}</ul>
  {{#if note}}<p>{{note}}</p>{{/if}}
</main>
HTML;
$defaultData = <<<'JSON'
{
  "title": "Document preview",
  "customer": {"name": "Example customer"},
  "lines": [
    {"description": "Consulting", "amount": "1,250.00 USD"}
  ],
  "note": "Thank you."
}
JSON;
$editing = $selected !== null;
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Business operations · Output</p>
      <h2>Documents</h2>
      <p>Versioned multilingual templates, HTML/PDF rendering, and immutable issue snapshots.</p>
    </div>
  </header>

  <?php if ($canCreate || $canEdit): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Designer v1</p><h3><?= $e($editing ? 'Publish a new version' : 'Publish template') ?></h3></div></header>
      <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>documents-publish/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <label class="kontor-nativefield"><span>Template key *</span><input name="template_key" value="<?= $e($selected?->templateKey ?? '') ?>" placeholder="invoice.standard" required></label>
        <label class="kontor-nativefield"><span>Document type *</span><input name="document_type" value="<?= $e($selected?->documentType ?? 'invoice') ?>" required></label>
        <label class="kontor-nativefield"><span>Language *</span><input name="language" value="<?= $e($selected?->language ?? 'en') ?>" maxlength="5" required></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Name *</span><input name="name" value="<?= $e($selected?->name ?? '') ?>" required></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Body HTML *</span><textarea name="body_html" rows="12" required><?= $e($selected?->bodyHtml ?? $defaultBody) ?></textarea></label>
        <label class="kontor-nativefield kontor-nativefield--wide"><span>Custom CSS</span><textarea name="custom_css" rows="6"><?= $e($selected?->customCss ?? 'body { font: 16px sans-serif; }') ?></textarea></label>
        <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Publish version</button></div>
      </form>
    </section>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Version ledger</p><h3>Templates</h3></div></header>
    <?php if ($templates !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Name</th><th>Key</th><th>Type</th><th>Language</th><th>Version</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($templates as $template): ?><tr>
        <td><strong><a href="<?= $e($adminUrl) ?>documents/?id=<?= $e(rawurlencode($template->uid->toString())) ?>"><?= $e($template->name) ?></a></strong></td>
        <td><code><?= $e($template->templateKey) ?></code></td>
        <td><?= $e($template->documentType) ?></td>
        <td><?= $e($template->language) ?></td>
        <td>v<?= $e((string) $template->versionNumber) ?></td>
        <td><?= $e($template->isArchived() ? 'archived' : 'current') ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No document templates.</p></div><?php endif; ?>
  </section>

  <?php if ($selected !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow"><?= $e($selected->documentType) ?> · <?= $e($selected->language) ?> · v<?= $e((string) $selected->versionNumber) ?></p><h3><?= $e($selected->name) ?></h3></div></header>
      <div class="kontor-detailgrid">
        <div><span>Template UID</span><strong><?= $e($selected->uid->toString()) ?></strong></div>
        <div><span>Family key</span><strong><?= $e($selected->templateKey) ?></strong></div>
        <div><span>Published</span><strong><?= $e($selected->createdAt->format('Y-m-d H:i:s')) ?></strong></div>
        <div><span>Status</span><strong><?= $e($selected->isArchived() ? 'archived' : 'current') ?></strong></div>
      </div>
      <?php if ($canArchive): ?><form method="post" action="<?= $e($adminUrl) ?>documents-archive/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="template_uid" value="<?= $e($selected->uid->toString()) ?>">
        <input type="hidden" name="action" value="<?= $e($selected->isArchived() ? 'restore' : 'archive') ?>">
        <button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit"><?= $e($selected->isArchived() ? 'Restore version' : 'Archive version') ?></button>
      </form><?php endif; ?>
    </section>

    <?php if ($canRender): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
        <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Rendering pipeline</p><h3>HTML, PDF, and snapshot</h3></div></header>
        <form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>documents-preview/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="template_uid" value="<?= $e($selected->uid->toString()) ?>">
          <label class="kontor-nativefield kontor-nativefield--wide"><span>Preview data (JSON) *</span><textarea name="data_json" rows="12" required><?= $e($preview['dataJson'] ?? $defaultData) ?></textarea></label>
          <div class="kontor-nativeform__actions"><button class="uk-button uk-button-primary kontor-button" type="submit">Render all formats</button></div>
        </form>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($preview !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Rendered output</p><h3>Preview</h3></div><div><strong><?= $e(number_format((int) $preview['pdfBytes'])) ?> PDF bytes</strong></div></header>
      <?php if (!empty($preview['fileUid'])): ?>
        <p><a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>files/?id=<?= $e(rawurlencode((string) $preview['fileUid'])) ?>">Open stored PDF · version <?= $e((string) $preview['fileVersion']) ?></a></p>
        <p class="kontor-secondary">The PDF and its immutable issue snapshot are stored privately by Kontor Files.</p>
      <?php endif; ?>
      <iframe class="kontor-document-preview" sandbox title="Document preview" srcdoc="<?= $e((string) $preview['html']) ?>"></iframe>
      <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Immutable issue payload</p><h3>Snapshot</h3></div></header>
      <pre><code><?= $e(json_encode($preview['snapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
    </section>
  <?php endif; ?>
</div>
