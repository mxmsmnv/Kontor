<details class="uk-card uk-card-default uk-card-small uk-card-body uk-margin"<?= $screenAi['draft'] !== '' || $screenAi['error'] !== '' ? ' open' : '' ?>>
  <summary class="uk-text-bold"><?= $e($screenAiTitle) ?></summary>
  <form class="uk-form-stacked uk-margin-small-top" method="post" action="<?= $e($screenAiUrl) ?>">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label class="uk-form-label" for="<?= $e($screenAiAction) ?>">Review the excerpt to send to the configured AI provider</label>
    <textarea class="uk-textarea uk-margin-small" id="<?= $e($screenAiAction) ?>" name="screen_ai_excerpt" rows="5" maxlength="4000" required><?= $e($screenAi['excerpt']) ?></textarea>
    <button class="uk-button uk-button-default" type="submit" name="<?= $e($screenAiAction) ?>" value="1">Generate suggestion</button>
  </form>
  <?php if ($screenAi['error'] !== ''): ?><p class="uk-text-danger" role="alert"><?= $e($screenAi['error']) ?></p><?php endif; ?>
  <?php if ($screenAi['draft'] !== ''): ?><div class="uk-margin-small-top"><?= nl2br($e($screenAi['draft'])) ?></div><?php endif; ?>
</details>
