<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Application\TimeTrackingService;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;

final class TimeTrackingServiceTest extends DatabaseTestCase
{
    private TimeTrackingService $timeTracking;
    private TimeEntryRepository $entries;
    private string $projectUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $projects = new ProjectRepository($this->pdo, $organizations);
        $this->entries = new TimeEntryRepository($this->pdo, $organizations);
        $this->timeTracking = new TimeTrackingService($this->entries);

        $project = Project::create($this->organizationUid, 'PRJ-1', 'Website Redesign', currencyCode: 'EUR');
        $projects->save($project);
        $this->projectUid = $project->uid->toString();
    }

    public function test_start_then_stop_computes_a_duration(): void
    {
        $entry = $this->timeTracking->start($this->organizationUid, $this->projectUid, 5, 'Design review');
        $this->assertTrue($this->entries->require($entry->uid->toString())->isRunning());

        $stopped = $this->timeTracking->stop($entry->uid->toString());

        $this->assertFalse($stopped->isRunning());
        $this->assertNotNull($stopped->durationMinutes);
        $this->assertGreaterThanOrEqual(0, $stopped->durationMinutes);
    }

    public function test_cannot_stop_a_timer_twice(): void
    {
        $entry = $this->timeTracking->start($this->organizationUid, $this->projectUid, 5);
        $this->timeTracking->stop($entry->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->timeTracking->stop($entry->uid->toString());
    }

    public function test_log_manual_records_a_closed_entry_directly(): void
    {
        $entry = $this->timeTracking->logManual(
            $this->organizationUid, $this->projectUid, 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 10:30:00'),
            description: 'Client call',
        );

        $reloaded = $this->entries->require($entry->uid->toString());
        $this->assertSame(90, $reloaded->durationMinutes);
        $this->assertSame('Client call', $reloaded->description);
    }
}
