<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Application\TaskReminderService;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class TaskReminderServiceTest extends DatabaseTestCase
{
    private TaskReminderService $reminders;
    private string $taskUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $tasks = new TaskRepository($this->pdo, $organizations);
        $this->reminders = new TaskReminderService(new TaskReminderRepository($this->pdo, $organizations));

        $task = Task::create($this->organizationUid, 'Call client');
        $tasks->save($task);
        $this->taskUid = $task->uid->toString();
    }

    public function test_due_reminders_only_returns_unsent_reminders_at_or_before_now(): void
    {
        $this->reminders->schedule($this->organizationUid, $this->taskUid, new \DateTimeImmutable('-1 hour'));
        $this->reminders->schedule($this->organizationUid, $this->taskUid, new \DateTimeImmutable('+1 hour'));

        $due = $this->reminders->dueReminders($this->organizationUid);

        $this->assertCount(1, $due);
    }

    public function test_mark_sent_excludes_it_from_due_reminders(): void
    {
        $reminder = $this->reminders->schedule($this->organizationUid, $this->taskUid, new \DateTimeImmutable('-1 hour'));

        $this->reminders->markSent($reminder->uid->toString());

        $this->assertCount(0, $this->reminders->dueReminders($this->organizationUid));
    }
}
