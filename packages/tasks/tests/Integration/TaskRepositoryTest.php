<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Application\TaskAccessScope;
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

    public function test_access_scope_filters_lists_before_the_limit_and_matches_record_access(): void
    {
        $repository = $this->repository();
        $tasks = [
            Task::create($this->organizationUid, 'Shared'),
            Task::create($this->organizationUid, 'My private', assignedTo: 10, visibility: Task::VISIBILITY_ASSIGNEE),
            Task::create($this->organizationUid, 'Other private', assignedTo: 11, visibility: Task::VISIBILITY_ASSIGNEE),
            Task::create($this->organizationUid, 'Media', visibility: Task::VISIBILITY_DEPARTMENT, departmentRoleId: 20),
            Task::create(
                $this->organizationUid,
                'Production',
                assignedTo: 10,
                dueAt: new \DateTimeImmutable('2020-01-01'),
                visibility: Task::VISIBILITY_DEPARTMENT,
                departmentRoleId: 21,
            ),
        ];
        foreach ($tasks as $task) {
            $repository->save($task);
        }

        $scopes = [
            [new TaskAccessScope(10, false, true, [20]), []],
            [new TaskAccessScope(10, true, false, [20]), ['Shared', 'My private', 'Media']],
            [new TaskAccessScope(10, true), ['Shared', 'My private']],
            [new TaskAccessScope(11, true, true), ['Shared', 'My private', 'Other private', 'Media', 'Production']],
        ];
        foreach ($scopes as [$scope, $expected]) {
            self::assertEqualsCanonicalizing(
                $expected,
                array_map(
                    static fn (Task $task): string => $task->title,
                    $repository->findMatching($this->organizationUid, access: $scope),
                ),
            );
            foreach ($tasks as $task) {
                self::assertSame(
                    in_array($task->title, $expected, true),
                    $scope->allows($repository->require($task->uid->toString())),
                );
            }
        }

        $media = $scopes[1][0];
        $limited = $repository->findMatching($this->organizationUid, limit: 1, access: $media);
        self::assertCount(1, $limited);
        self::assertNotSame('Production', $limited[0]->title);
        self::assertSame(
            ['My private'],
            array_map(
                static fn (Task $task): string => $task->title,
                $repository->openForAssignee($this->organizationUid, 10, limit: 1, access: $media),
            ),
        );
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
