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
/** @var string $contextType */
/** @var string $contextUid */
/** @var string $contextLabel */
/** @var array<int, array{label: string, kind: string, route: string}> $relatedRecords */
/** @var array<int, string> $authorLabels */
/** @var string $assigneeLabel */
/** @var bool $canComplete */
/** @var bool $canCancel */
/** @var bool $canEdit */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static fn (?string $value): string => $value === null || $value === ''
    ? 'Not set'
    : ucwords(str_replace('_', ' ', $value));
$statusClass = static fn (string $status): string => match ($status) {
    'done' => ' uk-label-success',
    'cancelled' => ' uk-label-warning',
    default => '',
};
$priorityIcon = static fn (string $priority): string => match ($priority) {
    'urgent' => 'exclamation-circle',
    'high' => 'arrow-up',
    'low' => 'arrow-down',
    default => 'minus',
};
$dueLabel = static function ($task): string {
    if ($task->dueAt === null) {
        return 'No due date';
    }
    if ($task->isOverdue()) {
        return $task->dueAt->format('M j, Y · H:i') . ' · Overdue';
    }
    if ($task->status === 'done') {
        return $task->dueAt->format('M j, Y · H:i') . ' · Completed';
    }

    return $task->dueAt->format('M j, Y · H:i');
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow"><?= $task === null ? 'New work item' : 'Task workspace' ?></p>
      <h2><?= $e($task?->title ?? 'Create task') ?></h2>
      <p><?= $task === null
          ? 'Capture a clear outcome, choose ownership and decide when the work needs attention.'
          : 'Keep the outcome, timing, reminders and team context together in one place.' ?></p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default" href="<?= $e($adminUrl) ?>tasks/<?= $archived ? '?archived=1' : '' ?>"><i class="fa fa-arrow-left"></i> All tasks</a>
      <?php if ($task !== null && $canEdit && !$archived): ?><button class="uk-button uk-button-default" type="button" uk-toggle="target: #kontor-task-editor"><i class="fa fa-pencil"></i> Edit task</button><?php endif; ?>
    </div>
  </header>

  <?php if ($error !== ''): ?><div class="uk-alert-danger uk-margin-medium-bottom" uk-alert><p><strong>Task could not be saved.</strong> <?= $e($error) ?></p></div><?php endif; ?>

  <?php if ($task === null): ?>
    <?php if ($contextLabel !== ''): ?>
      <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><p><strong>Connected to <?= $e($contextLabel) ?>.</strong> This task will appear in the related <?= $e($contextType) ?> workspace after creation.</p></div>
    <?php endif; ?>
    <form class="uk-form-stacked" method="post">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <?php if ($contextType !== '' && $contextUid !== ''): ?><input type="hidden" name="context_entity_type" value="<?= $e($contextType) ?>"><input type="hidden" name="context_entity_uid" value="<?= $e($contextUid) ?>"><?php endif; ?>
      <div class="uk-grid-medium" uk-grid>
        <div class="uk-width-1-1 uk-width-2-3@l">
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Task details</p>
            <h3 class="uk-card-title uk-margin-small-top">What needs to happen?</h3>
            <p class="uk-text-muted">Write the task so another teammate can understand the outcome without asking for more context.</p>
            <div class="uk-margin">
              <label class="uk-form-label" for="task-title">Task title</label>
              <input class="uk-input uk-margin-small-top" id="task-title" name="title" value="<?= $e($values['title']) ?>" placeholder="For example: Confirm the proposal with the customer" required>
              <div class="uk-text-meta uk-margin-small-top">Start with an action and name the result that marks the work as complete.</div>
            </div>
            <div class="uk-margin-remove-bottom">
              <label class="uk-form-label" for="task-description">Description <span class="uk-text-meta">(optional)</span></label>
              <textarea class="uk-textarea uk-margin-small-top" id="task-description" name="description" rows="7" placeholder="Add the relevant background, constraints and definition of done"><?= $e($values['description']) ?></textarea>
              <div class="uk-text-meta uk-margin-small-top">Keep decisions and execution details here. Team discussion can continue after the task is created.</div>
            </div>
          </section>
        </div>
        <div class="uk-width-1-1 uk-width-1-3@l">
          <section class="uk-card uk-card-default uk-card-small uk-card-body">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Planning</p>
            <h3 class="uk-card-title uk-margin-small-top">Set responsibility</h3>
            <div class="uk-margin">
              <label class="uk-form-label" for="task-priority">Priority</label>
              <select class="uk-select uk-margin-small-top" id="task-priority" name="priority"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['priority'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
              <div class="uk-text-meta uk-margin-small-top">Reserve High and Urgent for work that blocks a customer or teammate.</div>
            </div>
            <div class="uk-margin">
              <label class="uk-form-label" for="task-due">Due date <span class="uk-text-meta">(optional)</span></label>
              <input class="uk-input uk-margin-small-top" id="task-due" type="datetime-local" name="due_at" value="<?= $e($values['dueAt']) ?>">
              <div class="uk-text-meta uk-margin-small-top">Choose a real commitment date, or leave this blank.</div>
            </div>
            <label class="uk-display-block uk-margin"><input class="uk-checkbox" type="checkbox" name="assigned_to_me" value="1"<?= $values['assignedToMe'] ? ' checked' : '' ?>> <span class="uk-margin-small-left"><strong>Assign to me</strong></span><span class="uk-text-meta uk-display-block uk-margin-small-left">The task will appear in your personal work queue.</span></label>
            <ul class="uk-accordion uk-margin" uk-accordion>
              <li<?= $values['recurrenceRule'] !== '' ? ' class="uk-open"' : '' ?>>
                <a class="uk-accordion-title uk-link-reset" href>Repeat this task</a>
                <div class="uk-accordion-content">
                  <div class="uk-margin-small">
                    <label class="uk-form-label" for="task-recurrence">Frequency</label>
                    <select class="uk-select uk-margin-small-top" id="task-recurrence" name="recurrence_rule"><option value="">Does not repeat</option><?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['recurrenceRule'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select>
                    <div class="uk-text-meta uk-margin-small-top">A due date is required when the task repeats.</div>
                  </div>
                  <div class="uk-margin">
                    <label class="uk-form-label" for="task-repeat-until">End repeating <span class="uk-text-meta">(optional)</span></label>
                    <input class="uk-input uk-margin-small-top" id="task-repeat-until" type="date" name="recurrence_until" value="<?= $e($values['recurrenceUntil']) ?>">
                  </div>
                </div>
              </li>
            </ul>
            <hr>
            <button class="uk-button uk-button-primary uk-width-1-1" name="submit_save" value="1" type="submit"><i class="fa fa-plus"></i> Create task</button>
            <p class="uk-text-meta uk-text-center uk-margin-small-top uk-margin-remove-bottom">You can add reminders and team updates after creation.</p>
          </section>
        </div>
      </div>
    </form>
  <?php else: ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom">
      <div class="uk-grid-medium uk-flex-middle" uk-grid>
        <div class="uk-width-expand@m">
          <div class="uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid><div><span class="uk-label<?= $statusClass($task->status) ?>"><?= $e($humanize($task->status)) ?></span></div><?php if ($archived): ?><div><span class="uk-label">Archived</span></div><?php endif; ?></div>
          <h3 class="uk-card-title uk-margin-small-top uk-margin-small-bottom"><?= $e($task->title) ?></h3>
          <p class="uk-text-muted uk-margin-remove"><?= $e($task->description ?: 'No description has been added yet.') ?></p>
        </div>
        <div class="uk-width-auto@m">
          <form class="uk-flex uk-flex-wrap uk-grid-small" uk-grid method="post" action="<?= $e($adminUrl) ?>task-action/">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($task->uid->toString()) ?>">
            <?php if (!$archived && $task->status === 'open' && $canEdit): ?><div><button class="uk-button uk-button-primary" name="action" value="start" type="submit"><i class="fa fa-play"></i> Start task</button></div><?php endif; ?>
            <?php if (!$archived && $task->isOpen() && $canComplete): ?><div><button class="uk-button uk-button-primary" name="action" value="complete" type="submit"><i class="fa fa-check"></i> Complete</button></div><?php endif; ?>
            <?php if (!$archived && $task->isOpen() && $canCancel): ?><div><button class="uk-button uk-button-default" name="action" value="cancel" type="submit" data-kontor-confirm="Cancel this task?"><i class="fa fa-ban"></i> Cancel</button></div><?php endif; ?>
            <?php if ($canEdit): ?><div><button class="uk-button uk-button-default" name="action" value="<?= $archived ? 'restore' : 'archive' ?>" type="submit" data-kontor-confirm="<?= $archived ? 'Restore this task to the active queue?' : 'Archive this task?' ?>"><i class="fa fa-<?= $archived ? 'undo' : 'archive' ?>"></i> <?= $archived ? 'Restore' : 'Archive' ?></button></div><?php endif; ?>
          </form>
        </div>
      </div>
    </section>

    <div class="uk-grid-medium uk-margin-medium-bottom" uk-grid>
      <div class="uk-width-1-1 uk-width-2-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Execution</p><h3 class="uk-card-title uk-margin-small-top">Task overview</h3>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s" uk-grid>
            <div><div class="uk-text-meta">Owner</div><strong><i class="fa fa-user"></i> <?= $e($assigneeLabel) ?></strong></div>
            <div><div class="uk-text-meta">Priority</div><strong><i class="fa fa-<?= $e($priorityIcon($task->priority)) ?>"></i> <?= $e($humanize($task->priority)) ?></strong></div>
            <div><div class="uk-text-meta">Due</div><strong class="<?= $task->isOverdue() ? 'uk-text-danger' : '' ?>"><i class="fa fa-calendar"></i> <?= $e($dueLabel($task)) ?></strong></div>
            <div><div class="uk-text-meta">Schedule</div><strong><i class="fa fa-repeat"></i> <?= $e($task->recurrenceRule !== null ? 'Repeats ' . $humanize($task->recurrenceRule) : 'One-time task') ?></strong></div>
          </div>
        </section>

        <?php if ($relatedRecords !== []): ?>
          <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected work</p><h3 class="uk-card-title uk-margin-small-top">Related records</h3>
            <p class="uk-text-muted">Open the customer or company that provides the business context for this task.</p>
            <ul class="uk-list uk-list-divider uk-margin-remove-bottom"><?php foreach ($relatedRecords as $record): ?><li><a class="uk-link-reset uk-flex uk-flex-between uk-flex-middle" href="<?= $e($adminUrl . $record['route']) ?>"><span><span class="uk-text-meta"><?= $e($humanize($record['kind'])) ?></span><br><strong><?= $e($record['label']) ?></strong></span><span class="uk-button uk-button-text">Open <i class="fa fa-angle-right"></i></span></a></li><?php endforeach; ?></ul>
          </section>
        <?php endif; ?>
      </div>

      <div class="uk-width-1-1 uk-width-1-3@l">
        <section class="uk-card uk-card-default uk-card-small uk-card-body">
          <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Stay on track</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Email reminders</h3></div><?php if ($canManageReminders && $task->assignedTo !== null && $task->isOpen() && !$archived): ?><div><button class="uk-button uk-button-default uk-button-small" type="button" uk-toggle="target: #kontor-task-reminder"><i class="fa fa-plus"></i> Add</button></div><?php endif; ?></div>
          <?php if ($reminders !== []): ?><ul class="uk-list uk-list-divider"><?php foreach ($reminders as $reminder): ?><li><div class="uk-flex uk-flex-between uk-flex-middle"><strong><?= $e($reminder->remindAt->format('M j · H:i')) ?></strong><span class="uk-label<?= $reminder->isSent() ? ' uk-label-success' : '' ?>"><?= $reminder->isSent() ? 'Sent' : 'Scheduled' ?></span></div><div class="uk-text-meta uk-margin-small-top">Email reminder</div></li><?php endforeach; ?></ul>
          <?php elseif ($task->assignedTo === null): ?><div class="uk-text-center uk-padding-small"><p class="uk-text-muted">Assign the task before scheduling an email reminder.</p></div>
          <?php elseif (!$task->isOpen()): ?><div class="uk-text-center uk-padding-small"><span class="fa fa-check-circle-o fa-2x uk-text-muted"></span><p class="uk-text-muted uk-margin-small-top">No reminders are needed for this completed task.</p></div>
          <?php else: ?><div class="uk-text-center uk-padding-small"><span class="fa fa-bell-o fa-2x uk-text-muted"></span><p class="uk-text-muted uk-margin-small-top">No reminders scheduled.</p><?php if ($canManageReminders && !$archived): ?><button class="uk-button uk-button-default" type="button" uk-toggle="target: #kontor-task-reminder">Schedule reminder</button><?php endif; ?></div><?php endif; ?>
        </section>
      </div>
    </div>

    <?php if ($collaborationReady): ?>
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small" uk-grid>
          <div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Team context</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Notes and discussion</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Keep internal context separate from the conversation teammates follow.</p></div>
          <?php if ($canManageFollow): ?><div><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>"><button class="uk-button uk-button-default" name="action" value="toggle_follow" type="submit"><i class="fa fa-<?= $following ? 'bell-slash-o' : 'bell-o' ?>"></i> <?= $following ? 'Stop following' : 'Follow updates' ?></button></form></div><?php endif; ?>
        </div>

        <ul class="uk-tab uk-margin" uk-tab="connect: #kontor-task-collaboration"><li><a href>Discussion <span class="uk-badge"><?= $e((string) count($comments)) ?></span></a></li><li><a href>Internal notes <span class="uk-badge"><?= $e((string) count($notes)) ?></span></a></li></ul>
        <ul id="kontor-task-collaboration" class="uk-switcher">
          <li>
            <?php if ($canViewComments && $comments !== []): ?><ul class="uk-list uk-list-divider"><?php foreach ($comments as $comment): ?><li><article class="uk-comment"><header class="uk-comment-header uk-grid-small uk-flex-middle" uk-grid><div class="uk-width-expand"><h4 class="uk-comment-title uk-margin-remove"><?= $e($comment->createdBy !== null ? ($authorLabels[$comment->createdBy] ?? 'Former user') : 'System') ?></h4><p class="uk-comment-meta uk-margin-remove-top"><?= $e($comment->createdAt->format('M j, Y · H:i')) ?><?= $comment->isReply() ? ' · Reply' : '' ?></p></div><?php if ($canArchiveComments): ?><div class="uk-width-auto"><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/" data-kontor-confirm="Archive this comment?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>"><input type="hidden" name="record_uid" value="<?= $e($comment->uid->toString()) ?>"><button class="uk-button uk-button-text" name="action" value="archive_comment" type="submit">Archive</button></form></div><?php endif; ?></header><div class="uk-comment-body"><p><?= nl2br($e($comment->body)) ?></p></div></article></li><?php endforeach; ?></ul><?php elseif ($canViewComments): ?><div class="uk-text-center uk-padding-small"><p class="uk-text-muted">No discussion yet.</p></div><?php endif; ?>
            <?php if ($canCreateComments && !$archived): ?><form class="uk-form-stacked uk-margin-top" method="post" action="<?= $e($adminUrl) ?>collaboration-post/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="kind" value="comment"><input type="hidden" name="entity_type" value="task"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>"><label class="uk-form-label" for="task-comment">Add to the discussion</label><textarea class="uk-textarea uk-margin-small-top" id="task-comment" name="body" rows="3" placeholder="Share an update, question or decision" required></textarea><div class="uk-text-meta uk-margin-small-top">Teammates following this task may be notified.</div><button class="uk-button uk-button-primary uk-margin-small-top" type="submit"><i class="fa fa-comment"></i> Post update</button></form><?php endif; ?>
          </li>
          <li>
            <?php if ($canViewNotes && $notes !== []): ?><ul class="uk-list uk-list-divider"><?php foreach ($notes as $note): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><p><?= nl2br($e($note->body)) ?></p><div class="uk-text-meta"><?= $e($note->createdBy !== null ? ($authorLabels[$note->createdBy] ?? 'Former user') : 'System') ?> · <?= $e($note->createdAt->format('M j, Y · H:i')) ?></div></div><?php if ($canArchiveNotes): ?><div><form method="post" action="<?= $e($adminUrl) ?>collaboration-action/" data-kontor-confirm="Archive this note?"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>"><input type="hidden" name="record_uid" value="<?= $e($note->uid->toString()) ?>"><button class="uk-button uk-button-text" name="action" value="archive_note" type="submit">Archive</button></form></div><?php endif; ?></div></li><?php endforeach; ?></ul><?php elseif ($canViewNotes): ?><div class="uk-text-center uk-padding-small"><p class="uk-text-muted">No internal notes yet.</p></div><?php endif; ?>
            <?php if ($canCreateNotes && !$archived): ?><form class="uk-form-stacked uk-margin-top" method="post" action="<?= $e($adminUrl) ?>collaboration-post/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="kind" value="note"><input type="hidden" name="entity_type" value="task"><input type="hidden" name="entity_uid" value="<?= $e($task->uid->toString()) ?>"><label class="uk-form-label" for="task-note">Add an internal note</label><textarea class="uk-textarea uk-margin-small-top" id="task-note" name="body" rows="3" placeholder="Capture private context for teammates" required></textarea><div class="uk-text-meta uk-margin-small-top">Internal notes are for operational context, not customer-facing communication.</div><button class="uk-button uk-button-primary uk-margin-small-top" type="submit"><i class="fa fa-sticky-note-o"></i> Add note</button></form><?php endif; ?>
          </li>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($canEdit && !$archived): ?>
      <div id="kontor-task-editor" class="uk-modal-container" uk-modal>
        <div class="uk-modal-dialog uk-modal-body">
          <a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a>
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Task settings</p><h2 class="uk-modal-title uk-margin-small-top">Edit task</h2>
          <form class="uk-form-stacked" method="post">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <div class="uk-margin"><label class="uk-form-label" for="task-edit-title">Title</label><input class="uk-input uk-margin-small-top" id="task-edit-title" name="title" value="<?= $e($values['title']) ?>" required><div class="uk-text-meta uk-margin-small-top">Describe the outcome or action in a few clear words.</div></div>
            <div class="uk-margin"><label class="uk-form-label" for="task-edit-description">Description</label><textarea class="uk-textarea uk-margin-small-top" id="task-edit-description" name="description" rows="4"><?= $e($values['description']) ?></textarea><div class="uk-text-meta uk-margin-small-top">Add the context teammates need to execute the work.</div></div>
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1 uk-width-1-2@m"><label class="uk-form-label" for="task-edit-priority">Priority</label><select class="uk-select uk-margin-small-top" id="task-edit-priority" name="priority"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['priority'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
              <div class="uk-width-1-1 uk-width-1-2@m"><label class="uk-form-label" for="task-edit-due">Due date</label><input class="uk-input uk-margin-small-top" id="task-edit-due" type="datetime-local" name="due_at" value="<?= $e($values['dueAt']) ?>"></div>
              <div class="uk-width-1-1 uk-width-1-2@m"><label class="uk-form-label" for="task-edit-recurrence">Repeats</label><select class="uk-select uk-margin-small-top" id="task-edit-recurrence" name="recurrence_rule"><option value="">Does not repeat</option><?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label): ?><option value="<?= $e($value) ?>"<?= $values['recurrenceRule'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
              <div class="uk-width-1-1 uk-width-1-2@m"><label class="uk-form-label" for="task-edit-repeat-until">Repeat until</label><input class="uk-input uk-margin-small-top" id="task-edit-repeat-until" type="date" name="recurrence_until" value="<?= $e($values['recurrenceUntil']) ?>"></div>
            </div>
            <label class="uk-display-block uk-margin"><input class="uk-checkbox" type="checkbox" name="assigned_to_me" value="1"<?= $values['assignedToMe'] ? ' checked' : '' ?>> <span class="uk-margin-small-left">Assigned to me</span></label>
            <div class="uk-flex uk-flex-right uk-grid-small" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" name="submit_save" value="1" type="submit"><i class="fa fa-check"></i> Save changes</button></div></div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($canManageReminders && $task->assignedTo !== null && $task->isOpen() && !$archived): ?>
      <div id="kontor-task-reminder" uk-modal><div class="uk-modal-dialog uk-modal-body">
        <a class="uk-modal-close-default" href="#" role="button" uk-close aria-label="Close"></a>
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Email reminder</p><h2 class="uk-modal-title uk-margin-small-top">Schedule reminder</h2><p class="uk-text-muted">Kontor will email <?= $e($assigneeLabel) ?> at the selected local date and time.</p>
        <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>task-reminder/"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="task_uid" value="<?= $e($task->uid->toString()) ?>"><label class="uk-form-label" for="task-remind-at">Remind at</label><input class="uk-input uk-margin-small-top" id="task-remind-at" type="datetime-local" name="remind_at" required><div class="uk-text-meta uk-margin-small-top">Choose a useful moment before the due date so there is time to act.</div><div class="uk-flex uk-flex-right uk-grid-small uk-margin" uk-grid><div><button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button></div><div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-bell"></i> Schedule</button></div></div></form>
      </div></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
