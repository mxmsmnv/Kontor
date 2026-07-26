<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Dashboard\MyOpenTasksWidgetProvider;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class MyOpenTasksWidgetProviderTest extends DatabaseTestCase
{
    public function test_it_renders_only_open_tasks_assigned_to_the_current_user(): void
    {
        $repository = new TaskRepository($this->pdo, new OrganizationRepository($this->pdo));

        $overdue = Task::create(
            $this->organizationUid,
            'Overdue assignment',
            priority: 'high',
            assignedTo: 42,
            dueAt: new \DateTimeImmutable('-1 day'),
        );
        $repository->save($overdue);

        $unassigned = Task::create($this->organizationUid, 'Someone else', assignedTo: 99);
        $repository->save($unassigned);

        $completed = Task::create($this->organizationUid, 'Already done', assignedTo: 42);
        $repository->save($completed);
        $completed->status = 'completed';
        $repository->save($completed);

        $data = (new MyOpenTasksWidgetProvider($repository))->render($this->organizationUid, 42);

        $this->assertSame(1, $data['count']);
        $this->assertSame(1, $data['overdueCount']);
        $this->assertSame('Overdue assignment', $data['tasks'][0]['title']);
        $this->assertTrue($data['tasks'][0]['overdue']);
    }

    public function test_it_returns_an_empty_payload_without_a_user(): void
    {
        $repository = new TaskRepository($this->pdo, new OrganizationRepository($this->pdo));

        $data = (new MyOpenTasksWidgetProvider($repository))->render($this->organizationUid, null);

        $this->assertSame(['count' => 0, 'overdueCount' => 0, 'tasks' => []], $data);
    }
}
