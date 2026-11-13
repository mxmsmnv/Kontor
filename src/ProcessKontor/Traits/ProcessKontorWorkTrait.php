<?php

namespace ProcessWire;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Note;
use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\Core\Application\AuditChangePresenter;
use Kontor\Core\Application\AuditCsvExporter;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Application\BackupOverviewBuilder;
use Kontor\Core\Application\ComponentOverviewBuilder;
use Kontor\Core\Application\EntityActionResolver;
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\HealthCheckRunner;
use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Application\NavigationAvailability;
use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Health\CoreHealthCheck;
use Kontor\Core\Infrastructure\Backup\BackupArchiveBuilder;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Invoices\Application\LedgerInvoicePostingService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;
use Kontor\Ledger\Domain\Account;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Domain\Payment;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\Task;
use Kontor\Workflow\Domain\ApprovalRequest;

/**
 * Administrative feature slice composed by ProcessKontor.
 *
 * @internal
 */
trait ProcessKontorWorkTrait
{

    private function canViewTask(Task $task): bool
    {
        return hash_equals($this->organizationUid(), $task->organizationId)
            && $this->taskModule()->accessFor($this->wire()->user)->allows($task);
    }

    private function requireVisibleTask(string $id): Task
    {
        $this->requireTasks();
        $task = $this->taskModule()->taskRepository()->find($id);
        if ($task === null || !$this->canViewTask($task)) {
            http_response_code(404);
            throw new Wire404Exception($this->_('Task not found.'));
        }

        return $task;
    }

    public function ___executeTasks(): string
    {
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-task-view');
        $archived = (string) $this->wire()->input->get('archived') === '1';
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['open', 'in_progress', 'done', 'cancelled']
        );
        $priority = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('priority'),
            ['low', 'normal', 'high', 'urgent']
        );
        $scope = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('scope'),
            ['active', 'all', 'closed', 'mine', 'overdue', 'today', 'upcoming']
        ) ?? ($archived ? 'all' : 'active');
        if (in_array($status, ['done', 'cancelled'], true) && !in_array($scope, ['all', 'closed'], true)) {
            $scope = 'all';
        }
        $repository = $this->taskModule()->taskRepository();
        $access = $this->taskModule()->accessFor($this->wire()->user);
        $allTasks = $repository->findMatching(
            $this->organizationUid(),
            archived: $archived,
            limit: 500,
            access: $access,
        );
        $tasks = $repository->findMatching(
            $this->organizationUid(),
            $query,
            $status,
            $priority,
            $archived,
            limit: 250,
            closedOnly: $scope === 'closed',
            access: $access,
        );
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $upcoming = $today->modify('+7 days');
        $currentUserId = (int) $this->wire()->user->id;
        $tasks = array_values(array_filter(
            $tasks,
            static fn (Task $task): bool => match ($scope) {
                'active' => $task->isOpen(),
                'mine' => $task->isOpen() && $task->assignedTo === $currentUserId,
                'overdue' => $task->isOverdue(),
                'today' => $task->isOpen() && $task->dueAt !== null
                    && $task->dueAt >= $today && $task->dueAt < $tomorrow,
                'upcoming' => $task->isOpen() && $task->dueAt !== null
                    && $task->dueAt >= $tomorrow && $task->dueAt < $upcoming,
                default => true,
            }
        ));
        $assigneeLabels = [];
        foreach ($tasks as $task) {
            if ($task->assignedTo === null || isset($assigneeLabels[$task->assignedTo])) {
                continue;
            }
            $assignee = $this->wire()->users->get($task->assignedTo);
            if ($assignee->id > 0) {
                $assigneeLabels[$task->assignedTo] = (string) (
                    $assignee->get('title') ?: ucfirst((string) $assignee->name)
                );
            }
        }
        $this->setPageTitle($archived
            ? $this->_('Kontor · Archived tasks')
            : $this->_('Kontor · Tasks'));

        return $this->renderTemplate('tasks', [
            'tasks' => $tasks,
            'allTasks' => $allTasks,
            'query' => $query,
            'selectedStatus' => $status,
            'selectedPriority' => $priority,
            'selectedScope' => $scope,
            'archived' => $archived,
            'assigneeLabels' => $assigneeLabels,
            'currentUserId' => $currentUserId,
            'canCreate' => $this->can('kontor-tasks-task-create'),
            'canManageTaskAccess' => $this->wire()->user->isSuperuser(),
        ]);
    }

    public function ___executeTaskAccess(): string
    {
        if (!$this->wire()->user->isSuperuser()) {
            throw new WirePermissionException($this->_('Only a site administrator can manage task access.'));
        }
        $this->requireTasks();
        $wire = $this->wire();
        $input = $wire->input;
        $selectedId = (int) $input->get('user_id');
        $employees = [];
        foreach ($wire->users as $employee) {
            if (!$employee->isGuest() && !$employee->isSuperuser() && !$employee->isUnpublished() && !$employee->isTrash()) {
                $employees[(int) $employee->id] = (string) ($employee->get('title') ?: $employee->name);
            }
        }
        asort($employees, SORT_NATURAL | SORT_FLAG_CASE);

        $base = ['kontor-access', 'kontor-tasks-task-view'];
        if ($wire->modules->isInstalled('Workspace')) {
            $base[] = 'workspace-use';
        }
        $staff = [...$base, 'kontor-tasks-task-create', 'kontor-tasks-task-edit', 'kontor-tasks-task-complete'];
        $presets = [
            'staff' => ['kontor-tasks-staff', $staff],
            'coordinator' => ['kontor-tasks-coordinator', [...$staff, 'kontor-tasks-task-assign']],
            'manager' => ['kontor-tasks-manager', [...$base, 'kontor-tasks-task-view-all']],
        ];
        $managed = [];
        $departments = [];
        foreach ($this->taskModule()->departmentRoles() as $role) {
            if ($this->taskRoleMatches($role, ['kontor-tasks-department'])) {
                $departments[(int) $role->id] = $this->taskDepartmentLabel($role);
            }
        }
        asort($departments, SORT_NATURAL | SORT_FLAG_CASE);

        $url = $wire->config->urls->admin . 'kontor/task-access/';
        $error = '';
        if ($input->requestMethod('POST')) {
            $this->requirePost();
            $action = (string) $input->post('action');
            try {
                if ($action === 'create_department') {
                    $selectedId = (int) $input->post('user_id');
                    $label = trim($wire->sanitizer->text((string) $input->post('department_name'), ['maxLength' => 64]));
                    $slug = $wire->sanitizer->pageNameTranslate($label, 64);
                    if ($slug === '') {
                        throw new WireException($this->_('Enter a department name.'));
                    }
                    foreach ($departments as $departmentLabel) {
                        if (mb_strtolower($departmentLabel) === mb_strtolower($label)) {
                            throw new WireException($this->_('A department with this name already exists.'));
                        }
                    }
                    $this->createTaskAccessRole('kontor-dept-' . $slug, ['kontor-tasks-department'], $label);
                    $this->message($this->_('Department created. Select an employee to add members.'));
                    $wire->session->redirect($url . ($selectedId ? '?user_id=' . $selectedId : ''));
                }

                if ($action !== 'save') {
                    throw new WireException($this->_('Unknown action.'));
                }
                $selectedId = (int) $input->post('user_id');
                $level = (string) $input->post('level');
                $chosen = array_values(array_unique(array_map('intval', (array) $input->post('departments'))));
                if (!isset($employees[$selectedId]) || ($level !== 'none' && !isset($presets[$level]))
                    || array_diff($chosen, array_keys($departments)) !== []) {
                    throw new WireException($this->_('Choose an available employee, access level and departments.'));
                }
                $target = $wire->users->get($selectedId);
                if (!$target instanceof User || !$target->id || $target->isSuperuser() || $target->isGuest()
                    || $target->isUnpublished() || $target->isTrash()) {
                    throw new WireException($this->_('This account cannot be managed here.'));
                }
                foreach ($presets as $key => [$name, $grants]) {
                    $role = $wire->roles->get($name);
                    if ($role->id && $this->taskRoleMatches($role, $grants)) {
                        $managed[$key] = $role;
                    }
                }
                if ($level !== 'none' && !isset($managed[$level])) {
                    [$name, $grants] = $presets[$level];
                    $managed[$level] = $this->createTaskAccessRole($name, $grants);
                }
                $target->of(false);
                foreach ($managed as $key => $role) {
                    if ($key === $level) {
                        $target->addRole($role);
                    } elseif ($target->hasRole($role)) {
                        $target->removeRole($role);
                    }
                }
                foreach (array_keys($departments) as $id) {
                    $role = $wire->roles->get($id);
                    if (!$role instanceof Role || !$role->id || !$this->taskRoleMatches($role, ['kontor-tasks-department'])) {
                        continue;
                    }
                    if (in_array($id, $chosen, true)) {
                        $target->addRole($role);
                    } elseif ($target->hasRole($role)) {
                        $target->removeRole($role);
                    }
                }
                if (!$target->save('roles')) {
                    throw new WireException($this->_('Could not save employee access.'));
                }
                $this->message($this->_('Task access saved.'));
                $wire->session->redirect($url . '?user_id=' . $selectedId);
            } catch (WireException $exception) {
                $error = $exception->getMessage();
            }
        }

        $selectedId = isset($employees[$selectedId]) ? $selectedId : 0;
        $target = $selectedId ? $wire->users->get($selectedId) : null;
        $selectedLevel = 'none';
        $selectedDepartments = [];
        $externalAccess = false;
        $effectiveAccess = '';
        if ($target instanceof User && $target->id) {
            $scope = $this->taskModule()->accessFor($target);
            $effectiveAccess = !$scope->canView ? $this->_('No task access')
                : ($scope->canViewAll ? $this->_('Can view all tasks') : $this->_('Can view assigned, department and shared tasks'));

            $knownIds = array_keys($departments);
            $selectedDepartments = array_values(array_filter($knownIds, $target->hasRole(...)));
            foreach ($presets as $key => [$name, $grants]) {
                $role = $wire->roles->get($name);
                if ($role->id && $this->taskRoleMatches($role, $grants)) {
                    $knownIds[] = (int) $role->id;
                    if ($target->hasRole($role)) {
                        $externalAccess = $externalAccess || $selectedLevel !== 'none';
                        $selectedLevel = $key;
                    }
                }
            }
            foreach ($target->roles as $role) {
                if (in_array((int) $role->id, $knownIds, true)) {
                    continue;
                }
                foreach ($role->permissions as $permission) {
                    if (str_starts_with((string) $permission->name, 'kontor-tasks-')) {
                        $externalAccess = true;
                        break 2;
                    }
                }
            }
        }

        $this->setPageTitle($this->_('Kontor · Task access'));

        return $this->renderTemplate('task-access', compact(
            'employees', 'selectedId', 'departments', 'selectedDepartments', 'selectedLevel', 'externalAccess', 'effectiveAccess', 'error'
        ));
    }

    private function taskDepartmentLabel(Role $role): string
    {
        $name = preg_replace('/^kontor-(?:dept-)?/', '', (string) $role->name);

        return (string) ($role->meta('kontorTaskDepartmentLabel') ?: $role->get('title') ?: ucwords(str_replace('-', ' ', $name)));
    }

    /** @param list<string> $grants */
    private function taskRoleMatches(Role $role, array $grants): bool
    {
        $actual = array_values(array_diff($role->permissions->explode('name'), ['page-view']));
        sort($actual);
        sort($grants);

        return $actual === $grants;
    }

    /** @param list<string> $grants */
    private function createTaskAccessRole(string $name, array $grants, string $title = ''): Role
    {
        $wire = $this->wire();
        if ($wire->roles->get($name)->id) {
            throw new WireException($this->_('A role with this name already exists. Review it in Access → Roles.'));
        }
        $permissionPages = [];
        foreach ($grants as $grant) {
            $permission = $wire->permissions->get($grant);
            if (!$permission->id) {
                throw new WireException($this->_('A required task permission is missing.'));
            }
            $permissionPages[] = $permission;
        }
        $role = $wire->roles->add($name);
        if (!$role instanceof Role || !$role->id) {
            throw new WireException($this->_('Could not create the access role.'));
        }
        foreach ($permissionPages as $permission) {
            $role->addPermission($permission);
        }
        if (!$wire->roles->save($role)) {
            throw new WireException($this->_('Could not save the access role.'));
        }

        if ($title !== '') {
            $role->meta('kontorTaskDepartmentLabel', $title);
        }

        return $role;
    }

    public function ___executeTask(): string
    {
        $this->requireTasks();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $repository = $this->taskModule()->taskRepository();
        $task = $id !== '' ? $this->requireVisibleTask($id) : null;
        $archived = $task?->archived ?? false;
        $canSuggestTaskSteps = $task !== null && !$archived && $task->isOpen()
            && $this->aiReady() && $this->can('kontor-ai-action-approve')
            && $this->aiModule()->providerRegistry()->find('draft') !== null;
        $taskSteps = null;
        if ($this->wire()->input->post('suggest_task_steps')) {
            $this->requirePost();
            if (!$canSuggestTaskSteps) {
                throw new WirePermissionException($this->_('AI suggestions are unavailable for this task.'));
            }
            $uid = $task->uid->toString();
            $response = $this->aiModule()->drafting()->draft(
                $task->organizationId,
                [
                    'title' => $task->title,
                    'description' => mb_substr($task->description ?? '', 0, 8000),
                ],
                'Suggest three to five practical next steps as a plain-text checklist in the task language. '
                    . 'Use only the task context. If information is missing, suggest what the user should clarify.',
                (string) $this->wire()->user->id,
            );
            $draft = $response->output['draft'] ?? null;
            if ($response->success && is_string($draft) && trim($draft) !== '') {
                $this->wire()->session->set('kontorTaskSteps:' . $uid, mb_substr(trim($draft), 0, 6000));
            } else {
                $this->error($this->_('AI suggestions are unavailable right now. Try again later.'));
            }
            $this->wire()->session->redirect('../task/?id=' . rawurlencode($uid));
        }
        if ($canSuggestTaskSteps) {
            $session = $this->wire()->session;
            $key = 'kontorTaskSteps:' . $task->uid->toString();
            $savedSteps = $session->get($key);
            $session->remove($key);
            $taskSteps = is_string($savedSteps) ? $savedSteps : null;
        }

        $canAssign = $this->can('kontor-tasks-task-assign');
        $canEdit = $this->can('kontor-tasks-task-edit');
        $actor = $this->wire()->user;
        $currentUserId = (int) $actor->id;
        $departmentRoles = [];
        $departments = [];
        $assignees = [];
        if ($task === null) {
            $this->requirePermission('kontor-tasks-task-create');
            $this->requirePermission('kontor-tasks-task-view');
        }
        if ($canAssign && ($task === null || $canEdit)) {
            $module = $this->taskModule();
            foreach ($module->departmentRoles() as $role) {
                $roleId = (int) $role->id;
                if ($this->can('kontor-tasks-task-view-all') || $actor->hasRole($roleId)) {
                    $departmentRoles[$roleId] = $role;
                    $departments[$roleId] = $this->taskDepartmentLabel($role);
                }
            }
            foreach ($this->wire()->users as $candidate) {
                if ($module->accessFor($candidate)->canView
                    && ($candidate->isSuperuser() || $candidate->hasPermission('kontor-access'))) {
                    $assignees[(int) $candidate->id] = (string) ($candidate->get('title') ?: $candidate->name);
                }
            }
            asort($assignees, SORT_NATURAL | SORT_FLAG_CASE);
            asort($departments, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $contextType = '';
        $contextUid = '';
        $contextLabel = '';
        if ($task === null) {
            $contextType = $this->wire()->sanitizer->option(
                (string) ($this->wire()->input->post('context_entity_type')
                    ?: $this->wire()->input->get('entity_type')),
                ['contact', 'company'],
            ) ?? '';
            $contextUid = $this->wire()->sanitizer->text(
                (string) ($this->wire()->input->post('context_entity_uid')
                    ?: $this->wire()->input->get('entity_uid')),
            );
            if ($contextType !== '' && $contextUid !== '') {
                $contextLabel = $this->customerEntityLabel($contextType, $contextUid);
            } else {
                $contextType = '';
                $contextUid = '';
            }
        }

        $values = [
            'title' => $task?->title ?? ($contextLabel !== '' ? 'Follow up with ' . $contextLabel : ''),
            'description' => $task?->description ?? '',
            'priority' => $task?->priority ?? 'normal',
            'dueAt' => $task?->dueAt?->format('Y-m-d\TH:i') ?? '',
            'recurrenceRule' => $task?->recurrenceRule ?? '',
            'recurrenceUntil' => $task?->recurrenceUntil?->format('Y-m-d') ?? '',
            'assignedTo' => $task === null ? $currentUserId : $task->assignedTo,
            'visibility' => $task?->visibility ?? Task::VISIBILITY_ASSIGNEE,
            'departmentRoleId' => $task?->departmentRoleId,
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            if ($task !== null) {
                $this->requirePermission('kontor-tasks-task-edit');
                if ($archived) {
                    throw new WirePermissionException($this->_('Restore the task before editing it.'));
                }
            }
            $values = array_replace($values, [
                'title' => trim($this->wire()->sanitizer->text((string) $this->wire()->input->post('title'))),
                'description' => trim((string) $this->wire()->input->post('description')),
                'priority' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('priority'),
                    ['low', 'normal', 'high', 'urgent']
                ) ?? 'normal',
                'dueAt' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('due_at')),
                'recurrenceRule' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('recurrence_rule'),
                    ['daily', 'weekly', 'monthly', 'yearly']
                ) ?? '',
                'recurrenceUntil' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('recurrence_until')
                ),
            ]);
            if ($canAssign && $this->wire()->input->post('visibility') !== null) {
                $values['visibility'] = (string) $this->wire()->input->post('visibility');
                $values['departmentRoleId'] = $values['visibility'] === Task::VISIBILITY_DEPARTMENT
                    ? (int) $this->wire()->input->post('department_role_id') : null;
                $values['assignedTo'] = (int) $this->wire()->input->post('assigned_to') ?: null;
                $changedAudience = $task === null || $values['visibility'] !== $task->visibility
                    || $values['departmentRoleId'] !== $task->departmentRoleId || $values['assignedTo'] !== $task->assignedTo;
                if ($changedAudience && (!in_array($values['visibility'], [
                    Task::VISIBILITY_ASSIGNEE, Task::VISIBILITY_DEPARTMENT, Task::VISIBILITY_ORGANIZATION,
                ], true) || ($values['visibility'] === Task::VISIBILITY_ASSIGNEE && $values['assignedTo'] === null)
                    || ($values['visibility'] === Task::VISIBILITY_ORGANIZATION && !$this->can('kontor-tasks-task-view-all'))
                    || ($values['visibility'] === Task::VISIBILITY_DEPARTMENT && !isset($departmentRoles[$values['departmentRoleId']]))
                    || ($values['assignedTo'] !== null && !isset($assignees[$values['assignedTo']])))) {
                    $error = $this->_('Select a valid task audience and assignee.');
                }
                if ($error === '' && $changedAudience && $values['visibility'] === Task::VISIBILITY_DEPARTMENT
                    && $values['assignedTo'] !== null) {
                    $candidate = $this->wire()->users->get($values['assignedTo']);
                    if (!$candidate->hasRole($values['departmentRoleId']) && !$candidate->isSuperuser()
                        && !$candidate->hasPermission('kontor-tasks-task-view-all')) {
                        $error = $this->_('The assignee must belong to the selected department.');
                    }
                }
            }
            $dueAt = null;
            $recurrenceUntil = null;

            if ($values['title'] === '') {
                $error = $this->_('Task title is required.');
            }
            if ($error === '' && $values['dueAt'] !== '') {
                try {
                    $dueAt = new \DateTimeImmutable($values['dueAt']);
                } catch (\Throwable) {
                    $error = $this->_('Due date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceUntil'] !== '') {
                try {
                    $recurrenceUntil = new \DateTimeImmutable($values['recurrenceUntil']);
                } catch (\Throwable) {
                    $error = $this->_('Recurrence end date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceRule'] !== '' && $dueAt === null) {
                $error = $this->_('A recurring task needs a due date.');
            }

            if ($error === '') {
                if ($task === null && $contextType !== '' && $contextUid !== '') {
                    $this->requirePermission('kontor-tasks-relation-manage');
                }
                $apply = static function (Task $target) use ($values, $dueAt, $recurrenceUntil, $repository): Task {
                    $target->title = $values['title'];
                    $target->description = $values['description'] !== '' ? $values['description'] : null;
                    $target->priority = $values['priority'];
                    $target->dueAt = $dueAt;
                    $target->recurrenceRule = $values['recurrenceRule'] !== '' ? $values['recurrenceRule'] : null;
                    $target->recurrenceUntil = $recurrenceUntil;
                    $target->assignedTo = $values['assignedTo'];
                    $target->visibility = $values['visibility'];
                    $target->departmentRoleId = $values['departmentRoleId'];
                    $repository->save($target);

                    return $target;
                };
                $isNew = $task === null;
                if ($task === null) {
                    $task = $apply(Task::create($this->organizationUid(), $values['title']));
                } else {
                    $task = $repository->changeActiveTask($id, function (Task $current) use ($task, $apply): Task {
                        if (!$this->canViewTask($current)) {
                            throw new Wire404Exception($this->_('Task not found.'));
                        }
                        if ($current->assignedTo !== $task->assignedTo || $current->visibility !== $task->visibility
                            || $current->departmentRoleId !== $task->departmentRoleId) {
                            throw new WireException($this->_('Task access changed. Reload the task and try again.'));
                        }

                        return $apply($current);
                    });
                }
                if ($isNew && $contextType !== '' && $contextUid !== '') {
                    $this->taskModule()->relations()->linkToEntity(
                        $this->organizationUid(),
                        $task->uid->toString(),
                        $contextType,
                        $contextUid,
                    );
                }
                $this->audit(
                    'tasks',
                    'task',
                    $task->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    current: [
                        'title' => $task->title,
                        'priority' => $task->priority,
                        'dueAt' => $task->dueAt?->format(DATE_ATOM),
                        'recurrenceRule' => $task->recurrenceRule,
                    ],
                );
                $this->message($isNew ? $this->_('Task created.') : $this->_('Task updated.'));
                $this->wire()->session->redirect(
                    $this->canViewTask($task) ? '../task/?id=' . rawurlencode($task->uid->toString()) : '../tasks/'
                );
            }
        }

        $this->setPageTitle($task === null
            ? $this->_('Kontor · New task')
            : sprintf($this->_('Kontor · %s'), $task->title));
        $collaborationReady = $task !== null && $this->collaborationReady();
        $reminders = $task !== null
            ? $this->taskModule()->reminderRepository()->forTask($task->uid->toString())
            : [];
        $notes = [];
        $comments = [];
        $following = false;
        $user = $this->wire()->user;
        $canViewNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-view');
        $canCreateNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-create');
        $canArchiveNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-archive');
        $canViewComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-view');
        $canCreateComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-create');
        $canArchiveComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-archive');
        $canManageFollow = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-follow-manage');

        if ($collaborationReady) {
            $collaboration = $this->collaborationModule();
            $notes = $canViewNotes
                ? $collaboration->noteRepository()->forEntity('task', $task->uid->toString())
                : [];
            $comments = $canViewComments
                ? $collaboration->commentRepository()->forEntity('task', $task->uid->toString())
                : [];
            if ($canManageFollow) {
                $following = $collaboration->followerRepository()->isFollowing(
                    $this->organizationUid(),
                    'task',
                    $task->uid->toString(),
                    (int) $user->id,
                );
            }
            if ($canViewComments) {
                $collaboration->unreadStateService()->markRead(
                    $this->organizationUid(),
                    (int) $user->id,
                    'task',
                    $task->uid->toString(),
                );
            }
        }

        $assigneeLabel = $this->_('Unassigned');
        if ($task?->assignedTo !== null) {
            $assignee = $this->wire()->users->get($task->assignedTo);
            $assigneeLabel = $assignee->id > 0
                ? (string) ($assignee->get('title') ?: ucfirst((string) $assignee->name))
                : $this->_('Former user');
        }

        $taskDepartment = $task?->departmentRoleId ? $this->wire()->roles->get($task->departmentRoleId) : null;

        return $this->renderTemplate('task', [
            'task' => $task,
            'canSuggestTaskSteps' => $canSuggestTaskSteps,
            'taskSteps' => $taskSteps,
            'values' => $values,
            'error' => $error,
            'archived' => $archived,
            'reminders' => $reminders,
            'canManageReminders' => $user->isSuperuser()
                || $user->hasPermission('kontor-tasks-reminder-manage'),
            'collaborationReady' => $collaborationReady,
            'notes' => $notes,
            'comments' => $comments,
            'following' => $following,
            'canViewNotes' => $canViewNotes,
            'canCreateNotes' => $canCreateNotes,
            'canArchiveNotes' => $canArchiveNotes,
            'canViewComments' => $canViewComments,
            'canCreateComments' => $canCreateComments,
            'canArchiveComments' => $canArchiveComments,
            'canManageFollow' => $canManageFollow,
            'contextType' => $contextType,
            'contextUid' => $contextUid,
            'contextLabel' => $contextLabel,
            'relatedRecords' => $task !== null ? $this->taskRelatedRecords($task) : [],
            'authorLabels' => $this->collaborationAuthorLabels(array_merge($notes, $comments)),
            'assigneeLabel' => $assigneeLabel,
            'canAssign' => $canAssign && ($task === null || $canEdit),
            'canShareOrganization' => $this->can('kontor-tasks-task-view-all'),
            'departments' => $departments,
            'assignees' => $assignees,
            'departmentLabel' => $taskDepartment instanceof Role ? $this->taskDepartmentLabel($taskDepartment) : '',
            'canComplete' => $this->can('kontor-tasks-task-complete'),
            'canCancel' => $this->can('kontor-tasks-task-cancel'),
            'canEdit' => $this->can('kontor-tasks-task-edit'),
        ]);
    }

    public function ___executeTaskAction(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $actions = ['start', 'complete', 'cancel', 'reopen', 'archive', 'restore'];
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            $actions,
        );
        $this->requireAction($action, $actions);
        $this->requirePermission(match ($action) {
            'complete' => 'kontor-tasks-task-complete',
            'cancel' => 'kontor-tasks-task-cancel',
            default => 'kontor-tasks-task-edit',
        });
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $module = $this->taskModule();
        $this->requireVisibleTask($id);
        $authorize = function (Task $current): void {
            if (!$this->canViewTask($current)) {
                throw new Wire404Exception($this->_('Task not found.'));
            }
        };

        if ($action === 'start') {
            $module->workflow()->start($id, authorize: $authorize);
        } elseif ($action === 'complete') {
            $result = $module->workflow()->complete($id, authorize: $authorize);
            if ($result['next'] !== null) {
                $this->audit('tasks', 'task', $id, 'complete');
                $this->audit(
                    'tasks',
                    'task',
                    $result['next']->uid->toString(),
                    'recurrence_created',
                    current: ['sourceTaskUid' => $id, 'dueAt' => $result['next']->dueAt?->format(DATE_ATOM)],
                );
                $this->message($this->_('Task completed and the next occurrence was created.'));
                $this->wire()->session->redirect(
                    '../task/?id=' . rawurlencode($result['next']->uid->toString())
                );
            }
        } elseif ($action === 'cancel') {
            $module->workflow()->cancel($id, authorize: $authorize);
        } elseif ($action === 'reopen') {
            $module->workflow()->reopen($id, authorize: $authorize);
        } elseif ($action === 'restore') {
            $module->taskRepository()->restore($id, authorize: $authorize);
            $this->audit('tasks', 'task', $id, 'restored');
            $this->message($this->_('Task restored.'));
            $this->wire()->session->redirect('../tasks/');
        } else {
            $module->taskRepository()->archive($id, authorize: $authorize);
            $this->audit('tasks', 'task', $id, 'archived');
            $this->message($this->_('Task archived.'));
            $this->wire()->session->redirect('../tasks/?archived=1');
        }

        $this->audit('tasks', 'task', $id, $action);
        $this->message($action === 'reopen' ? $this->_('Task reopened.') : $this->_('Task updated.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($id));
    }

    public function ___executeTaskReminder(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-reminder-manage');
        $taskUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('task_uid')
        );
        $task = $this->requireVisibleTask($taskUid);
        if ($task->assignedTo === null) {
            throw new WireException($this->_('Assign the task before scheduling an email reminder.'));
        }

        $recipient = $this->wire()->users->get($task->assignedTo);
        $recipientEmail = strtolower(trim((string) $recipient->email));
        if (!$recipient->id || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new WireException($this->_('The assigned user needs a valid email address.'));
        }

        $rawRemindAt = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('remind_at')
        );
        if ($rawRemindAt === '') {
            throw new WireException($this->_('Reminder date is required.'));
        }
        try {
            $remindAt = new \DateTimeImmutable($rawRemindAt);
        } catch (\Throwable) {
            throw new WireException($this->_('Reminder date is invalid.'));
        }

        $fromAddress = strtolower(trim((string) $this->wire()->config->adminEmail));
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $fromAddress = 'notifications@kontor.local';
        }
        $result = $this->taskModule()->reminderDispatcher()->scheduleEmail(
            organizationUid: $task->organizationId,
            taskUid: $task->uid->toString(),
            taskTitle: $task->title,
            remindAt: $remindAt,
            recipientUserId: (int) $recipient->id,
            recipientEmail: $recipientEmail,
            fromAddress: $fromAddress,
            createdBy: (int) $this->wire()->user->id,
        );

        $this->audit(
            'tasks',
            'task_reminder',
            $result['reminder']->uid->toString(),
            'scheduled',
            current: [
                'taskUid' => $task->uid->toString(),
                'remindAt' => $remindAt->format(DATE_ATOM),
                'channel' => 'email',
                'jobUid' => $result['jobUid'],
            ],
        );
        $this->message($this->_('Email reminder scheduled.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($task->uid->toString()));
    }

    public function ___executeCollaboration(): string
    {
        $this->requireCollaboration();
        $this->requirePermission('kontor-collaboration-comment-view');
        $module = $this->collaborationModule();
        $this->setPageTitle($this->_('Kontor · Collaboration'));

        $canViewNotes = $this->wire()->user->isSuperuser()
            || $this->wire()->user->hasPermission('kontor-collaboration-note-view');
        $notes = $canViewNotes
            ? $module->noteRepository()->findRecent($this->organizationUid(), 50)
            : [];
        $comments = $module->commentRepository()->findRecent($this->organizationUid(), 50);
        $taskVisibility = [];
        $canViewRecord = function (Note|Comment $record) use (&$taskVisibility): bool {
            if ($record->entityType !== 'task') {
                return true;
            }

            return $taskVisibility[$record->entityUid] ??= $this->canViewTaskByUid($record->entityUid);
        };
        $notes = array_values(array_filter($notes, $canViewRecord));
        $comments = array_values(array_filter($comments, $canViewRecord));
        $records = array_merge($notes, $comments);
        $selectedView = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('view'),
            ['all', 'comments', 'notes'],
        ) ?: 'all';
        if (!$canViewNotes && $selectedView === 'notes') {
            $selectedView = 'all';
        }

        return $this->renderTemplate('collaboration', [
            'notes' => $notes,
            'comments' => $comments,
            'entityLinks' => $this->collaborationEntityLinks($records),
            'authorLabels' => $this->collaborationAuthorLabels($records),
            'canViewNotes' => $canViewNotes,
            'canViewTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-view'),
            'canCreateTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-create'),
            'query' => trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q'))),
            'selectedView' => $selectedView,
        ]);
    }

    public function ___executeCollaborationPost(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $kind = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('kind'),
            ['note', 'comment']
        );
        $this->requireAction($kind, ['note', 'comment']);
        $this->requirePermission($kind === 'note'
            ? 'kontor-collaboration-note-create'
            : 'kontor-collaboration-comment-create');
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('entity_type'),
            ['task']
        );
        $this->requireAction($entityType, ['task']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->requireVisibleTask($entityUid);
        $body = trim((string) $this->wire()->input->post('body'));

        if ($body === '') {
            throw new WireException($this->_('Collaboration text cannot be empty.'));
        }
        $body = mb_substr($body, 0, 10000);
        $module = $this->collaborationModule();
        $notificationCount = 0;

        if ($kind === 'note') {
            $record = Note::create(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
            );
            $module->noteRepository()->save($record);
        } else {
            $parentUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('parent_uid')
            );
            if ($parentUid !== '') {
                $parent = $module->commentRepository()->require($parentUid);
                if (
                    !hash_equals($parent->organizationId, $this->organizationUid())
                    || $parent->entityType !== $entityType
                    || !hash_equals($parent->entityUid, $entityUid)
                ) {
                    throw new WirePermissionException($this->_('Reply target does not belong to this thread.'));
                }
            }
            $record = $module->commentService()->post(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
                $parentUid !== '' ? $parentUid : null,
            );
            $notificationCount = count($this->queueCollaborationNotifications($record, $task->title));
        }

        $this->audit(
            'collaboration',
            $kind,
            $record->uid->toString(),
            'created',
            current: ['entityType' => $entityType, 'entityUid' => $entityUid],
        );
        $this->message(match (true) {
            $kind === 'note' => $this->_('Note added.'),
            $notificationCount > 0 => sprintf(
                $this->_('Comment posted; %d notification(s) queued.'),
                $notificationCount,
            ),
            default => $this->_('Comment posted.'),
        });
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeCollaborationAction(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive_note', 'archive_comment', 'toggle_follow']
        );
        $this->requireAction($action, ['archive_note', 'archive_comment', 'toggle_follow']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->requireVisibleTask($entityUid);
        $module = $this->collaborationModule();

        if ($action === 'toggle_follow') {
            $this->requirePermission('kontor-collaboration-follow-manage');
            $userId = (int) $this->wire()->user->id;
            $following = $module->followerRepository()->isFollowing(
                $this->organizationUid(),
                'task',
                $entityUid,
                $userId,
            );
            if ($following) {
                $module->followerRepository()->unfollow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            } else {
                $module->followerRepository()->follow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            }
            $this->audit(
                'collaboration',
                'follower',
                $entityUid,
                $following ? 'unfollowed' : 'followed',
            );
            $this->message($following ? $this->_('Thread unfollowed.') : $this->_('Thread followed.'));
        } else {
            $kind = $action === 'archive_note' ? 'note' : 'comment';
            $this->requirePermission('kontor-collaboration-' . $kind . '-archive');
            $recordUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('record_uid'));
            $repository = $kind === 'note'
                ? $module->noteRepository()
                : $module->commentRepository();
            $record = $repository->require($recordUid);
            if (
                !hash_equals($record->organizationId, $this->organizationUid())
                || $record->entityType !== 'task'
                || !hash_equals($record->entityUid, $entityUid)
            ) {
                throw new WirePermissionException($this->_('Collaboration record does not belong to this task.'));
            }
            $repository->archive($recordUid);
            $this->audit('collaboration', $kind, $recordUid, 'archived');
            $this->message($kind === 'note' ? $this->_('Note archived.') : $this->_('Comment archived.'));
        }

        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeReports(): string
    {
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-view');
        $explainRequested = $this->wire()->input->post('explain_report') === '1';
        if ($explainRequested) {
            $this->requirePost();
            $this->requirePermission('kontor-ai-action-approve');
        }
        $this->setPageTitle($this->_('Kontor · Reports'));
        $module = $this->reportsModule();
        $providers = $module->providerRegistry()->all();
        $requestedKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('provider')
        );
        $providerKey = $requestedKey !== '' ? $requestedKey : (string) array_key_first($providers);
        $provider = $providerKey !== '' && $module->providerRegistry()->has($providerKey)
            ? $module->providerRegistry()->get($providerKey)
            : null;
        $filters = [];
        $groupBy = [];
        $result = null;
        $fieldOptions = [];
        $fieldLabels = [];

        if ($providerKey === 'crm_pipeline' && $this->crmReady()) {
            /** @var KontorCRM $crm */
            $crm = $this->wire()->modules->get('KontorCRM');
            $pipelineOptions = [];
            $stageOptions = [];
            foreach ($crm->pipelineRepository()->forOrganization($this->organizationUid()) as $pipeline) {
                $pipelineUid = $pipeline->uid->toString();
                $pipelineOptions[$pipelineUid] = $pipeline->name;
                foreach ($crm->stageRepository()->forPipeline($pipelineUid) as $stage) {
                    $stageOptions[$stage->uid->toString()] = $pipeline->name . ' · '
                        . ($stage->displayNameIn('en') ?? ucwords(str_replace('_', ' ', $stage->nameKey)));
                }
            }
            $fieldOptions = [
                'pipeline_uid' => $pipelineOptions,
                'stage_uid' => $stageOptions,
                'status' => ['open' => $this->_('Open'), 'won' => $this->_('Won'), 'lost' => $this->_('Lost')],
            ];
            $fieldLabels = [
                'pipeline_uid' => $this->_('Sales pipeline'),
                'stage_uid' => $this->_('Sales stage'),
                'status' => $this->_('Deal status'),
                'deal_count' => $this->_('Deals'),
                'total_value_minor' => $this->_('Pipeline value'),
            ];
        }

        if ($provider !== null) {
            $schema = $provider->schema();
            foreach ($schema->filterableFields as $field) {
                $value = trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->get('filter_' . $field)
                ));
                if ($value !== '') {
                    $filters[$field] = $value;
                }
            }
            $requestedGroup = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->get('group_by')
            );
            if ($requestedGroup !== '' && in_array($requestedGroup, $schema->groupableFields, true)) {
                $groupBy[] = $requestedGroup;
            }

            if ((string) $this->wire()->input->get('run') === '1') {
                $result = $module->reportBuilder()->run(
                    $providerKey,
                    new ReportQuery($this->organizationUid(), $filters, $groupBy),
                );
                $this->audit(
                    'reports',
                    'report',
                    $providerKey,
                    'run',
                    metadata: ['filters' => $filters, 'groupBy' => $groupBy, 'rowCount' => count($result->rows)],
                );
            }
        }

        $canExplainReport = $result !== null && $result->rows !== []
            && $this->can('kontor-ai-action-approve') && $this->aiReady()
            && $this->aiModule()->providerRegistry()->find('draft') !== null;
        $reportExplanation = null;
        $reportAiError = null;
        $reportAiExcerpt = '';
        if ($canExplainReport) {
            $reportAiExcerpt = "Report: " . $provider->title() . "\nMatching rows: " . count($result->rows);
            foreach ($result->totals as $field => $total) {
                if (isset($schema->fields[$field]) && (is_int($total) || is_float($total) || (is_string($total) && is_numeric($total)))) {
                    $reportAiExcerpt .= "\n" . ($fieldLabels[$field] ?? $field) . " ({$field}): " . $total;
                }
            }
            $reportAiExcerpt = mb_substr($reportAiExcerpt, 0, 4000);
        }
        if ($explainRequested) {
            if (!$canExplainReport) {
                throw new WirePermissionException($this->_('AI explanation is unavailable for this report.'));
            }
            $reportAiExcerpt = trim((string) $this->wire()->input->post('report_ai_excerpt'));
            if ($reportAiExcerpt === '' || mb_strlen($reportAiExcerpt) > 4000) {
                $reportAiError = $this->_('Enter up to 4,000 characters of report data.');
            } else {
                $response = $this->aiModule()->drafting()->draft(
                    $this->organizationUid(),
                    ['reportExcerpt' => $reportAiExcerpt],
                    'Explain these report totals and suggest what to investigate. Keep the language of the excerpt. Do not infer trends from a single snapshot, invent facts, or guess currencies or units.',
                    (string) $this->wire()->user->id,
                );
                $draft = $response->success ? ($response->output['draft'] ?? null) : null;
                if (is_string($draft) && trim($draft) !== '') {
                    $reportExplanation = $draft;
                } else {
                    $reportAiError = $this->_('The AI provider did not return an explanation. Try again.');
                }
            }
        }

        return $this->renderTemplate('reports', [
            'providers' => $providers,
            'providerKey' => $providerKey,
            'provider' => $provider,
            'filters' => $filters,
            'groupBy' => $groupBy,
            'canExplainReport' => $canExplainReport,
            'reportAiExcerpt' => $reportAiExcerpt,
            'reportExplanation' => $reportExplanation,
            'reportAiError' => $reportAiError,
            'result' => $result,
            'fieldOptions' => $fieldOptions,
            'fieldLabels' => $fieldLabels,
            'currencyCode' => $this->organization()->defaultCurrency,
            'schedules' => $module->scheduledReportRepository()->forOrganization(
                $this->organizationUid()
            ),
            'canManageSchedules' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-schedule-manage'),
            'canExport' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-report-export'),
        ]);
    }

    public function ___executeReportsSchedule(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        if ($name === '') {
            throw new WireException($this->_('A schedule name is required.'));
        }
        $recurrence = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('recurrence'),
            ['daily', 'weekly', 'monthly', 'yearly'],
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('format'),
            ['csv', 'json', 'xlsx', 'pdf'],
        );
        if ($recurrence === null || $format === null) {
            throw new WireException($this->_('Invalid schedule settings.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }

        $firstRunInput = trim((string) $this->wire()->input->post('first_run_at'));
        try {
            $firstRunAt = $firstRunInput !== ''
                ? new \DateTimeImmutable($firstRunInput)
                : new \DateTimeImmutable();
        } catch (\Exception) {
            throw new WireException($this->_('Invalid first run time.'));
        }

        $schedule = $module->scheduledReports()->schedule(
            organizationUid: $this->organizationUid(),
            providerKey: $providerKey,
            name: $name,
            recurrenceRule: $recurrence,
            filters: $filters,
            groupBy: array_values(array_map('strval', $groupBy)),
            format: $format,
            firstRunAt: $firstRunAt,
            createdBy: (int) $this->wire()->user->id,
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $schedule->uid->toString(),
            'created',
            current: [
                'provider' => $providerKey,
                'recurrence' => $recurrence,
                'format' => $format,
                'nextRunAt' => $firstRunAt->format(DATE_ATOM),
            ],
        );
        $this->message($this->_('Scheduled report created.'));
        $this->wire()->session->redirect('../reports/?provider='.rawurlencode($providerKey));
    }

    public function ___executeReportsDispatchDue(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $jobs = $this->reportsModule()->scheduledReportDispatcher()->dispatchDue(
            $this->organizationUid()
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $this->organizationUid(),
            'dispatched',
            metadata: ['jobCount' => count($jobs), 'jobUids' => array_values($jobs)],
        );
        $this->message(sprintf($this->_('%d due report job(s) queued.'), count($jobs)));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsScheduleAction(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('uid'));
        $schedule = $this->reportsModule()->scheduledReportRepository()->require($uid);
        if (!hash_equals($schedule->organizationId, $this->organizationUid())) {
            throw new WirePermissionException($this->_('Scheduled report does not belong to this organization.'));
        }

        $this->reportsModule()->scheduledReportRepository()->archive($uid);
        $this->audit('reports', 'scheduled_report', $uid, 'archived');
        $this->message($this->_('Scheduled report archived.'));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsExport(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-export');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        foreach ($filters as $field => $value) {
            if (!is_string($field) || !is_scalar($value)) {
                throw new WireException($this->_('Invalid report filter.'));
            }
            $filters[$field] = mb_substr(trim((string) $value), 0, 191);
        }
        $groupBy = array_values(array_filter(
            $groupBy,
            static fn (mixed $field): bool => is_string($field),
        ));
        $result = $module->reportBuilder()->run(
            $providerKey,
            new ReportQuery($this->organizationUid(), $filters, $groupBy),
        );
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_report_');
        if ($temporary === false) {
            throw new WireException($this->_('Could not create a report export file.'));
        }
        $path = $temporary . '.csv';
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        $fields = array_keys($module->providerRegistry()->get($providerKey)->schema()->fields);
        $module->reportExporter()->export($result, $fields, 'csv', $path);
        $this->audit(
            'reports',
            'report',
            $providerKey,
            'exported',
            metadata: ['format' => 'csv', 'rowCount' => count($result->rows)],
        );
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => preg_replace('/[^a-z0-9_-]+/i', '-', $providerKey) . '-report.csv',
            'exit' => true,
        ]);
    }
}
