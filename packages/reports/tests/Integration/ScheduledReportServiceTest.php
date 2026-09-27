<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Integration;

use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Reports\Application\ReportBuilderService;
use Kontor\Reports\Application\ReportExportService;
use Kontor\Reports\Application\ScheduledReportService;
use Kontor\Reports\Infrastructure\Persistence\ScheduledReportRepository;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;

final class ScheduledReportServiceTest extends DatabaseTestCase
{
    private ScheduledReportService $service;
    private ScheduledReportRepository $schedules;
    private string $exportDir = '';

    private function fakeProvider(): ReportProviderInterface
    {
        return new class implements ReportProviderInterface {
            public function key(): string
            {
                return 'fake';
            }

            public function title(): string
            {
                return 'Fake report';
            }

            public function schema(): ReportSchema
            {
                return new ReportSchema(fields: ['status' => 'string', 'count' => 'int'], filterableFields: ['status'], groupableFields: ['status']);
            }

            public function execute(ReportQuery $query): ReportResult
            {
                return new ReportResult(rows: [['status' => 'open', 'count' => 3]]);
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->schedules = new ScheduledReportRepository($this->pdo, $organizations);

        $registry = new ReportProviderRegistry();
        $registry->register($this->fakeProvider());

        $this->service = new ScheduledReportService(
            $this->schedules,
            $registry,
            new ReportBuilderService($registry),
            new ReportExportService(new FormatResolver(), new PdfRenderer()),
        );

        $this->exportDir = sys_get_temp_dir() . '/kontor-reports-integration-' . bin2hex(random_bytes(8));
        mkdir($this->exportDir);
    }

    protected function tearDown(): void
    {
        if ($this->exportDir !== '') {
            array_map('unlink', glob($this->exportDir . '/*') ?: []);
            @rmdir($this->exportDir);
        }

        parent::tearDown();
    }

    public function test_schedule_rejects_a_provider_key_that_is_not_registered(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->schedule($this->organizationUid, 'missing', 'My report', 'daily');
    }

    public function test_schedule_rejects_an_invalid_filter_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->schedule($this->organizationUid, 'fake', 'My report', 'daily', filters: ['not_a_field' => 'x']);
    }

    public function test_due_schedules_only_returns_schedules_whose_next_run_has_arrived(): void
    {
        $this->service->schedule($this->organizationUid, 'fake', 'Due now', 'daily', firstRunAt: new \DateTimeImmutable('-1 hour'));
        $this->service->schedule($this->organizationUid, 'fake', 'Not yet', 'daily', firstRunAt: new \DateTimeImmutable('+1 hour'));

        $due = $this->service->dueSchedules($this->organizationUid);

        $this->assertCount(1, $due);
        $this->assertSame('Due now', $due[0]->name);
    }

    public function test_run_exports_the_report_and_advances_next_run_at(): void
    {
        $schedule = $this->service->schedule(
            $this->organizationUid, 'fake', 'Daily fake report', 'daily',
            format: 'csv', firstRunAt: new \DateTimeImmutable('2026-01-01 09:00:00'),
        );

        $path = $this->service->run($schedule->uid->toString(), $this->exportDir);

        $this->assertFileExists($path);
        $this->assertStringContainsString('status,count', file_get_contents($path));

        $reloaded = $this->schedules->require($schedule->uid->toString());
        $this->assertNotNull($reloaded->lastRunAt);
        $this->assertEquals(new \DateTimeImmutable('2026-01-02 09:00:00'), $reloaded->nextRunAt);
    }
}
