<?php

/** @var array<int, array{key: string, label: string}> $providers */
/** @var array<string, mixed>|null $preview */
/** @var bool $canExport */
/** @var bool $canImport */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$displayValue = static function (mixed $value) use ($e): string {
    if ($value === null || $value === '') {
        return '<span class="uk-text-muted">Not set</span>';
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    return $e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell kontor-settings-migration">
  <header class="kontor-formhead">
    <p class="kontor-eyebrow">Workspace portability</p>
    <h2>Settings migration</h2>
    <p>Move reviewed workspace defaults between Kontor installations without copying customers, transactions or credentials.</p>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-bottom">
    <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
      <div>
        <p class="kontor-eyebrow uk-margin-remove-bottom">Portable</p>
        <h3 class="uk-margin-small-top"><?= count($providers) ?> settings provider<?= count($providers) === 1 ? '' : 's' ?></h3>
        <p class="uk-text-meta">Only providers available on both sites can be applied.</p>
      </div>
      <div>
        <p class="kontor-eyebrow uk-margin-remove-bottom">Protected</p>
        <h3 class="uk-margin-small-top">Secrets excluded</h3>
        <p class="uk-text-meta">Passwords, tokens, API keys and mail credentials are rejected.</p>
      </div>
      <div>
        <p class="kontor-eyebrow uk-margin-remove-bottom">Controlled</p>
        <h3 class="uk-margin-small-top">Preview before apply</h3>
        <p class="uk-text-meta">Nothing changes until the reviewed preview is confirmed.</p>
      </div>
    </div>
  </section>

  <div class="uk-grid-match uk-child-width-1-2@m uk-grid-small" uk-grid>
    <div>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
        <header class="kontor-sectionhead">
          <div>
            <p class="kontor-eyebrow">Export</p>
            <h3>Download this workspace profile</h3>
            <p class="uk-text-meta">Creates a readable, versioned JSON file for review or another Kontor site.</p>
          </div>
          <span class="kontor-cardicon"><i class="fa fa-download"></i></span>
        </header>
        <ul class="uk-list uk-list-divider uk-margin">
          <?php foreach ($providers as $provider): ?>
            <li class="uk-flex uk-flex-between uk-flex-middle">
              <span><i class="fa fa-check-circle uk-text-success uk-margin-small-right"></i><?= $e($provider['label']) ?></span>
              <span class="uk-label kontor-pill">Included</span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($canExport): ?>
          <a class="uk-button uk-button-primary uk-width-1-1" href="<?= $e($adminUrl) ?>settings-export/">
            <i class="fa fa-download"></i> Download settings
          </a>
        <?php else: ?>
          <p class="uk-alert-warning uk-margin-remove-bottom" uk-alert>Export permission is required.</p>
        <?php endif; ?>
      </section>
    </div>

    <div>
      <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
        <header class="kontor-sectionhead">
          <div>
            <p class="kontor-eyebrow">Import</p>
            <h3>Check a settings profile</h3>
            <p class="uk-text-meta">Select a Kontor JSON export. The next step shows every proposed change.</p>
          </div>
          <span class="kontor-cardicon"><i class="fa fa-upload"></i></span>
        </header>
        <?php if ($canImport): ?>
          <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>settings-preview/" data-kontor-settings-import>
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <label class="uk-form-label" for="kontor-settings-file">Settings file</label>
            <div class="uk-form-custom uk-width-1-1" uk-form-custom="target: true">
              <input id="kontor-settings-file" type="file" accept="application/json,.json" required data-kontor-settings-file>
              <input class="uk-input uk-width-1-1" type="text" placeholder="Choose a .json profile" disabled>
            </div>
            <div class="uk-text-meta uk-margin-small-top">Maximum 1 MB. Business records and credentials are not accepted.</div>
            <textarea class="uk-hidden" name="settings_payload" data-kontor-settings-payload required></textarea>
            <button class="uk-button uk-button-default uk-width-1-1 uk-margin" type="submit" disabled data-kontor-settings-preview>
              <i class="fa fa-search"></i> Preview changes
            </button>
          </form>
        <?php else: ?>
          <p class="uk-alert-warning uk-margin-remove-bottom" uk-alert>Import permission is required.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <?php if (is_array($preview)): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card uk-margin-top">
      <header class="kontor-sectionhead">
        <div>
          <p class="kontor-eyebrow">Import preview</p>
          <h3><?= !empty($preview['successful']) ? 'Review proposed changes' : 'Profile needs attention' ?></h3>
          <p class="uk-text-meta"><?= (int) ($preview['changeCount'] ?? 0) ?> change<?= (int) ($preview['changeCount'] ?? 0) === 1 ? '' : 's' ?> detected. No settings have been changed yet.</p>
        </div>
        <span class="uk-label kontor-pill<?= !empty($preview['successful']) ? '' : ' kontor-pill--inactive' ?>">
          <?= !empty($preview['successful']) ? 'Ready' : 'Blocked' ?>
        </span>
      </header>

      <?php foreach ((array) ($preview['errors'] ?? []) as $error): ?>
        <div class="uk-alert-danger" uk-alert><p><?= $e($error) ?></p></div>
      <?php endforeach; ?>
      <?php foreach ((array) ($preview['warnings'] ?? []) as $warning): ?>
        <div class="uk-alert-warning" uk-alert><p><?= $e($warning) ?></p></div>
      <?php endforeach; ?>

      <?php foreach ((array) ($preview['providers'] ?? []) as $provider): ?>
        <article class="uk-margin">
          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
            <h4 class="uk-margin-remove"><?= $e($provider['label'] ?? $provider['key'] ?? 'Settings') ?></h4>
            <span class="uk-label kontor-pill"><?= $e(ucfirst((string) ($provider['status'] ?? 'ready'))) ?></span>
          </div>
          <?php foreach ((array) ($provider['errors'] ?? []) as $error): ?>
            <div class="uk-alert-danger uk-margin-small-top" uk-alert><p><?= $e($error) ?></p></div>
          <?php endforeach; ?>
          <?php if (!empty($provider['changes'])): ?>
            <div class="uk-overflow-auto uk-margin-small-top">
              <table class="uk-table uk-table-small uk-table-divider uk-table-middle kontor-table">
                <thead><tr><th>Setting</th><th>Current</th><th>Imported</th></tr></thead>
                <tbody>
                  <?php foreach ($provider['changes'] as $change): ?>
                    <tr>
                      <th><?= $e($change['label'] ?? $change['field'] ?? 'Setting') ?></th>
                      <td><?= $displayValue($change['from'] ?? null) ?></td>
                      <td><strong><?= $displayValue($change['to'] ?? null) ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php elseif (empty($provider['errors'])): ?>
            <p class="uk-text-muted uk-margin-small-top">This provider is already up to date.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <?php if ($canImport && !empty($preview['successful']) && !empty($preview['fingerprint'])): ?>
        <form class="uk-form-stacked uk-margin-top" method="post" action="<?= $e($adminUrl) ?>settings-apply/" data-kontor-confirm="Apply the reviewed workspace settings?">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="fingerprint" value="<?= $e($preview['fingerprint']) ?>">
          <label class="uk-flex uk-flex-middle uk-grid-small" uk-grid>
            <span><input class="uk-checkbox" type="checkbox" name="confirm_apply" value="1" required></span>
            <span>I reviewed the changes and understand that current workspace defaults will be updated.</span>
          </label>
          <button class="uk-button uk-button-primary uk-margin" type="submit">
            <i class="fa fa-check"></i> Apply reviewed settings
          </button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-kontor-settings-import]');
  if (!form) return;
  var file = form.querySelector('[data-kontor-settings-file]');
  var payload = form.querySelector('[data-kontor-settings-payload]');
  var button = form.querySelector('[data-kontor-settings-preview]');
  file.addEventListener('change', function () {
    payload.value = '';
    button.disabled = true;
    if (!file.files || !file.files[0] || file.files[0].size > 1048576) return;
    var reader = new FileReader();
    reader.addEventListener('load', function () {
      payload.value = typeof reader.result === 'string' ? reader.result : '';
      button.disabled = payload.value.length === 0;
    });
    reader.readAsText(file.files[0]);
  });
});
</script>
