<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Health\TasksHealthCheck;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class TasksHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        $tasks = new TaskRepository($this->pdo, new OrganizationRepository($this->pdo));
        $tasks->save(Task::create($this->organizationUid, 'Open task'));

        $result = (new TasksHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['openTasks']);
        $this->assertSame(0, $result->details['dueReminders']);
    }
}
