<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Projects\Application\ProjectInvoicingService;
use Kontor\Projects\Application\ProjectMilestoneService;
use Kontor\Projects\Application\TimeTrackingService;
use Kontor\Projects\Health\ProjectsHealthCheck;
use Kontor\Projects\Infrastructure\Persistence\BillableItemRepository;
use Kontor\Projects\Infrastructure\Persistence\MilestoneRepository;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;
use Kontor\Projects\Migrations\Migration0001CreateProjectsTable;
use Kontor\Projects\Migrations\Migration0002CreateProjectMilestonesTable;
use Kontor\Projects\Migrations\Migration0003CreateTimeEntriesTable;
use Kontor\Projects\Migrations\Migration0004CreateBillableItemsTable;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

/**
 * KontorProjects bootstrap module (kontor.md Substage 6.4). Fourth and
 * final component of Stage 6. Requires KontorSales (shared document
 * lines) and KontorInvoices (the "invoicing integration" milestone).
 */
class KontorProjects extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Projects',
            'summary' => 'Projects, milestones, time tracking, billable items, invoicing integration.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorProjects',
            'icon' => 'tasks',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorSales', 'KontorInvoices'],
            'permissions' => [
                'kontor-projects-project-view' => 'View projects',
                'kontor-projects-project-create' => 'Create projects',
                'kontor-projects-project-edit' => 'Edit projects',
                'kontor-projects-project-archive' => 'Archive projects',
                'kontor-projects-milestone-manage' => 'Manage project milestones',
                'kontor-projects-time-track' => 'Track time on projects',
                'kontor-projects-billable-item-manage' => 'Manage billable items',
                'kontor-projects-invoice-generate' => 'Generate invoices from a project',
            ],
        ];
    }

    private ?ProjectRepository $projectRepository = null;
    private ?MilestoneRepository $milestoneRepository = null;
    private ?TimeEntryRepository $timeEntryRepository = null;
    private ?BillableItemRepository $billableItemRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorProjects', $language, $strings);
        }
    }

    public function projectRepository(): ProjectRepository
    {
        return $this->projectRepository ??= new ProjectRepository($this->pdo(), $this->organizations());
    }

    public function milestoneRepository(): MilestoneRepository
    {
        return $this->milestoneRepository ??= new MilestoneRepository($this->pdo(), $this->organizations());
    }

    public function timeEntryRepository(): TimeEntryRepository
    {
        return $this->timeEntryRepository ??= new TimeEntryRepository($this->pdo(), $this->organizations());
    }

    public function billableItemRepository(): BillableItemRepository
    {
        return $this->billableItemRepository ??= new BillableItemRepository($this->pdo(), $this->organizations());
    }

    public function timeTracking(): TimeTrackingService
    {
        return new TimeTrackingService($this->timeEntryRepository());
    }

    public function milestones(): ProjectMilestoneService
    {
        return new ProjectMilestoneService($this->milestoneRepository());
    }

    public function invoicing(): ProjectInvoicingService
    {
        /** @var KontorInvoices $invoicesModule */
        $invoicesModule = $this->wire()->modules->get('KontorInvoices');
        /** @var KontorSales $salesModule */
        $salesModule = $this->wire()->modules->get('KontorSales');

        return new ProjectInvoicingService(
            $this->pdo(),
            $this->projectRepository(),
            $this->timeEntryRepository(),
            $this->billableItemRepository(),
            $invoicesModule->invoiceRepository(),
            $salesModule->documentLineRepository(),
        );
    }

    public function healthCheck(): ProjectsHealthCheck
    {
        return new ProjectsHealthCheck($this->pdo());
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
            new Migration0001CreateProjectsTable(),
            new Migration0002CreateProjectMilestonesTable(),
            new Migration0003CreateTimeEntriesTable(),
            new Migration0004CreateBillableItemsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('projects', self::getModuleInfo()['version'], 'projects');
        $components->enable('projects');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Projects module removed. Project data was kept intact.'));
    }
}
