<?php

/** @var \Kontor\Tasks\Domain\Task|null $task */
/** @var array<string, mixed> $values */
/** @var string $error */
/** @var bool $archived */
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
    <p>Define the work now; reminders and calendar views come on later floors.</p>
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
</div>
