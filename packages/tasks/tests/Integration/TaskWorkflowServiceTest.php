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

    public function test_recurrence_inherits_task_visibility_and_department(): void
    {
        $task = Task::create(
            $this->organizationUid,
            'Department review',
            assignedTo: 10,
            dueAt: new \DateTimeImmutable('2026-10-01'),
            recurrenceRule: 'weekly',
            visibility: Task::VISIBILITY_DEPARTMENT,
            departmentRoleId: 20,
        );
        $this->tasks->save($task);

        $next = $this->workflow->complete($task->uid->toString())['next'];

        self::assertNotNull($next);
        $stored = $this->tasks->require($next->uid->toString());
        self::assertSame(Task::VISIBILITY_DEPARTMENT, $stored->visibility);
        self::assertSame(20, $stored->departmentRoleId);
        self::assertSame(10, $stored->assignedTo);
    }

    public function test_denied_state_transitions_do_not_modify_the_locked_task(): void
    {
        $task = Task::create($this->organizationUid, 'Private task', assignedTo: 10, visibility: Task::VISIBILITY_ASSIGNEE);
        $this->tasks->save($task);
        $uid = $task->uid->toString();
        $deny = static function (Task $current): void {
            self::assertSame(10, $current->assignedTo);
            throw new \DomainException('Denied');
        };

        foreach ([$this->workflow->complete(...), $this->tasks->archive(...)] as $change) {
            try {
                $change($uid, authorize: $deny);
                self::fail('Expected the authorization callback to deny the transition');
            } catch (\DomainException $exception) {
                self::assertSame('Denied', $exception->getMessage());
            }
        }
        $current = $this->tasks->require($uid);
        self::assertSame('open', $current->status);
        self::assertFalse($current->archived);

        $this->tasks->archive($uid);
        try {
            $this->tasks->restore($uid, authorize: $deny);
            self::fail('Expected the authorization callback to deny restoration');
        } catch (\DomainException $exception) {
            self::assertSame('Denied', $exception->getMessage());
        }
        self::assertTrue($this->tasks->require($uid)->archived);
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

    public function test_closed_tasks_can_be_found_and_reopened(): void
    {
        $completed = Task::create($this->organizationUid, 'Completed task');
        $cancelled = Task::create($this->organizationUid, 'Cancelled task');
        $open = Task::create($this->organizationUid, 'Open task');
        foreach ([$completed, $cancelled, $open] as $task) {
            $this->tasks->save($task);
        }
        $this->workflow->complete($completed->uid->toString());
        $this->workflow->cancel($cancelled->uid->toString());

        $closed = $this->tasks->findMatching($this->organizationUid, closedOnly: true);
        self::assertEqualsCanonicalizing(
            [$completed->uid->toString(), $cancelled->uid->toString()],
            array_map(static fn (Task $task): string => $task->uid->toString(), $closed),
        );

        foreach ($closed as $task) {
            $uid = $task->uid->toString();
            $this->workflow->reopen($uid);
            $reloaded = $this->tasks->require($uid);
            self::assertSame('open', $reloaded->status);
            self::assertNull($reloaded->completedAt);
        }
        self::assertSame([], $this->tasks->findMatching($this->organizationUid, closedOnly: true));
    }

    public function test_recompleting_a_reopened_recurring_task_keeps_one_successor(): void
    {
        $task = Task::create(
            $this->organizationUid, 'Weekly report',
            dueAt: new \DateTimeImmutable('2026-01-01 10:00:00'), recurrenceRule: 'weekly',
        );
        $this->tasks->save($task);
        $uid = $task->uid->toString();

        $firstCompletion = $this->workflow->complete($uid);
        $this->workflow->reopen($uid);
        $secondCompletion = $this->workflow->complete($uid);

        self::assertNotNull($firstCompletion['next']);
        self::assertNull($secondCompletion['next']);
        $openTasks = $this->tasks->findMatching($this->organizationUid, status: 'open');
        self::assertCount(1, $openTasks);
        self::assertSame($firstCompletion['next']->uid->toString(), $openTasks[0]->uid->toString());
    }

    public function test_existing_completed_recurring_task_does_not_advance_again(): void
    {
        $task = Task::create(
            $this->organizationUid, 'Existing weekly report',
            dueAt: new \DateTimeImmutable('2026-01-01 10:00:00'), recurrenceRule: 'weekly',
        );
        $task->status = 'done';
        $task->completedAt = new \DateTimeImmutable('2026-01-02 10:00:00');
        $this->tasks->save($task);
        $successor = Task::create(
            $this->organizationUid, 'Existing weekly report',
            dueAt: new \DateTimeImmutable('2026-01-08 10:00:00'), recurrenceRule: 'weekly',
        );
        $this->tasks->save($successor);

        $uid = $task->uid->toString();
        $this->workflow->reopen($uid);

        self::assertNull($this->workflow->complete($uid)['next']);
        $openTasks = $this->tasks->findMatching($this->organizationUid, status: 'open');
        self::assertCount(1, $openTasks);
        self::assertSame($successor->uid->toString(), $openTasks[0]->uid->toString());
    }

    public function test_archived_task_cannot_be_reopened_before_restoration(): void
    {
        $task = Task::create($this->organizationUid, 'Archived task');
        $this->tasks->save($task);
        $uid = $task->uid->toString();
        $this->workflow->cancel($uid);
        $this->tasks->archive($uid);

        $this->expectException(\RuntimeException::class);
        $this->workflow->reopen($uid);
    }
}
