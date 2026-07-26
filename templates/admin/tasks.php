<?php

/** @var \Kontor\Tasks\Domain\Task[] $tasks */
/** @var string $query */
/** @var string|null $selectedStatus */
/** @var string|null $selectedPriority */
/** @var bool $archived */
/** @var string $adminUrl */
/** @var callable $e */
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Work queue</p>
      <h2><?= $archived ? 'Archived tasks' : 'Tasks' ?></h2>
      <p>Capture work, move it forward, and complete recurring items.</p>
    </div>
    <a class="uk-button uk-button-primary kontor-button" href="<?= $e($adminUrl) ?>task/"><i class="fa fa-plus"></i> New task</a>
  </header>

  <form class="uk-card uk-card-default uk-card-small uk-card-body kontor-card kontor-filterbar" method="get">
    <label>Search <input name="q" value="<?= $e($query) ?>" placeholder="Title or description"></label>
    <label>Status
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $value => $label): ?>
          <option value="<?= $e($value) ?>"<?= $selectedStatus === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Priority
      <select name="priority">
        <option value="">All priorities</option>
        <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
          <option value="<?= $e($value) ?>"<?= $selectedPriority === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <button class="uk-button uk-button-primary kontor-button" type="submit">Filter</button>
    <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>tasks/<?= $archived ? '?archived=1' : '' ?>">Clear</a>
    <a href="<?= $e($adminUrl) ?>tasks/?archived=<?= $archived ? '0' : '1' ?>"><?= $archived ? 'Active tasks' : 'Archive' ?></a>
  </form>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <?php if ($tasks !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table">
        <thead><tr><th>Task</th><th>Due</th><th>Priority</th><th>Status</th><th>Recurrence</th></tr></thead>
        <tbody>
          <?php foreach ($tasks as $task): ?>
            <tr>
              <td><strong><a href="<?= $e($adminUrl) ?>task/?id=<?= $e(rawurlencode($task->uid->toString())) ?><?= $archived ? '&amp;archived=1' : '' ?>"><?= $e($task->title) ?></a></strong><span class="kontor-secondary"><?= $e($task->description ?? 'No description') ?></span></td>
              <td><?= $e($task->dueAt?->format('Y-m-d H:i') ?? 'No due date') ?><?= $task->isOverdue() ? ' · overdue' : '' ?></td>
              <td><?= $e($task->priority) ?></td>
              <td><span class="uk-label kontor-pill<?= $task->status === 'cancelled' ? ' kontor-pill--inactive' : '' ?>"><?= $e(str_replace('_', ' ', $task->status)) ?></span></td>
              <td><?= $e($task->recurrenceRule ?? 'One-off') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="pw-empty-state uk-placeholder uk-text-center kontor-empty"><i class="fa fa-check-square-o"></i><h3>No matching tasks</h3><p>Create a task or change the filters.</p></div>
    <?php endif; ?>
  </section>
</div>
