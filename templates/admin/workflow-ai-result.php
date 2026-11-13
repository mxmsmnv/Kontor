<section class="uk-card uk-card-default uk-card-body">
  <h2>AI suggestion</h2>
  <?php if ($result !== ''): ?>
    <p>Review this draft before using it.</p>
    <div><?= nl2br($e($result)) ?></div>
  <?php else: ?>
    <p role="alert">The AI provider did not return a suggestion. Go back and try again.</p>
  <?php endif; ?>
  <p><a href="<?= $e($adminUrl) ?>">Back to Kontor</a></p>
</section>
