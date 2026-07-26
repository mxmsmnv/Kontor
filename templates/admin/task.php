<?php

/** @var \Kontor\Tasks\Domain\Task|null $task */
/** @var array<string, mixed> $values */
/** @var string $error */
/** @var bool $archived */
/** @var \Kontor\Tasks\Domain\TaskReminder[] $reminders */
/** @var bool $canManageReminders */
/** @var bool $collaborationReady */
/** @var \Kontor\Collaboration\Domain\Note[] $notes */
/** @var \Kontor\Collaboration\Domain\Comment[] $comments */
/** @var bool $following */
/** @var bool $canViewNotes */
/** @var bool $canCreateNotes */
/** @var bool $canArchiveNotes */
/** @var bool $canViewComments */
/** @var bool $canCreateComments */
/** @var bool $canArchiveComments */
/** @var bool $canManageFollow */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
?>
<div class="kontor-shell">
  <header class="kontor-formhead">
    <a class="kontor-formhead__back" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-arrow-left"></i> Back to tasks</a>
    <p class="kontor-eyebrow"><?= $task ? 'Task' : 'New task' ?></p>
    <h2><?= $e($task?->title ?? 'Create task') ?></h2>
    <p>Define the work, schedule reminders, and move it forward.</p>
  </header>

  <?php if ($task !== null): ?>
    <section class="kontor-card kontor-documenthead">
      <div>
        <span class="kontor-pill<?= $task->status === 'cancelled' ? ' kontor-pill--inactive' : '' ?>"><?= $e(str_replace('_', ' ', $task->status)) ?></span>
        <strong><?= $e($task->priority) ?> priority</strong>
        <span><?= $e($task->dueAt?->format('Y-m-d H:i') ?? 'No due date') ?><?= $task->isOverdue() ? ' · overdue' : '' ?></span>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($error !== ''): ?><div class="NoticeError"><?= $e($error) ?></div><?php endif; ?>
  <form class="kontor-card kontor-entity-form" method="post">
    <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
    <label>Title <input name="title" value="<?= $e($values['title']) ?>" required></label>
    <label>Description <textarea name="description" rows="5"><?= $e($values['description']) ?></textarea></label>
    <label>Priority
      <select name="priority">
        <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
          <option value="<?= $e($value) ?>"<?= $values['priority'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Due <input type="datetime-local" name="due_at" value="<?= $e($values['dueAt']) ?>"></label>
    <label>Recurrence
      <select name="recurrence_rule">
        <option value="">One-off</option>
        <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label): ?>
          <option value="<?= $e($value) ?>"<?= $values['recurrenceRule'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Repeat until <input type="date" name="recurrence_until" value="<?= $e($values['recurrenceUntil']) ?>"></label>
    <label><input type="checkbox" name="assigned_to_me" value="1"<?= $values['assignedToMe'] ? ' checked' : '' ?>> Assigned to me</label>
    <button class="kontor-button" name="submit_save" value="1" type="submit"><?= $task ? 'Save task' : 'Create task' ?></button>
  </form>

  <?php if ($task !== null): ?>
    <div class="kontor-documentactions">
      <form method="post" action="<?= $e($adminUrl) ?>task-action/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="id" value="<?= $e($task->uid->toString()) ?>">
        <?php if (!$archived && $task->status === 'open'): ?><button class="kontor-button" name="action" value="start" type="submit">Start task</button><?php endif; ?>
        <?php if (!$archived && $task->isOpen()): ?>
          <button class="kontor-button" name="action" value="complete" type="submit">Complete task</button>
          <button class="kontor-button kontor-button--ghost" name="action" value="cancel" type="submit">Cancel task</button>
        <?php endif; ?>
        <button class="kontor-button kontor-button--ghost" name="action" value="<?= $archived ? 'restore' : 'archive' ?>" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($task !== null): ?>
    <section class="kontor-card">
      <div class="kontor-pagehead">
        <div><p class="kontor-eyebrow">Queue + Mail</p><h3>Email reminders</h3></div>
      </div>
      <?php if ($reminders): ?>
        <table class="kontor-table">
          <thead><tr><th>Remind at</th><th>Channel</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($reminders as $reminder): ?><tr>
            <td><?= $e($reminder->remindAt->format('Y-m-d H:i')) ?></td>
            <td><?= $e($reminder->channel) ?></td>
            <td><span class="kontor-pill<?= $reminder->isSent() ? '' : ' kontor-pill--warning' ?>"><?= $reminder->isSent() ? 'sent' : 'scheduled' ?></span></td>
          </tr><?php endforeach; ?></tbody>
        </table>
      <?php else: ?>
        <p>No reminders scheduled.</p>
      <?php endif; ?>
      <?php if ($canManageReminders): ?>
        <form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>task-reminder/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="task_uid" value="<?= $e($task->uid->toString()) ?>">
          <label class="kontor-nativefield"><span>Remind at *</span><input type="datetime-local" name="remind_at" required></label>
          <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Schedule email reminder</button></div>
        </form>
        <?php if ($task->assignedTo === null): ?><p class="kontor-secondary">Assign this task before scheduling an email reminder.</p><?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($task !== null && $collaborationReady): ?>
    <section class="kontor-card">
      <div class="kontor-pagehead">
        <div><p class="kontor-eyebrow">Collaboration</p><h3>Notes and discussion</h3></div>
        <?php if ($canManageFollow): ?><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>">
          <button class="kontor-button kontor-button--ghost" name="action" value="toggle_follow" type="submit"><?= $following ? 'Unfollow thread' : 'Follow thread' ?></button>
        </form><?php endif; ?>
      </div>

      <?php if ($canViewNotes): ?><h4>Internal notes</h4>
      <?php foreach ($notes as $note): ?>
        <article class="kontor-card">
          <p><?= nl2br($e($note->body)) ?></p>
          <small>User #<?= $e($note->createdBy ?? 'system') ?> · <?= $e($note->createdAt->format('Y-m-d H:i')) ?></small>
          <?php if ($canArchiveNotes): ?><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>">
            <input type="hidden" name="record_uid" value="<?= $e($note->uid->toString()) ?>">
            <button class="kontor-button kontor-button--ghost" name="action" value="archive_note" type="submit">Archive note</button>
          </form><?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php if ($canCreateNotes): ?><form method="post" action="<?= $e($adminUrl) ?>collaboration-post/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="kind" value="note"><input type="hidden" name="entity_type" value="task"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>">
        <label>New note <textarea name="body" rows="3" required></textarea></label>
        <button class="kontor-button" type="submit">Add note</button>
      </form><?php endif; ?><?php endif; ?>

      <?php if ($canViewComments): ?><h4>Discussion</h4>
      <?php foreach ($comments as $comment): ?>
        <article class="kontor-card">
          <p><?= nl2br($e($comment->body)) ?></p>
          <small>User #<?= $e($comment->createdBy ?? 'system') ?> · <?= $e($comment->createdAt->format('Y-m-d H:i')) ?><?= $comment->isReply() ? ' · reply' : '' ?></small>
          <?php if ($canArchiveComments): ?><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>">
            <input type="hidden" name="record_uid" value="<?= $e($comment->uid->toString()) ?>">
            <button class="kontor-button kontor-button--ghost" name="action" value="archive_comment" type="submit">Archive comment</button>
          </form><?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php if ($canCreateComments): ?><form method="post" action="<?= $e($adminUrl) ?>collaboration-post/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <input type="hidden" name="kind" value="comment"><input type="hidden" name="entity_type" value="task"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>">
        <label>New comment <textarea name="body" rows="3" required></textarea></label>
        <button class="kontor-button" type="submit">Post comment</button>
      </form><?php endif; ?><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
