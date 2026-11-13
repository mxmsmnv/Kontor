<section id="employee-profile" class="ws-account uk-margin-top">
  <h2>Employee profile</h2>
  <p><strong><?= $e($name) ?></strong></p>
  <?php if ($avatar !== ''): ?>
    <img src="<?= $e($avatar) ?>" alt="Current avatar" width="96" height="96" class="uk-border-circle">
  <?php endif; ?>
  <?php if ($error !== ''): ?><p class="uk-alert-danger" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <form action="<?= $e($url) ?>" method="post" enctype="multipart/form-data" class="uk-form-stacked uk-margin-small-top">
    <input type="hidden" name="<?= $e($csrf['name']) ?>" value="<?= $e($csrf['value']) ?>">
    <label class="uk-form-label" for="employee-avatar">Avatar</label>
    <input id="employee-avatar" type="file" name="employee_avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="employee-avatar-help">
    <p id="employee-avatar-help" class="uk-text-meta">JPEG, PNG or WebP, up to 5 MB. The image is cropped to a square.</p>
    <button class="uk-button uk-button-primary" name="kontor_employee_avatar_action" value="upload">Upload avatar</button>
    <?php if ($avatar !== ''): ?>
      <button class="uk-button uk-button-default" name="kontor_employee_avatar_action" value="remove">Remove avatar</button>
    <?php endif; ?>
  </form>
</section>
