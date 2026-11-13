<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Application\TaskAccessScope;
use Kontor\Tasks\Domain\Task;
use PDO;
use ProcessWire\KontorTasks;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait TaskTools
{
    private const TASK_STATUSES = ['open', 'in_progress', 'done', 'cancelled'];
    private const TASK_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const TASK_VISIBILITIES = [Task::VISIBILITY_ASSIGNEE, Task::VISIBILITY_DEPARTMENT, Task::VISIBILITY_ORGANIZATION];
    private const TASK_RECURRENCES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @return list<array<string, mixed>> */
    private function taskTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorTasks')) {
            return [];
        }

        $fields = [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'description' => ['type' => ['string', 'null'], 'maxLength' => 16000],
            'priority' => ['type' => 'string', 'enum' => self::TASK_PRIORITIES],
            'dueAt' => ['type' => ['string', 'null'], 'maxLength' => 35, 'description' => 'ISO 8601 date and time with an explicit offset.'],
            'recurrenceRule' => ['type' => ['string', 'null'], 'enum' => [...self::TASK_RECURRENCES, null]],
            'recurrenceUntil' => ['type' => ['string', 'null'], 'maxLength' => 10, 'description' => 'Recurrence end in YYYY-MM-DD format.'],
            'assignedTo' => ['type' => ['integer', 'null'], 'minimum' => 1],
            'visibility' => ['type' => 'string', 'enum' => self::TASK_VISIBILITIES],
            'departmentRoleId' => ['type' => ['integer', 'null'], 'minimum' => 1],
        ];
        // The gateway accepts closed schemas. Domain checks enforce permissions and field combinations.
        $taskDocument = ['type' => 'object', 'properties' => $fields, 'additionalProperties' => false];
        $key = $this->idempotencySchema();

        return [
            $this->tool('kontor_tasks_list', 'List Kontor tasks', 'Search or list tasks visible to the linked user.',
                [$this, 'mcpKontorTasksList'], 'read', [
                    'query' => ['type' => 'string', 'maxLength' => 200],
                    'status' => ['type' => 'string', 'enum' => self::TASK_STATUSES],
                    'priority' => ['type' => 'string', 'enum' => self::TASK_PRIORITIES],
                    'archived' => ['type' => 'boolean'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ]),
            $this->tool('kontor_task_get', 'Get a Kontor task', 'Read a task visible to the linked user by its UID.',
                [$this, 'mcpKontorTaskGet'], 'read', ['uid' => $this->uidSchema()], ['uid']),
            $this->tool('kontor_task_assignment_options', 'Find task assignees and audiences',
                'Find eligible users and departments. Assignment requires the matching Workspace permission.',
                [$this, 'mcpKontorTaskAssignmentOptions'], 'read', [
                    'query' => ['type' => 'string', 'maxLength' => 100],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ]),
            $this->tool('kontor_task_create', 'Create a Kontor task',
                'Create a task. With no audience specified, the task is visible only to its creator and users with view-all access.',
                [$this, 'mcpKontorTaskCreate'], 'draft', [
                    'fields' => $taskDocument + ['required' => ['title']],
                    'idempotency_key' => $key,
                    'related_customer' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['contact', 'company']],
                            'uid' => $this->uidSchema(),
                        ],
                        'required' => ['type', 'uid'],
                        'additionalProperties' => false,
                        'description' => 'Optional visible CRM contact or company to link, as when creating a task from its Workspace record.',
                    ],
                ], ['fields', 'idempotency_key']),
            $this->tool('kontor_task_update', 'Update a Kontor task',
                'Update selected fields of a visible, active task. Use the task action tool to change status.',
                [$this, 'mcpKontorTaskUpdate'], 'draft', [
                    'uid' => $this->uidSchema(),
                    'fields' => $taskDocument + ['minProperties' => 1],
                    'idempotency_key' => $key,
                ], ['uid', 'fields', 'idempotency_key']),
            $this->tool('kontor_task_action', 'Change Kontor task status',
                'Start, complete or reopen a task. Completing a recurring task can create its next occurrence.',
                [$this, 'mcpKontorTaskAction'], 'draft', [
                    'uid' => $this->uidSchema(),
                    'action' => ['type' => 'string', 'enum' => ['start', 'complete', 'reopen']],
                    'idempotency_key' => $key,
                ], ['uid', 'action', 'idempotency_key']),
            $this->tool('kontor_task_reviewed_action', 'Cancel, archive or restore a Kontor task',
                'Apply the specified lifecycle action to a visible task after explicit review.',
                [$this, 'mcpKontorTaskReviewedAction'], 'draft', [
                    'uid' => $this->uidSchema(),
                    'action' => ['type' => 'string', 'enum' => ['cancel', 'archive', 'restore']],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_REVIEWED_KONTOR_TASK_ACTION'],
                ], ['uid', 'action', 'idempotency_key', 'confirmation'], destructive: true),
        ];
    }

    public function mcpKontorTasksList(
        string $query = '',
        ?string $status = null,
        ?string $priority = null,
        bool $archived = false,
        int $limit = 25,
        int $offset = 0,
    ): array {
        [$module, , $access, $organizationUid] = $this->taskContext();
        $items = $module->taskRepository()->findMatching(
            $organizationUid,
            $query,
            $status,
            $priority,
            $archived,
            $limit + 1,
            $offset,
            access: $access,
        );

        return [
            'items' => array_map($this->taskRecord(...), array_slice($items, 0, $limit)),
            'nextOffset' => count($items) > $limit && $offset + $limit <= 10000 ? $offset + $limit : null,
        ];
    }

    public function mcpKontorTaskGet(string $uid): array
    {
        [$module, , $access, $organizationUid] = $this->taskContext();
        $task = $module->taskRepository()->find($uid);
        $this->requireVisibleMcpTask($task, $access, $organizationUid);

        return ['record' => $this->taskRecord($task)];
    }

    public function mcpKontorTaskAssignmentOptions(string $query = '', int $offset = 0): array
    {
        [$module, $actor, $access] = $this->taskContext('assign');
        if (!$this->taskPermission($actor, 'create') && !$this->taskPermission($actor, 'edit')) {
            throw new WirePermissionException('Task create or edit permission is required.');
        }

        $items = [];
        $position = 0;
        foreach ($this->wire()->users as $user) {
            $name = (string) ($user->get('title') ?: $user->name);
            if (!$module->accessFor($user)->canView || $user->isLocked()
                || ($query !== '' && mb_stripos($name, $query) === false)) {
                continue;
            }
            if ($position++ < $offset) {
                continue;
            }
            $items[] = ['id' => (int) $user->id, 'name' => $name];
            if (count($items) > 50) {
                break;
            }
        }
        $departments = [];
        foreach ($module->departmentRoles() as $role) {
            if ($access->canViewAll || $actor->hasRole((int) $role->id)) {
                $label = $role->meta('kontorTaskDepartmentLabel') ?: $role->get('title') ?: $role->name;
                $departments[] = ['id' => (int) $role->id, 'name' => (string) $label];
            }
        }

        return [
            'assignees' => array_slice($items, 0, 50),
            'nextOffset' => count($items) > 50 && $offset + 50 <= 10000 ? $offset + 50 : null,
            'departments' => $departments,
            'visibilities' => $access->canViewAll ? self::TASK_VISIBILITIES : [Task::VISIBILITY_ASSIGNEE, Task::VISIBILITY_DEPARTMENT],
        ];
    }

    public function mcpKontorTaskCreate(array $fields, string $idempotency_key, ?array $related_customer = null): array
    {
        [$module, $actor, $access, $organizationUid] = $this->taskContext('create');

        if ($related_customer !== null) {
            $this->requireMcpTaskCustomer($actor, $organizationUid, $related_customer);
            ksort($related_customer);
        }
        return $this->writeMcpTask(
            $module,
            $actor,
            $access,
            $organizationUid,
            $idempotency_key,
            'create',
            ['fields' => $fields, 'related_customer' => $related_customer],
            function () use ($module, $actor, $access, $organizationUid, $fields, $related_customer): array {
                $task = Task::create($organizationUid, '', assignedTo: (int) $actor->id, visibility: Task::VISIBILITY_ASSIGNEE);
                $this->applyMcpTaskFields($task, $fields, $module, $actor, $access);
                $module->taskRepository()->save($task);
                $uid = $task->uid->toString();
                if ($related_customer !== null) {
                    $module->relations()->linkToEntity($organizationUid, $uid, $related_customer['type'], $related_customer['uid']);
                }

                return ['taskUid' => $uid];
            },
        );
    }

    public function mcpKontorTaskUpdate(string $uid, array $fields, string $idempotency_key): array
    {
        [$module, $actor, $access, $organizationUid] = $this->taskContext('edit');
        $repository = $module->taskRepository();

        return $this->writeMcpTask($module, $actor, $access, $organizationUid, $idempotency_key, 'update', ['uid' => $uid, 'fields' => $fields],
            function () use ($repository, $uid, $fields, $module, $actor, $access, $organizationUid): array {
                $this->requireVisibleMcpTask($repository->find($uid), $access, $organizationUid);

                return $repository->changeActiveTask($uid, function (Task $task) use ($repository, $fields, $module, $actor, $access, $organizationUid): array {
                    $this->requireVisibleMcpTask($task, $access, $organizationUid);
                    $this->applyMcpTaskFields($task, $fields, $module, $actor, $access);
                    $repository->save($task);

                    return ['taskUid' => $task->uid->toString()];
                });
            });
    }

    public function mcpKontorTaskAction(string $uid, string $action, string $idempotency_key): array
    {
        if (!in_array($action, ['start', 'complete', 'reopen'], true)) {
            throw new WireException('Unsupported task action.');
        }

        return $this->performMcpTaskAction($uid, $action, $idempotency_key);
    }

    public function mcpKontorTaskReviewedAction(string $uid, string $action, string $idempotency_key, string $confirmation): array
    {
        if ($confirmation !== 'APPLY_REVIEWED_KONTOR_TASK_ACTION'
            || !in_array($action, ['cancel', 'archive', 'restore'], true)) {
            throw new WireException('A reviewed task action and its confirmation are required.');
        }

        return $this->performMcpTaskAction($uid, $action, $idempotency_key);
    }

    private function performMcpTaskAction(string $uid, string $action, string $key): array
    {
        $permission = in_array($action, ['complete', 'cancel'], true) ? $action : 'edit';
        [$module, $actor, $access, $organizationUid] = $this->taskContext($permission);
        $authorize = fn (Task $task) => $this->requireVisibleMcpTask($task, $access, $organizationUid);

        return $this->writeMcpTask($module, $actor, $access, $organizationUid, $key, $action, ['uid' => $uid],
            function () use ($module, $uid, $action, $authorize, $access, $organizationUid): array {
                $this->requireVisibleMcpTask($module->taskRepository()->find($uid), $access, $organizationUid);
                $next = null;
                switch ($action) {
                    case 'archive':
                        $module->taskRepository()->archive($uid, authorize: $authorize);
                        break;
                    case 'restore':
                        $module->taskRepository()->restore($uid, authorize: $authorize);
                        break;
                    case 'complete':
                        $next = $module->workflow()->complete($uid, authorize: $authorize)['next'];
                        break;
                    default:
                        $module->workflow()->{$action}($uid, authorize: $authorize);
                        break;
                }

                return ['taskUid' => $uid, 'nextTaskUid' => $next?->uid->toString()];
            });
    }

    /** @return array{KontorTasks, User, TaskAccessScope, string} */
    private function taskContext(?string $permission = null): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->wire()->modules->isInstalled('KontorTasks')) {
            throw new WirePermissionException('A linked MCP user and the Tasks component are required.');
        }
        $module = $this->wire()->modules->get('KontorTasks');
        $access = $module->accessFor($actor);
        if (!$access->canView || ($permission !== null && !$this->taskPermission($actor, $permission))) {
            throw new WirePermissionException('The linked user lacks the required task permission.');
        }

        $container = $this->wire()->modules->get('Kontor')->container();
        $organization = $container->get(OrganizationRepository::class)->defaultOrganization('US', 'en', 'USD');

        return [$module, $actor, $access, $organization->uid->toString()];
    }

    private function requireMcpTaskCustomer(User $actor, string $organizationUid, array $customer): void
    {
        $type = $customer['type'] ?? '';
        $uid = $customer['uid'] ?? '';
        if (!in_array($type, ['contact', 'company'], true) || !preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid)) {
            throw new WireException('Supply a valid contact or company UID to link.');
        }
        if (!$actor->isSuperuser() && (!$actor->hasPermission('kontor-tasks-relation-manage')
            || !$actor->hasPermission('kontor-contacts-' . $type . '-view'))) {
            throw new WirePermissionException('Linking a task requires relation management and CRM record view permissions.');
        }
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorContacts')) {
            throw new Wire404Exception('Related CRM record not found.');
        }
        $contacts = $modules->get('KontorContacts');
        $customer = $type === 'contact' ? $contacts->contactRepository()->find($uid) : $contacts->companyRepository()->find($uid);
        if ($customer === null || !hash_equals($organizationUid, $customer->organizationId)) {
            throw new Wire404Exception('Related CRM record not found.');
        }
    }

    private function taskPermission(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission('kontor-tasks-task-' . $permission);
    }

    private function requireVisibleMcpTask(?Task $task, TaskAccessScope $access, string $organizationUid): void
    {
        if ($task === null || !hash_equals($organizationUid, $task->organizationId) || !$access->allows($task)) {
            throw new Wire404Exception('Task not found.');
        }
    }

    private function applyMcpTaskFields(Task $task, array $fields, KontorTasks $module, User $actor, TaskAccessScope $access): void
    {
        if (array_key_exists('title', $fields)) {
            $task->title = trim($this->wire()->sanitizer->text((string) $fields['title']));
        }
        if ($task->title === '') {
            throw new WireException('Task title is required.');
        }
        if (array_key_exists('description', $fields)) {
            $description = trim((string) $fields['description']);
            $task->description = $description !== '' ? $description : null;
        }
        $task->priority = $fields['priority'] ?? $task->priority;
        if (array_key_exists('dueAt', $fields)) {
            $task->dueAt = $this->parseMcpTaskDate($fields['dueAt'], false);
        }
        if (array_key_exists('recurrenceRule', $fields)) {
            $task->recurrenceRule = $fields['recurrenceRule'];
        }
        if (array_key_exists('recurrenceUntil', $fields)) {
            $task->recurrenceUntil = $this->parseMcpTaskDate($fields['recurrenceUntil'], true);
        }
        if ($task->recurrenceRule !== null && $task->dueAt === null) {
            throw new WireException('A recurring task needs a due date.');
        }

        $visibility = $fields['visibility'] ?? $task->visibility;
        $assignedTo = array_key_exists('assignedTo', $fields) ? $fields['assignedTo'] : $task->assignedTo;
        if ($visibility !== Task::VISIBILITY_DEPARTMENT && isset($fields['departmentRoleId'])) {
            throw new WireException('A department ID requires the department audience.');
        }
        $departmentId = $visibility === Task::VISIBILITY_DEPARTMENT
            ? (array_key_exists('departmentRoleId', $fields) ? $fields['departmentRoleId'] : $task->departmentRoleId)
            : null;
        if ($visibility === $task->visibility && $assignedTo === $task->assignedTo && $departmentId === $task->departmentRoleId) {
            return;
        }

        if (!$this->taskPermission($actor, 'assign')) {
            throw new WirePermissionException('Task assignment permission is required to change the audience or assignee.');
        }
        if (($visibility === Task::VISIBILITY_ASSIGNEE && $assignedTo === null)
            || ($visibility === Task::VISIBILITY_ORGANIZATION && !$access->canViewAll)) {
            throw new WirePermissionException('Select a task audience permitted by your Workspace role.');
        }

        if ($visibility === Task::VISIBILITY_DEPARTMENT) {
            $roleIds = array_map(static fn ($role): int => (int) $role->id, $module->departmentRoles());
            if (!in_array($departmentId, $roleIds, true)
                || (!$access->canViewAll && !$actor->hasRole($departmentId))) {
                throw new WirePermissionException('Select a permitted task department.');
            }
        }

        if ($assignedTo !== null) {
            $assignee = $this->wire()->users->get($assignedTo);
            $assigneeAccess = $module->accessFor($assignee);
            if (!$assigneeAccess->canView || $assignee->isLocked()
                || ($departmentId !== null && !$assigneeAccess->canViewAll && !$assignee->hasRole($departmentId))) {
                throw new WirePermissionException('Select an eligible assignee for the task audience.');
            }
        }

        $task->visibility = $visibility;
        $task->assignedTo = $assignedTo;
        $task->departmentRoleId = $departmentId;
    }

    private function parseMcpTaskDate(?string $value, bool $dateOnly): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $pattern = $dateOnly ? '/^\d{4}-\d{2}-\d{2}$/D' : '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/D';
        if (!preg_match($pattern, $value)) {
            throw new WireException($dateOnly ? 'Use YYYY-MM-DD for the recurrence end.' : 'Use an ISO 8601 due date and time with an explicit offset.');
        }
        $format = $dateOnly ? 'Y-m-d' : (strlen($value) === 20 || strlen($value) === 25 ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:iP');
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new WireException($dateOnly ? 'Use YYYY-MM-DD for the recurrence end.' : 'Use an ISO 8601 due date and time with an explicit offset.');
        }
        if ($dateOnly) {
            return $date;
        }

        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private function taskRecord(Task $task): array
    {
        return [
            'uid' => $task->uid->toString(),
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'assignedTo' => $task->assignedTo,
            'visibility' => $task->visibility,
            'departmentRoleId' => $task->departmentRoleId,
            'startAt' => $task->startAt?->format(DateTimeInterface::ATOM),
            'dueAt' => $task->dueAt?->format(DateTimeInterface::ATOM),
            'completedAt' => $task->completedAt?->format(DateTimeInterface::ATOM),
            'recurrenceRule' => $task->recurrenceRule,
            'recurrenceUntil' => $task->recurrenceUntil?->format('Y-m-d'),
            'archived' => $task->archived,
        ];
    }

    /**
     * Commit the task, its audit event and the idempotency receipt together. A concurrent
     * duplicate receipt rolls back the second mutation. Receipts never cache task contents.
     *
     * @param callable(): array{taskUid:string,nextTaskUid?:?string} $operation
     */
    private function writeMcpTask(
        KontorTasks $module,
        User $actor,
        TaskAccessScope $access,
        string $organizationUid,
        string $key,
        string $action,
        array $arguments,
        callable $operation,
    ): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Use an idempotency key of 8 to 160 safe characters.');
        }
        if (isset($arguments['fields'])) {
            ksort($arguments['fields']);
        }
        ksort($arguments);

        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $pdo = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $repository = $module->taskRepository();
            if (isset($arguments['uid'])) {
                $this->requireVisibleMcpTask($repository->find($arguments['uid']), $access, $organizationUid);
            }
            $performed = false;
            $result = $idempotency->remember(
                $organizationUid,
                'mcp:task:' . $actor->id . ':' . hash('sha256', $key),
                ['action' => $action, 'arguments' => $arguments],
                function () use ($operation, $container, $organizationUid, $actor, $action, &$performed): array {
                    $body = $operation();
                    $performed = true;
                    $organizationId = $container->get(OrganizationRepository::class)->internalIdOf($organizationUid);
                    $audit = $container->get(AuditLogger::class);
                    $audit->record($organizationId, 'tasks', 'task', $body['taskUid'], match ($action) {
                        'create' => 'created', 'update' => 'updated', 'archive' => 'archived', 'restore' => 'restored', default => $action,
                    }, 'user', (string) $actor->id, metadata: ['transport' => 'mcp']);
                    if (($body['nextTaskUid'] ?? null) !== null) {
                        $audit->record($organizationId, 'tasks', 'task', $body['nextTaskUid'], 'recurrence_created',
                            'user', (string) $actor->id, current: ['sourceTaskUid' => $body['taskUid']], metadata: ['transport' => 'mcp']);
                    }

                    return ['status' => 200, 'body' => $body];
                },
            );
            $receipt = $result->body + ['action' => $action];
            $task = $repository->find($receipt['taskUid']);
            if (!$performed) {
                $this->requireVisibleMcpTask($task, $access, $organizationUid);
            } elseif ($task === null || !$access->allows($task)) {
                // The Workspace redirects to the list when the actor transfers the task away.
                $receipt['taskUid'] = null;
            }
            if (isset($receipt['nextTaskUid'])) {
                $next = $repository->find($receipt['nextTaskUid']);
                if ($next === null || !hash_equals($organizationUid, $next->organizationId) || !$access->allows($next)) {
                    $receipt['nextTaskUid'] = null;
                }
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $receipt;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }

            return $this->safe(static fn () => throw $error);
        }
    }
}
