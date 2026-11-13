<?php if ($operationAi !== null): ?>
  <details class="uk-card uk-card-default uk-card-small uk-card-body uk-margin"<?= $operationAi['draft'] !== null || $operationAi['error'] !== null ? ' open' : '' ?>>
    <summary class="uk-text-bold"><?= $e($operationAi['title']) ?></summary>
    <form class="uk-form-stacked uk-margin-small-top" method="post" action="<?= $e($adminUrl . $operationAi['route']) ?>">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="uk-form-label" for="operation-ai-excerpt">Review the information to send to the configured AI provider</label>
      <textarea class="uk-textarea uk-margin-small" id="operation-ai-excerpt" name="operation_ai_excerpt" rows="6" maxlength="4000" required><?= $e($operationAi['excerpt']) ?></textarea>
      <button class="uk-button uk-button-default" type="submit" name="draft_operation_ai" value="1">Generate suggestion</button>
    </form>
    <?php if ($operationAi['error'] !== null): ?><p class="uk-text-danger" role="alert"><?= $e($operationAi['error']) ?></p><?php endif; ?>
    <?php if ($operationAi['draft'] !== null): ?>
      <label class="uk-form-label uk-margin-small-top" for="operation-ai-draft">Suggestion for review</label>
      <textarea class="uk-textarea" id="operation-ai-draft" rows="7"><?= $e($operationAi['draft']) ?></textarea>
    <?php endif; ?>
  </details>
<?php endif; ?>
