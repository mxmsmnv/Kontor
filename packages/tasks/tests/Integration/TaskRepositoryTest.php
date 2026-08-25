<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class TaskRepositoryTest extends DatabaseTestCase
{
    private function repository(): TaskRepository
    {
        return new TaskRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $task = Task::create($this->organizationUid, 'Prepare quarterly review', priority: 'high');

        $repository->save($task);
        $found = $repository->find($task->uid->toString());

        $this->assertNotNull($found);
        $this->assertSame('Prepare quarterly review', $found->title);
        $this->assertSame('high', $found->priority);
        $this->assertSame('open', $found->status);
    }

    public function test_due_between_is_the_calendar_milestones_query(): void
    {
        $repository = $this->repository();

        $inRange = Task::create($this->organizationUid, 'In range', dueAt: new \DateTimeImmutable('2026-03-15'));
        $repository->save($inRange);

        $outOfRange = Task::create($this->organizationUid, 'Out of range', dueAt: new \DateTimeImmutable('2026-05-01'));
        $repository->save($outOfRange);

        $noDueDate = Task::create($this->organizationUid, 'No due date');
        $repository->save($noDueDate);

        $results = $repository->dueBetween($this->organizationUid, new \DateTimeImmutable('2026-03-01'), new \DateTimeImmutable('2026-03-31'));

        $this->assertCount(1, $results);
        $this->assertSame('In range', $results[0]->title);
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $task = Task::create($this->organizationUid, 'Task');
        $repository->save($task);

        $repository->archive($task->uid->toString());
        $repository->restore($task->uid->toString());

        $this->assertNotNull($repository->find($task->uid->toString()));
    }

    public function test_find_active_hides_archived_tasks(): void
    {
        $repository = $this->repository();
        $task = Task::create($this->organizationUid, 'Connected task');
        $repository->save($task);

        $this->assertNotNull($repository->findActive($this->organizationUid, $task->uid->toString()));

        $repository->archive($task->uid->toString());

        $this->assertNull($repository->findActive($this->organizationUid, $task->uid->toString()));
    }
}
