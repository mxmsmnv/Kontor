<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Integration;

use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Files\Application\FileManager;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Queue\Application\QueueWorker;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Queue\Infrastructure\Queue;
use Kontor\Queue\JobRegistry;
use Kontor\Reports\Application\ReportBuilderService;
use Kontor\Reports\Application\ReportExportService;
use Kontor\Reports\Application\ScheduledReportDispatcher;
use Kontor\Reports\Application\ScheduledReportService;
use Kontor\Reports\Infrastructure\Persistence\ScheduledReportRepository;
use Kontor\Reports\Infrastructure\Queue\ScheduledReportJob;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;
use Kontor\SDK\DTO\StoredFile;

final class ScheduledReportQueueVerticalTest extends DatabaseTestCase
{
    private string $storageDir = '';

    protected function tearDown(): void
    {
        if ($this->storageDir !== '') {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->storageDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            @rmdir($this->storageDir);
        }

        parent::tearDown();
    }

    public function test_due_schedule_runs_through_queue_and_is_delivered_to_files(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $providers = new ReportProviderRegistry();
        $providers->register($this->provider());
        $reports = new ScheduledReportService(
            new ScheduledReportRepository($this->pdo, $organizations),
            $providers,
            new ReportBuilderService($providers),
            new ReportExportService(new FormatResolver(), new PdfRenderer()),
        );
        $schedule = $reports->schedule(
            $this->organizationUid,
            'queue_vertical',
            'Daily pipeline',
            'daily',
            format: 'csv',
            firstRunAt: new \DateTimeImmutable('-1 minute'),
        );

        $jobs = new JobRepository($this->pdo);
        $queue = new Queue($jobs);
        $dispatcher = new ScheduledReportDispatcher($reports, $queue);
        $firstDispatch = $dispatcher->dispatchDue($this->organizationUid);
        $secondDispatch = $dispatcher->dispatchDue($this->organizationUid);
        $this->assertSame($firstDispatch, $secondDispatch);
        $this->assertCount(1, $firstDispatch);

        $this->storageDir = sys_get_temp_dir().'/kontor-report-files-'.bin2hex(random_bytes(8));
        $files = new FileManager(
            new LocalPrivateStorage(
                $this->storageDir,
                new SignedUrlSigner('test-secret', '/download'),
            ),
            new FileRepository($this->pdo),
            $organizations,
        );
        $registry = new JobRegistry();
        $registry->register(
            'reports.scheduled',
            static fn (array $payload): ScheduledReportJob => new ScheduledReportJob($payload, $reports, $files),
        );

        $this->assertTrue((new QueueWorker($jobs, $registry))->processNext('reports'));
        $job = $jobs->find((string) reset($firstDispatch));
        $this->assertSame('completed', $job['status']);
        $this->assertSame(100, (int) $job['progress']);

        $stored = (new FileRepository($this->pdo))->forEntity(
            'scheduled_report',
            $schedule->uid->toString(),
            organizationId: $organizations->internalIdOf($this->organizationUid),
        );
        $this->assertCount(1, $stored);
        $this->assertSame('Daily-pipeline.csv', $stored[0]['original_name']);
        $this->assertSame('confidential', $stored[0]['classification']);
        $stream = $files->read($stored[0]['uid'], $this->organizationUid);
        $this->assertStringContainsString('status,count', stream_get_contents($stream));
        fclose($stream);

        $advanced = (new ScheduledReportRepository($this->pdo, $organizations))
            ->require($schedule->uid->toString());
        $this->assertNotNull($advanced->lastRunAt);
        $this->assertGreaterThan($schedule->nextRunAt, $advanced->nextRunAt);
    }

    public function test_failed_file_delivery_does_not_advance_the_schedule(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $providers = new ReportProviderRegistry();
        $providers->register($this->provider());
        $repository = new ScheduledReportRepository($this->pdo, $organizations);
        $reports = new ScheduledReportService(
            $repository,
            $providers,
            new ReportBuilderService($providers),
            new ReportExportService(new FormatResolver(), new PdfRenderer()),
        );
        $schedule = $reports->schedule(
            $this->organizationUid,
            'queue_vertical',
            'Retry-safe report',
            'daily',
            firstRunAt: new \DateTimeImmutable('2026-07-26 09:00:00'),
        );
        $failingStorage = new class implements StorageInterface {
            public function put(string $path, mixed $contents, array $options = []): StoredFile
            {
                throw new \RuntimeException('Storage unavailable.');
            }

            public function read(string $path)
            {
                throw new \RuntimeException('Not implemented.');
            }

            public function delete(string $path): void
            {
            }

            public function exists(string $path): bool
            {
                return false;
            }

            public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string
            {
                return '';
            }
        };
        $files = new FileManager(
            $failingStorage,
            new FileRepository($this->pdo),
            $organizations,
        );
        $payload = ScheduledReportJob::forSchedule($schedule)->payload();
        $job = new ScheduledReportJob($payload, $reports, $files);
        $progress = new class implements JobProgressReporterInterface {
            public function report(int $percent): void
            {
            }
        };

        try {
            $job->handle($payload, $progress);
            $this->fail('Expected the failing Files delivery to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('File storage write failed.', $exception->getMessage());
            $this->assertStringNotContainsString('Storage unavailable.', $exception->getMessage());
        }

        $unchanged = $repository->require($schedule->uid->toString());
        $this->assertNull($unchanged->lastRunAt);
        $this->assertEquals(new \DateTimeImmutable('2026-07-26 09:00:00'), $unchanged->nextRunAt);
    }

    private function provider(): ReportProviderInterface
    {
        return new class implements ReportProviderInterface {
            public function key(): string
            {
                return 'queue_vertical';
            }

            public function title(): string
            {
                return 'Queue vertical';
            }

            public function schema(): ReportSchema
            {
                return new ReportSchema(fields: ['status' => 'string', 'count' => 'int']);
            }

            public function execute(ReportQuery $query): ReportResult
            {
                return new ReportResult(rows: [['status' => 'open', 'count' => 3]]);
            }
        };
    }
}
