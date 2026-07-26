<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Tasks\Application\TaskReminderService;
use Kontor\Tasks\Application\TaskReminderDispatcher;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Application\TaskWorkflowService;
use Kontor\Tasks\Health\TasksHealthCheck;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use Kontor\Tasks\Infrastructure\Automation\CreateTaskActionHandler;
use Kontor\Tasks\Infrastructure\Queue\TaskReminderDeliveryJob;
use Kontor\Tasks\Migrations\Migration0001CreateTasksTable;
use Kontor\Tasks\Migrations\Migration0002CreateTaskRemindersTable;

/**
 * KontorTasks bootstrap module (kontor.md Substage 5.1). First business
 * component of Stage 5 (Collaboration/productivity), and the first real
 * consumer of Kontor\Core\Infrastructure\Persistence\RelationRepository
 * (kontor_relations, existed since Substage 1.2 with no service).
 */
class KontorTasks extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Tasks',
            'summary' => 'Tasks, reminders, recurrence, calendar, entity relations.',
            'version' => '004',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorTasks',
            'icon' => 'check-square-o',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorQueue', 'KontorMail', 'KontorAutomation'],
            'permissions' => [
                'kontor-tasks-task-view' => 'View tasks',
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
            ),
        );

        /** @var KontorAutomation $automation */
        $automation = $this->wire()->modules->get('KontorAutomation');
        $automation->actionHandlerRegistry()->register(new CreateTaskActionHandler(
            $this->taskRepository(),
            $this->relations(),
            $this->reminderDispatcher(),
        ));
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
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateTasksTable(),
            new Migration0002CreateTaskRemindersTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('tasks', self::getModuleInfo()['version'], 'tasks');
        $components->enable('tasks');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
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
