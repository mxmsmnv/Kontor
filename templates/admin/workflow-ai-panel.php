<details class="uk-card uk-card-default uk-card-small uk-card-body uk-margin">
  <summary class="uk-text-bold">AI assistant</summary>
  <p><?= $e(\Kontor\Core\Application\WorkflowAIContexts::PROMPTS[$aiContext]) ?></p>
  <form action="<?= $e($adminUrl) ?>ai-workflow/" method="post" class="uk-form-stacked">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <input type="hidden" name="workflow_context" value="<?= $e($aiContext) ?>">
    <label class="uk-form-label" for="kontor-workflow-ai-notes">Notes to send to the AI provider</label>
    <textarea class="uk-textarea uk-margin-small" id="kontor-workflow-ai-notes" name="workflow_notes" rows="4" maxlength="4000" required></textarea>
    <button class="uk-button uk-button-primary" type="submit">Generate suggestion</button>
  </form>
</details>
