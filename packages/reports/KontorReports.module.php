<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Reports\Application\ChartDataMapper;
use Kontor\Reports\Application\ReportBuilderService;
use Kontor\Reports\Application\ReportExportService;
use Kontor\Reports\Application\ScheduledReportDispatcher;
use Kontor\Reports\Application\ScheduledReportService;
use Kontor\Reports\Health\ReportsHealthCheck;
use Kontor\Reports\Infrastructure\Persistence\ScheduledReportRepository;
use Kontor\Reports\Infrastructure\Queue\ScheduledReportJob;
use Kontor\Reports\Migrations\Migration0001CreateScheduledReportsTable;

/**
 * KontorReports bootstrap module (kontor.md Substage 5.4). Depends on
 * kontor/documents for PDF export. The "provider registry" milestone
 * itself already existed (Kontor\Core\Infrastructure\Registry\
 * ReportProviderRegistry, Substage 3.3) — this module is the
 * orchestration layer on top of it, not a second registry.
 */
class KontorReports extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Reports',
            'summary' => 'Report builder, charts, exports, scheduled reports.',
            'version' => '003',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorReports',
            'icon' => 'bar-chart',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorDocuments', 'KontorQueue', 'KontorFiles'],
            'permissions' => [
                'kontor-reports-report-view' => 'View and run reports',
                'kontor-reports-report-export' => 'Export reports',
                'kontor-reports-schedule-manage' => 'Create and manage scheduled reports',
            ],
        ];
    }

    private ?ScheduledReportRepository $scheduledReportRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        /** @var KontorQueue $queue */
        $queue = $this->wire()->modules->get('KontorQueue');
        /** @var KontorFiles $files */
        $files = $this->wire()->modules->get('KontorFiles');
        $queue->jobRegistry()->register(
            'reports.scheduled',
            fn (array $payload): ScheduledReportJob => new ScheduledReportJob(
                $payload,
                $this->scheduledReports(),
                $files->fileManager(),
            ),
        );
        $this->addHook('LazyCron::everyMinute', $this, 'hookDispatchDueReports');
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorReports', $language, $strings);
        }
    }

    public function scheduledReportRepository(): ScheduledReportRepository
    {
        return $this->scheduledReportRepository ??= new ScheduledReportRepository($this->pdo(), $this->organizations());
    }

    public function reportBuilder(): ReportBuilderService
    {
        return new ReportBuilderService($this->providerRegistry());
    }

    public function chartDataMapper(): ChartDataMapper
    {
        return new ChartDataMapper();
    }

    public function reportExporter(): ReportExportService
    {
        return new ReportExportService(new FormatResolver(), new PdfRenderer());
    }

    public function scheduledReports(): ScheduledReportService
    {
        return new ScheduledReportService($this->scheduledReportRepository(), $this->providerRegistry(), $this->reportBuilder(), $this->reportExporter());
    }

    public function scheduledReportDispatcher(): ScheduledReportDispatcher
    {
        /** @var KontorQueue $queue */
        $queue = $this->wire()->modules->get('KontorQueue');

        return new ScheduledReportDispatcher($this->scheduledReports(), $queue->queue());
    }

    public function hookDispatchDueReports(HookEvent $event): void
    {
        $this->scheduledReportDispatcher()->dispatchDue();
    }

    public function healthCheck(): ReportsHealthCheck
    {
        return new ReportsHealthCheck($this->pdo(), $this->providerRegistry());
    }

    public function providerRegistry(): ReportProviderRegistry
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ReportProviderRegistry::class);
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateScheduledReportsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('reports', self::getModuleInfo()['version'], 'reports');
        $components->enable('reports');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('reports', self::getModuleInfo()['version'], 'reports');
        $components->enable('reports');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Reports module removed. Scheduled report data was kept intact.'));
    }
}
