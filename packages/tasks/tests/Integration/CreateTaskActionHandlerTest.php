<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\QueueInterface;
use Kontor\SDK\DTO\QueueOptions;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Application\TaskReminderDispatcher;
use Kontor\Tasks\Application\TaskReminderService;
use Kontor\Tasks\Infrastructure\Automation\CreateTaskActionHandler;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class CreateTaskActionHandlerTest extends DatabaseTestCase
{
    public function test_automation_creates_and_links_a_task_and_queues_its_reminder(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $tasks = new TaskRepository($this->pdo, $organizations);
        $reminders = new TaskReminderService(new TaskReminderRepository($this->pdo, $organizations));
        $queue = new class implements QueueInterface {
            public ?\DateTimeImmutable $when = null;
            public ?JobInterface $job = null;
            public ?QueueOptions $options = null;

            public function dispatch(JobInterface $job, ?QueueOptions $options = null): string
            {
                $this->job = $job;
                $this->options = $options;

                return '01KYFT00000000000000000001';
            }

            public function later(
                \DateTimeImmutable $when,
                JobInterface $job,
                ?QueueOptions $options = null,
            ): string {
                $this->when = $when;
                $this->job = $job;
                $this->options = $options;

                return '01KYFT00000000000000000001';
            }

            public function cancel(string $jobId): bool
            {
                return false;
            }
        };
        $handler = new CreateTaskActionHandler(
            $tasks,
            new TaskRelationService(new RelationRepository($this->pdo, $organizations)),
            new TaskReminderDispatcher($reminders, $queue),
        );
        $triggerUid = '01KYFT00000000000000000002';

        $result = $handler->execute([
            'customer' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test'],
            '_event' => [
                'organizationId' => $this->organizationUid,
                'entityType' => 'contact',
                'entityId' => $triggerUid,
                'actorId' => '42',
            ],
        ], [
            'title' => 'Follow up with {{ customer.name }}',
            'description' => 'Triggered for {{ customer.email }}',
            'priority' => 'high',
            'assignedTo' => 42,
            'dueInMinutes' => 60,
            'linkToTrigger' => true,
            'reminder' => [
                'beforeMinutes' => 15,
                'recipientUserId' => 42,
                'recipientEmail' => '{{ customer.email }}',
                'fromAddress' => 'tasks@example.test',
            ],
        ]);

        $task = $tasks->require($result['taskUid']);
        $this->assertSame('Follow up with Ada Lovelace', $task->title);
        $this->assertSame('Triggered for ada@example.test', $task->description);
        $this->assertSame('high', $task->priority);
        $this->assertSame(42, $task->assignedTo);
        $this->assertSame(
            [$triggerUid],
            array_column(
                (new TaskRelationService(new RelationRepository($this->pdo, $organizations)))
                    ->relatedEntities($this->organizationUid, $task->uid->toString()),
                'targetUid',
            ),
        );
        $this->assertSame('tasks.reminder', $queue->job?->jobType());
        $this->assertSame('notifications', $queue->options?->queue);
        $this->assertSame('01KYFT00000000000000000001', $result['jobUid']);
        $this->assertNotNull($reminders->reminder($result['reminderUid']));
        $this->assertEquals($task->dueAt?->modify('-15 minutes'), $queue->when);
    }
}
