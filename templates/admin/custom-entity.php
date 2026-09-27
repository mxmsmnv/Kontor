<?php

/** @var \Kontor\Entities\Domain\EntityDefinition|null $definition */
/** @var array{entityKey: string, name: string, viewPermission: string, editPermission: string, apiExposed: bool} $values */
/** @var string $error */
/** @var \Kontor\Entities\Domain\EntityField[] $fields */
/** @var \Kontor\Entities\Domain\EntityView[] $views */
/** @var \Kontor\Entities\Domain\EntityView|null $selectedView */
/** @var \Kontor\Entities\Domain\EntityRecord[] $records */
/** @var array<string, mixed>|null $schema */
/** @var bool $canDefine */
/** @var bool $canManageRecords */
/** @var bool $canManageViews */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <?php if ($definition === null): ?>
    <header class="pw-module-head kontor-pagehead">
      <div>
        <p class="kontor-eyebrow">Workspace builder · First step</p>
        <h2>Create data workspace</h2>
        <p>Give the workspace a clear identity. After creation, you will add its fields, records and useful saved views.</p>
      </div>
      <div class="pw-module-actions kontor-pagehead__actions">
        <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>custom-entities/"><i class="fa fa-arrow-left"></i> Back to custom data</a>
      </div>
    </header>
  <?php else: ?>
    <header class="kontor-formhead"><a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>custom-entities/"><i class="fa fa-arrow-left"></i> Back to custom entities</a><p class="kontor-eyebrow">Extensibility · Schema builder</p><h2><?= $e($definition->name) ?></h2></header>
  <?php endif; ?>
  <?php if ($error !== ''): ?><div class="uk-alert uk-alert-warning kontor-warning"><strong><?= $e($error) ?></strong></div><?php endif; ?>
  <?php if ($definition === null): ?>
    <div class="uk-grid-medium" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <form class="uk-card uk-card-default uk-card-small uk-card-body uk-form-stacked" method="post" action="./">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">

          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Workspace identity</p>
          <h3 class="uk-card-title uk-margin-small-top">What will your team manage?</h3>
          <p class="uk-text-muted">Use a business concept people already recognize. One workspace should cover one type of information.</p>

          <div class="uk-margin">
            <label class="uk-form-label" for="entity-name">Workspace name <span aria-hidden="true">*</span></label>
            <input class="uk-input uk-margin-small-top" id="entity-name" name="name" value="<?= $e($values['name']) ?>" maxlength="191" placeholder="Equipment register" aria-describedby="entity-name-help" required autofocus>
            <div class="uk-text-meta uk-margin-small-top" id="entity-name-help">Shown to teammates in the workspace directory. Use a short plural or collective name, such as Equipment, Contracts or Site inspections.</div>
          </div>

          <div class="uk-margin">
            <label class="uk-form-label" for="entity-key">Workspace key <span aria-hidden="true">*</span></label>
            <input class="uk-input uk-margin-small-top" id="entity-key" name="entity_key" value="<?= $e($values['entityKey']) ?>" maxlength="64" pattern="[a-z][a-z0-9_-]{0,63}" placeholder="equipment" aria-describedby="entity-key-help" required autocomplete="off">
            <div class="uk-text-meta uk-margin-small-top" id="entity-key-help">A permanent lowercase reference used when this workspace connects to imports or automations. Start with a letter; use letters, numbers, hyphens or underscores.</div>
          </div>

          <hr>

          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connections</p>
          <h3 class="uk-card-title uk-margin-small-top">Choose how this workspace can be used</h3>
          <label class="uk-display-block uk-margin">
            <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="api_exposed" value="1"<?= $values['apiExposed'] ? ' checked' : '' ?>>
            <span><strong>Make available to integrations</strong><span class="uk-text-meta uk-display-block uk-margin-small-left">Allow authorized integrations to read and work with this workspace. Leave off when the data is only needed inside Kontor.</span></span>
          </label>

          <details class="uk-margin-medium-top">
            <summary class="uk-button uk-button-default uk-button-small"><i class="fa fa-lock"></i> Advanced access rules</summary>
            <div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-top">
              <p class="uk-text-muted uk-margin-remove-top">Keep these defaults unless your ProcessWire installation uses custom permissions for this workspace.</p>
              <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>
                <div>
                  <label class="uk-form-label" for="entity-view-permission">Who can view records</label>
                  <input class="uk-input uk-margin-small-top" id="entity-view-permission" name="view_permission" value="<?= $e($values['viewPermission']) ?>" autocomplete="off">
                  <div class="uk-text-meta uk-margin-small-top">ProcessWire permission name used to open records.</div>
                </div>
                <div>
                  <label class="uk-form-label" for="entity-edit-permission">Who can edit records</label>
                  <input class="uk-input uk-margin-small-top" id="entity-edit-permission" name="edit_permission" value="<?= $e($values['editPermission']) ?>" autocomplete="off">
                  <div class="uk-text-meta uk-margin-small-top">ProcessWire permission name used to create and update records.</div>
                </div>
              </div>
            </div>
          </details>

          <hr>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-auto@s uk-flex-right" uk-grid>
            <div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>custom-entities/">Cancel</a></div>
            <div><button class="uk-button uk-button-primary uk-width-1-1" type="submit" name="submit_save" value="1"><i class="fa fa-plus"></i> Create workspace</button></div>
          </div>
        </form>
      </div>

      <aside class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">What happens next</p>
          <h3 class="uk-card-title uk-margin-small-top">Build it one layer at a time</h3>
          <ul class="uk-list uk-list-divider">
            <li><strong>1. Add fields</strong><div class="uk-text-meta uk-margin-small-top">Define the information every record should contain.</div></li>
            <li><strong>2. Add records</strong><div class="uk-text-meta uk-margin-small-top">Start capturing consistent business data with your team.</div></li>
            <li><strong>3. Save views</strong><div class="uk-text-meta uk-margin-small-top">Create focused lists for recurring work and reporting.</div></li>
          </ul>
        </section>
        <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">A good first workspace</p>
          <h3 class="uk-h4 uk-margin-small-top">Start narrow</h3>
          <p class="uk-text-muted uk-margin-remove-bottom">Choose one process with a clear owner and a repeatable set of fields. You can add more fields and views later.</p>
        </section>
      </aside>
    </div>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><div class="kontor-detailgrid"><div><span>Entity key</span><strong><code><?= $e($definition->entityKey) ?></code></strong></div><div><span>Fields</span><strong><?= $e((string) count($fields)) ?></strong></div><div><span>Records</span><strong><?= $e((string) count($records)) ?></strong></div><div><span>API</span><strong><?= $definition->apiExposed ? 'exposed' : 'private' ?></strong></div></div><?php if ($schema !== null): ?><p><small>Schema contract: <code><?= $e(json_encode($schema, JSON_UNESCAPED_SLASHES)) ?></code></small></p><?php endif; ?></section>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Schema</p><h3>Fields</h3></div></header><?php if ($canDefine): ?><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>custom-entity-field/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield"><span>Field key *</span><input name="field_key" required></label><label class="kontor-nativefield"><span>Label *</span><input name="label" required></label><label class="kontor-nativefield"><span>Type *</span><select name="field_type"><?php foreach (\Kontor\Entities\Domain\EntityField::TYPES as $type): ?><option value="<?= $e($type) ?>"><?= $e($type) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span><input type="checkbox" name="required" value="1"> Required</span></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Add field</button></div></form><?php endif; ?><?php if ($fields !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th></tr></thead><tbody><?php foreach ($fields as $field): ?><tr><td><strong><?= $e($field->label) ?></strong></td><td><code><?= $e($field->fieldKey) ?></code></td><td><?= $e($field->fieldType) ?></td><td><?= $field->required ? 'yes' : 'no' ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>Add the first field to define this entity.</p></div><?php endif; ?></section>
    <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Records<?= $selectedView !== null ? ' · ' . $e($selectedView->name) : '' ?></p><h3>Data</h3></div><?php if ($canManageRecords && $fields !== []): ?><a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>custom-entity-record/?definition=<?= $e(rawurlencode($definition->uid->toString())) ?>"><i class="fa fa-plus"></i> New record</a><?php endif; ?></header><?php if ($views !== []): ?><p><a href="<?= $e($adminUrl) ?>custom-entity/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>">All records</a><?php foreach ($views as $view): ?> · <a href="<?= $e($adminUrl) ?>custom-entity/?id=<?= $e(rawurlencode($definition->uid->toString())) ?>&amp;view=<?= $e(rawurlencode($view->uid->toString())) ?>"><?= $e($view->name) ?></a><?php endforeach; ?></p><?php endif; ?><?php if ($records !== []): ?><table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><?php foreach ($fields as $field): ?><th><?= $e($field->label) ?></th><?php endforeach; ?><th>Updated</th></tr></thead><tbody><?php foreach ($records as $record): ?><tr><?php foreach ($fields as $field): ?><?php $value = $record->data[$field->fieldKey] ?? null; ?><td><a href="<?= $e($adminUrl) ?>custom-entity-record/?id=<?= $e(rawurlencode($record->uid->toString())) ?>"><?= $e(is_bool($value) ? ($value ? 'yes' : 'no') : (string) ($value ?? '—')) ?></a></td><?php endforeach; ?><td><?= $e($record->updatedAt->format('Y-m-d H:i')) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><p>No records in this view.</p></div><?php endif; ?></section>
    <?php if ($canManageViews && $fields !== []): ?><section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card"><header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Reusable query</p><h3>Create saved view</h3></div></header><form class="uk-form-stacked kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>custom-entity-view/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="definition_uid" value="<?= $e($definition->uid->toString()) ?>"><label class="kontor-nativefield"><span>Name *</span><input name="name" required></label><label class="kontor-nativefield"><span>Filter field</span><select name="filter_field"><option value="">None</option><?php foreach ($fields as $field): ?><option value="<?= $e($field->fieldKey) ?>"><?= $e($field->label) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>Operator</span><select name="filter_operator"><?php foreach (['equals', 'not_equals', 'greater_than', 'less_than', 'contains'] as $operator): ?><option value="<?= $e($operator) ?>"><?= $e(str_replace('_', ' ', $operator)) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>Filter value</span><input name="filter_value"></label><label class="kontor-nativefield"><span>Sort field</span><select name="sort_field"><option value="">None</option><?php foreach ($fields as $field): ?><option value="<?= $e($field->fieldKey) ?>"><?= $e($field->label) ?></option><?php endforeach; ?></select></label><label class="kontor-nativefield"><span>Direction</span><select name="sort_direction"><option value="asc">Ascending</option><option value="desc">Descending</option></select></label><div class="kontor-nativeform__actions"><button class="uk-button uk-button-secondary kontor-button kontor-button--ghost" type="submit">Create view</button></div></form></section><?php endif; ?>
  <?php endif; ?>
</div>
