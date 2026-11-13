<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Tasks\Application\TaskAccessScope;
use Kontor\Tasks\Application\TaskReminderService;
use Kontor\Tasks\Application\TaskReminderDispatcher;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Application\TaskWorkflowService;
use Kontor\Tasks\Health\TasksHealthCheck;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use Kontor\Tasks\Infrastructure\Automation\CreateTaskActionHandler;
use Kontor\Tasks\Infrastructure\Dashboard\MyOpenTasksWidgetProvider;
use Kontor\Tasks\Infrastructure\Queue\TaskReminderDeliveryJob;
use Kontor\Tasks\Migrations\Migration0001CreateTasksTable;
use Kontor\Tasks\Migrations\Migration0002CreateTaskRemindersTable;
use Kontor\Tasks\Migrations\Migration0003TrackTaskRecurrence;
use Kontor\Tasks\Migrations\Migration0004AddTaskVisibility;

/**
 * KontorTasks bootstrap module (kontor.md Substage 5.1). First business
 * component of Stage 5 (Collaboration/productivity), and the first real
 * consumer of Kontor\Core\Infrastructure\Persistence\RelationRepository
 * (kontor_relations, existed since Substage 1.2 with no service).
 */
class KontorTasks extends WireData implements Module
{
    private const VIEW_ALL = 'kontor-tasks-task-view-all';
    private const DEPARTMENT = 'kontor-tasks-department';

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Tasks',
            'summary' => 'Tasks, reminders, recurrence, calendar, entity relations.',
            'version' => '102',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorTasks',
            'icon' => 'check-square-o',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorQueue', 'KontorMail', 'KontorAutomation'],
            'permissions' => [
                'kontor-tasks-task-view' => 'View tasks within your access scope',
                self::VIEW_ALL => 'View all tasks in the organization',
                self::DEPARTMENT => 'Use this role as a task department',
                'kontor-tasks-task-create' => 'Create tasks',
                'kontor-tasks-task-edit' => 'Edit tasks',
                'kontor-tasks-task-complete' => 'Complete tasks',
                'kontor-tasks-task-cancel' => 'Cancel tasks',
                'kontor-tasks-task-assign' => 'Assign tasks to other users',
                'kontor-tasks-reminder-manage' => 'Manage task reminders',
                'kontor-tasks-relation-manage' => 'Link tasks to other records',
            ],
        ];
    }

    private ?TaskRepository $taskRepository = null;
    private ?TaskReminderRepository $reminderRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        /** @var KontorQueue $queue */
        $queue = $this->wire()->modules->get('KontorQueue');
        /** @var KontorMail $mail */
        $mail = $this->wire()->modules->get('KontorMail');
        $queue->jobRegistry()->register(
            'tasks.reminder',
            fn (array $payload): TaskReminderDeliveryJob => new TaskReminderDeliveryJob(
                $payload,
                $this->reminders(),
                $mail->outbound(),
                $mail->entityLinking(),
                $this->reminderMaySend(...),
            ),
        );

        /** @var KontorAutomation $automation */
        $automation = $this->wire()->modules->get('KontorAutomation');
        $automation->actionHandlerRegistry()->register(new CreateTaskActionHandler(
            $this->taskRepository(),
            $this->relations(),
            $this->reminderDispatcher(),
        ));

        if ($this->wire()->modules->isInstalled('KontorDashboard')) {
            /** @var KontorDashboard $dashboard */
            $dashboard = $this->wire()->modules->get('KontorDashboard');
            $dashboard->widgetRegistry()->register(
                new MyOpenTasksWidgetProvider(
                    $this->taskRepository(),
                    $this->accessForUserId(...),
                )
            );
        }
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorTasks', $language, $strings);
        }
    }

    /** @return list<Role> */
    public function departmentRoles(): array
    {
        $departments = [];
        foreach ($this->wire()->roles as $role) {
            if ($this->isDepartmentRole($role)) {
                $departments[] = $role;
            }
        }

        return $departments;
    }

    public function accessFor(User $user): TaskAccessScope
    {
        if (!$user->id || $user->isGuest() || $user->isUnpublished() || $user->isTrash()) {
            return new TaskAccessScope((int) $user->id, false);
        }

        $departments = [];
        foreach ($user->roles as $role) {
            if ($this->isDepartmentRole($role)) {
                $departments[] = (int) $role->id;
            }
        }

        $superuser = $user->isSuperuser();

        return new TaskAccessScope(
            (int) $user->id,
            $superuser || $user->hasPermission('kontor-tasks-task-view'),
            $superuser || $user->hasPermission(self::VIEW_ALL),
            $departments,
        );
    }

    private function accessForUserId(int $userId): TaskAccessScope
    {
        $user = $this->wire()->users->get($userId);

        return $user instanceof User ? $this->accessFor($user) : new TaskAccessScope($userId, false);
    }

    private function isDepartmentRole(Role $role): bool
    {
        $config = $this->wire()->config;

        return !in_array((int) $role->id, [(int) $config->guestUserRolePageID, (int) $config->superUserRolePageID], true)
            && $role->hasPermission(self::DEPARTMENT);
    }

    private function reminderMaySend(array $payload): bool
    {
        $task = $this->taskRepository()->findActive(
            (string) $payload['organizationUid'],
            (string) $payload['taskUid'],
        );
        $recipientId = (int) $payload['recipientUserId'];
        if ($task === null || $task->assignedTo !== $recipientId) {
            return false;
        }

        $recipient = $this->wire()->users->get($recipientId);

        return $recipient instanceof User && $this->accessFor($recipient)->allows($task)
            && strtolower(trim((string) $recipient->email)) === strtolower((string) $payload['recipientEmail']);
    }

    public function taskRepository(): TaskRepository
    {
        return $this->taskRepository ??= new TaskRepository($this->pdo(), $this->organizations());
    }

    public function reminderRepository(): TaskReminderRepository
    {
        return $this->reminderRepository ??= new TaskReminderRepository($this->pdo(), $this->organizations());
    }

    public function workflow(): TaskWorkflowService
    {
        return new TaskWorkflowService($this->taskRepository());
    }

    public function reminders(): TaskReminderService
    {
        return new TaskReminderService($this->reminderRepository());
    }

    public function reminderDispatcher(): TaskReminderDispatcher
    {
        /** @var KontorQueue $queue */
        $queue = $this->wire()->modules->get('KontorQueue');

        return new TaskReminderDispatcher($this->reminders(), $queue->queue());
    }

    public function relations(): TaskRelationService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return new TaskRelationService($kontor->container()->get(RelationRepository::class));
    }

    public function healthCheck(): TasksHealthCheck
    {
        return new TasksHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateTasksTable(),
            new Migration0002CreateTaskRemindersTable(),
            new Migration0003TrackTaskRecurrence(),
            new Migration0004AddTaskVisibility(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('tasks', self::getModuleInfo()['version'], 'tasks');
        $components->enable('tasks');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $pdo = $this->pdo();
        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([new Migration0003TrackTaskRecurrence(), new Migration0004AddTaskVisibility()]);

        $permissions = $this->wire()->permissions;
        $definitions = self::getModuleInfo()['permissions'];
        foreach ([self::VIEW_ALL, self::DEPARTMENT] as $name) {
            if (!$permissions->get($name)->id) {
                $permission = $permissions->add($name);
                $permission->title = $definitions[$name];
                if (!$permissions->save($permission)) {
                    throw new WireException('Unable to register task permission: ' . $name);
                }
            }
        }

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('tasks', self::getModuleInfo()['version'], 'tasks');
        $components->enable('tasks');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Tasks module removed. Task data was kept intact.'));
    }
}
