<?php

/** @var array<int, array<string, mixed>> $intakeFields */
/** @var array<string, mixed> $intakeValues */
/** @var callable $e */

$groups = [];
foreach ($intakeFields as $intakeField) {
    $groups[$intakeField['group'] ?: 'Qualification'][] = $intakeField;
}
?>
<?php foreach ($groups as $groupLabel => $groupFields): ?>
  <div class="uk-width-1-1"><h4 class="uk-heading-line uk-text-small uk-text-uppercase"><span><?= $e($groupLabel) ?></span></h4></div>
  <?php foreach ($groupFields as $intakeField): ?>
    <?php
    $key = (string) $intakeField['key'];
    $name = 'crm_intake__' . $key;
    $type = (string) $intakeField['type'];
    $value = $intakeValues[$key] ?? ($type === 'multiselect' ? [] : '');
    $wide = in_array($type, ['textarea', 'multiselect'], true);
    ?>
    <label class="kontor-nativefield<?= $wide ? ' kontor-nativefield--wide' : '' ?>">
      <span><?= $e($intakeField['label']) ?><?= $intakeField['required'] ? ' *' : '' ?></span>
      <?php if ($intakeField['description'] !== ''): ?><small class="kontor-field-description"><?= $e($intakeField['description']) ?></small><?php endif; ?>
      <?php if ($type === 'textarea'): ?>
        <textarea class="uk-textarea" name="<?= $e($name) ?>" rows="4"<?= $intakeField['required'] ? ' required' : '' ?>><?= $e((string) $value) ?></textarea>
      <?php elseif ($type === 'select'): ?>
        <select class="uk-select" name="<?= $e($name) ?>"<?= $intakeField['required'] ? ' required' : '' ?>>
          <option value="">Choose an option</option>
          <?php foreach ($intakeField['options'] as $optionValue => $optionLabel): ?><option value="<?= $e($optionValue) ?>"<?= (string) $value === (string) $optionValue ? ' selected' : '' ?>><?= $e($optionLabel) ?></option><?php endforeach; ?>
        </select>
      <?php elseif ($type === 'multiselect'): ?>
        <div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@l" uk-grid>
          <?php foreach ($intakeField['options'] as $optionValue => $optionLabel): ?><div><label class="uk-flex uk-flex-middle uk-grid-small" uk-grid><span><input class="uk-checkbox" type="checkbox" name="<?= $e($name) ?>[]" value="<?= $e($optionValue) ?>"<?= in_array($optionValue, (array) $value, true) ? ' checked' : '' ?>></span><span><?= $e($optionLabel) ?></span></label></div><?php endforeach; ?>
        </div>
      <?php else: ?>
        <input class="uk-input" type="<?= $e($type === 'datetime' ? 'datetime-local' : ($type === 'url' ? 'url' : 'text')) ?>" name="<?= $e($name) ?>" value="<?= $e((string) $value) ?>"<?= $intakeField['required'] ? ' required' : '' ?>>
      <?php endif; ?>
      <?php if ($intakeField['note'] !== ''): ?><small class="kontor-field-note"><strong>Note:</strong> <?= $e($intakeField['note']) ?></small><?php endif; ?>
    </label>
  <?php endforeach; ?>
<?php endforeach; ?>
