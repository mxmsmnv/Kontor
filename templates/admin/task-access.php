<?php

/** @var array<int, string> $employees */
/** @var int $selectedId */
/** @var array<int, string> $departments */
/** @var list<int> $selectedDepartments */
/** @var string $selectedLevel */
/** @var bool $externalAccess */
/** @var string $effectiveAccess */
/** @var string $error */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$accessUrl = $adminUrl . 'task-access/';
$levels = [
    'none' => ['No access from this screen', 'Remove the task access level set here. Permissions granted by other roles stay in place.'],
    'staff' => ['Team member', 'Create tasks and work on tasks visible to them, including those assigned to them or shared with their departments.'],
    'coordinator' => ['Team coordinator', 'Work on visible tasks, assign tasks to other people, and share tasks with their departments.'],
    'manager' => ['View all tasks', 'See every task, including private tasks. This level grants viewing only.'],
];
$hasEmployee = isset($employees[$selectedId]);
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <h2>Task access</h2>
      <p>Choose what each employee can do in Tasks and which departments they belong to.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default" href="<?= $e($adminUrl . 'tasks/') ?>">Back to tasks</a>
    </div>
  </header>

  <?php if ($error !== ''): ?>
    <div class="uk-alert-danger" role="alert"><?= $e($error) ?></div>
  <?php endif; ?>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-2-3@m">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <h3 class="uk-card-title">Employee access</h3>
        <?php if ($employees === []): ?>
          <p class="uk-margin-remove-bottom">No employee accounts are available. Add an account in ProcessWire to set up access here.</p>
        <?php else: ?>
          <form method="get" action="<?= $e($accessUrl) ?>" class="uk-form-stacked">
            <label for="task-access-user" class="uk-form-label">Employee</label>
            <div class="uk-grid-small uk-flex-middle" uk-grid>
              <div class="uk-width-expand@s">
                <select class="uk-select" id="task-access-user" name="user_id" required>
                  <option value=""<?= !$hasEmployee ? ' selected' : '' ?>>Choose an employee</option>
                  <?php foreach ($employees as $id => $label): ?>
                    <option value="<?= $e($id) ?>"<?= (int) $id === (int) $selectedId ? ' selected' : '' ?>><?= $e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="uk-width-auto@s">
                <button type="submit" class="uk-button uk-button-default">Open access settings</button>
              </div>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($hasEmployee): ?>
          <hr>
          <p class="uk-text-meta"><strong>Current access:</strong> <?= $e($effectiveAccess) ?></p>
          <?php if ($externalAccess): ?>
            <p class="uk-alert-warning" role="status">This employee also has task permissions from another ProcessWire role. Those permissions still apply when you change access here.</p>
          <?php endif; ?>

          <form method="post" action="<?= $e($accessUrl) ?>" class="uk-form-stacked">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="user_id" value="<?= $e($selectedId) ?>">

            <fieldset class="uk-fieldset">
              <legend class="uk-form-label">Access level for <?= $e($employees[$selectedId]) ?></legend>
              <?php foreach ($levels as $value => [$label, $description]): ?>
                <div class="uk-margin-small">
                  <label for="task-access-level-<?= $e($value) ?>">
                    <input type="radio" class="uk-radio" id="task-access-level-<?= $e($value) ?>" name="level" value="<?= $e($value) ?>"<?= $selectedLevel === $value ? ' checked' : '' ?> aria-describedby="task-access-help-<?= $e($value) ?>" required>
                    <strong><?= $e($label) ?></strong>
                  </label>
                  <div class="uk-text-meta" id="task-access-help-<?= $e($value) ?>"><?= $e($description) ?></div>
                </div>
              <?php endforeach; ?>
            </fieldset>

            <fieldset class="uk-fieldset uk-margin">
              <legend class="uk-form-label">Departments</legend>
              <?php if ($departments === []): ?>
                <p class="uk-text-meta uk-margin-small-top">Add a department to assign it to this employee.</p>
              <?php else: ?>
                <p class="uk-text-meta uk-margin-small-top">Members with task access can see tasks shared with these departments.</p>
                <?php foreach ($departments as $id => $label): ?>
                  <div class="uk-margin-small">
                    <label>
                      <input type="checkbox" class="uk-checkbox" name="departments[]" value="<?= $e($id) ?>"<?= in_array((int) $id, $selectedDepartments, true) ? ' checked' : '' ?>>
                      <?= $e($label) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </fieldset>

            <button type="submit" class="uk-button uk-button-primary">Save access</button>
          </form>
        <?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-3@m">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <h3 class="uk-card-title">Add a department</h3>
        <form method="post" action="<?= $e($accessUrl) ?>" class="uk-form-stacked">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <input type="hidden" name="action" value="create_department">
          <?php if ($hasEmployee): ?>
            <input type="hidden" name="user_id" value="<?= $e($selectedId) ?>">
          <?php endif; ?>
          <label for="task-access-department-name" class="uk-form-label">Department name</label>
          <input type="text" class="uk-input" id="task-access-department-name" name="department_name" placeholder="Media or Production" maxlength="64" required>
          <button type="submit" class="uk-button uk-button-default uk-margin-small-top">Add department</button>
        </form>
      </section>
    </div>
  </div>
</div>
