<?php

$typeLabels = ['task' => 'Task', 'birthday' => 'Birthday', 'followup' => 'Client follow-up'];
$remindersUrl = $adminUrl . 'reminders/';
$now = new DateTimeImmutable();
?>
<section aria-label="Reminders">
  <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin">
    <form method="get" action="<?= $e($remindersUrl) ?>" class="uk-flex uk-flex-middle uk-flex-wrap">
      <label class="uk-margin-small-right" for="reminders-channel">Channel</label>
      <select class="uk-select uk-form-width-small uk-margin-small-right" name="channel" id="reminders-channel">
        <option value="">All channels</option>
        <?php foreach ($channels as $channel): ?>
          <option value="<?= $e($channel['id']) ?>"<?= $selectedChannel === $channel['id'] ? ' selected' : '' ?>><?= $e($channel['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="uk-button uk-button-default" type="submit">Filter</button>
    </form>
    <a class="uk-button uk-button-default" href="<?= $e($adminUrl . 'tasks/?view=calendar') ?>">Task calendar</a>
  </div>
  <?php if (isset($channels['email']) && !empty($channels['email']['description'])): ?>
    <p class="uk-text-meta"><?= $e($channels['email']['description']) ?></p>
  <?php endif; ?>
  <?php if ($items === []): ?>
    <div class="uk-placeholder uk-text-center">
      <p class="uk-margin-remove"><?= $selectedChannel === '' ? 'No reminders to show.' : 'No reminders for this channel.' ?></p>
    </div>
  <?php else: ?>
    <div class="uk-overflow-auto">
      <table class="uk-table uk-table-divider uk-table-small uk-table-middle">
        <thead>
          <tr><th scope="col">Reminder</th><th scope="col">Date and time</th><th scope="col">Channel</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $taskUrl = $adminUrl . 'task/?id=' . rawurlencode($item['taskUid']); ?>
            <tr>
              <td>
                <a href="<?= $e($taskUrl) ?>"><?= $e($item['title']) ?></a>
                <div class="uk-text-meta"><?= $e($typeLabels[$item['kind']] ?? 'Task') ?></div>
              </td>
              <td><time datetime="<?= $e($item['remindAt']->format(DATE_ATOM)) ?>"><?= $e($item['remindAt']->format('M j, Y · H:i')) ?></time></td>
              <td><?= $e($channels[$item['channel']]['label'] ?? $item['channel']) ?></td>
              <td><?= $item['channel'] === 'email' ? ($item['sentAt'] === null ? 'Not confirmed' : 'Sent') : 'Due date' ?></td>
              <td>
                <?php if ($item['contactUid'] !== null): ?>
                  <a class="uk-button uk-button-default uk-button-small" href="<?= $e($adminUrl . 'contact/?id=' . rawurlencode($item['contactUid'])) ?>">Contact</a>
                <?php endif; ?>
                <?php if ($canManageEmail && $item['channel'] === 'app' && isset($channels['email']) && ($item['canScheduleEmail'] ?? false)): ?>
                  <details class="uk-margin-small-top">
                    <summary>Schedule email</summary>
                    <form method="post" action="<?= $e($adminUrl . 'task-reminder/') ?>" class="uk-form-stacked uk-margin-small-top">
                      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                      <input type="hidden" name="task_uid" value="<?= $e($item['taskUid']) ?>">
                      <input type="hidden" name="return_to" value="reminders">
                      <label class="uk-form-label">
                        Send at
                        <input class="uk-input uk-form-small" name="remind_at" type="datetime-local" required
                          value="<?= $item['remindAt'] > $now ? $e($item['remindAt']->format('Y-m-d\TH:i')) : '' ?>">
                      </label>
                      <button class="uk-button uk-button-primary uk-button-small uk-margin-small-top" type="submit">Schedule</button>
                    </form>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
