<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Application\TimeTrackingService;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Health\ProjectsHealthCheck;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;

final class ProjectsHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_active_projects_and_running_timers(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $projects = new ProjectRepository($this->pdo, $organizations);
        $entries = new TimeEntryRepository($this->pdo, $organizations);
        $timeTracking = new TimeTrackingService($entries);

        $project = Project::create($this->organizationUid, 'PRJ-1', 'Website Redesign', currencyCode: 'EUR');
        $projects->save($project);
        $timeTracking->start($this->organizationUid, $project->uid->toString(), 5);

        $result = (new ProjectsHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeProjects']);
        $this->assertSame(1, $result->details['runningTimers']);
        $this->assertSame(0, $result->details['inconsistentEntries']);
    }
}
