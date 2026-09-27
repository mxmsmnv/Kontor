<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Application\TaskWorkflowService;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class TaskWorkflowServiceTest extends DatabaseTestCase
{
    private TaskRepository $tasks;
    private TaskWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tasks = new TaskRepository($this->pdo, new OrganizationRepository($this->pdo));
        $this->workflow = new TaskWorkflowService($this->tasks);
    }

    public function test_start_moves_an_open_task_to_in_progress(): void
    {
        $task = Task::create($this->organizationUid, 'Draft proposal');
        $this->tasks->save($task);

        $started = $this->workflow->start($task->uid->toString());

        $this->assertSame('in_progress', $started->status);
    }

    public function test_completing_a_non_recurring_task_creates_no_next_occurrence(): void
    {
        $task = Task::create($this->organizationUid, 'One-off task');
        $this->tasks->save($task);

        $result = $this->workflow->complete($task->uid->toString());

        $this->assertSame('done', $result['completed']->status);
        $this->assertNotNull($result['completed']->completedAt);
        $this->assertNull($result['next']);
    }

    public function test_completing_a_recurring_task_creates_the_next_occurrence(): void
    {
        $task = Task::create(
            $this->organizationUid, 'Weekly report', priority: 'high',
            dueAt: new \DateTimeImmutable('2026-01-01 10:00:00'), recurrenceRule: 'weekly',
        );
        $this->tasks->save($task);

        $result = $this->workflow->complete($task->uid->toString());

        $this->assertNotNull($result['next']);
        $this->assertSame('Weekly report', $result['next']->title);
        $this->assertSame('high', $result['next']->priority);
        $this->assertSame('open', $result['next']->status);
        $this->assertEquals(new \DateTimeImmutable('2026-01-08 10:00:00'), $result['next']->dueAt);

        $reloaded = $this->tasks->require($result['next']->uid->toString());
        $this->assertSame('weekly', $reloaded->recurrenceRule);
    }

    public function test_cannot_complete_an_already_completed_task(): void
    {
        $task = Task::create($this->organizationUid, 'Task');
        $this->tasks->save($task);
        $this->workflow->complete($task->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->workflow->complete($task->uid->toString());
    }

    public function test_cancel_only_works_on_open_tasks(): void
    {
        $task = Task::create($this->organizationUid, 'Task');
        $this->tasks->save($task);

        $cancelled = $this->workflow->cancel($task->uid->toString());
        $this->assertSame('cancelled', $cancelled->status);

        $this->expectException(\RuntimeException::class);
        $this->workflow->cancel($task->uid->toString());
    }
}
