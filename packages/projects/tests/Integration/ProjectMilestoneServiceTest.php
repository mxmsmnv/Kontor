<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Application\ProjectMilestoneService;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Projects\Infrastructure\Persistence\MilestoneRepository;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;

final class ProjectMilestoneServiceTest extends DatabaseTestCase
{
    private ProjectMilestoneService $service;
    private MilestoneRepository $milestones;
    private string $projectUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $projects = new ProjectRepository($this->pdo, $organizations);
        $this->milestones = new MilestoneRepository($this->pdo, $organizations);
        $this->service = new ProjectMilestoneService($this->milestones);

        $project = Project::create($this->organizationUid, 'PRJ-1', 'Website Redesign', currencyCode: 'EUR');
        $projects->save($project);
        $this->projectUid = $project->uid->toString();
    }

    public function test_complete_then_reopen(): void
    {
        $milestone = ProjectMilestone::create($this->organizationUid, $this->projectUid, 'Design sign-off');
        $this->milestones->save($milestone);

        $completed = $this->service->complete($milestone->uid->toString());
        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->completedAt);

        $reopened = $this->service->reopen($milestone->uid->toString());
        $this->assertFalse($reopened->isCompleted());
        $this->assertNull($reopened->completedAt);
    }

    public function test_cannot_complete_twice(): void
    {
        $milestone = ProjectMilestone::create($this->organizationUid, $this->projectUid, 'Design sign-off');
        $this->milestones->save($milestone);
        $this->service->complete($milestone->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->service->complete($milestone->uid->toString());
    }

    public function test_for_project_orders_by_sort_order(): void
    {
        $this->milestones->save(ProjectMilestone::create($this->organizationUid, $this->projectUid, 'Second', sortOrder: 2));
        $this->milestones->save(ProjectMilestone::create($this->organizationUid, $this->projectUid, 'First', sortOrder: 1));

        $names = array_map(fn ($m) => $m->name, $this->milestones->forProject($this->projectUid));

        $this->assertSame(['First', 'Second'], $names);
    }
}
