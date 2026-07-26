<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\CRM\Application\CRMService;
use Kontor\CRM\Application\KanbanBoardService;
use Kontor\CRM\Application\LeadConversionService;
use Kontor\CRM\Contracts\CRMServiceInterface;
use Kontor\CRM\Health\CRMHealthCheck;
use Kontor\CRM\Infrastructure\Export\DealExportProvider;
use Kontor\CRM\Infrastructure\Export\LeadExportProvider;
use Kontor\CRM\Infrastructure\Import\DealImportProvider;
use Kontor\CRM\Infrastructure\Import\LeadImportProvider;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\CRM\Infrastructure\Reports\PipelineReportProvider;
use Kontor\CRM\Migrations\Migration0001CreateLeadsTable;
use Kontor\CRM\Migrations\Migration0002CreatePipelinesTable;
use Kontor\CRM\Migrations\Migration0003CreateStagesTable;
use Kontor\CRM\Migrations\Migration0004CreateDealsTable;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Search\Infrastructure\Search\SqlFullTextSearchProvider;

/**
 * KontorCRM bootstrap module (kontor.md Substage 3.3), following the
 * canonical manifest example in kontor.md#22.1 as closely as this stage's
 * milestones allow: registers the "crm" capability
 * (Kontor\CRM\Contracts\CRMServiceInterface), import/export/report
 * providers into Core's registries, a repository into RepositoryRegistry
 * for import rollback, and search providers into KontorSearch's.
 */
class KontorCRM extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor CRM',
            'summary' => 'Leads, pipelines, stages, deals, conversion, Kanban board data and pipeline reports.',
            'version' => '006',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorCRM',
            'icon' => 'handshake-o',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorContacts', 'KontorSearch'],
            'permissions' => [
                'kontor-crm-lead-view' => 'View leads',
                'kontor-crm-lead-create' => 'Create leads',
                'kontor-crm-lead-edit' => 'Edit leads',
                'kontor-crm-lead-convert' => 'Convert leads to deals',
                'kontor-crm-lead-archive' => 'Archive leads',
                'kontor-crm-deal-view' => 'View deals',
                'kontor-crm-deal-create' => 'Create deals',
                'kontor-crm-deal-edit' => 'Edit deals',
                'kontor-crm-deal-move' => 'Move deals between pipeline stages',
                'kontor-crm-deal-close-won' => 'Close deals as won',
                'kontor-crm-deal-close-lost' => 'Close deals as lost',
                'kontor-crm-pipeline-admin' => 'Administer pipelines and stages',
            ],
        ];
    }

    private ?LeadRepository $leadRepository = null;
    private ?DealRepository $dealRepository = null;
    private ?PipelineRepository $pipelineRepository = null;
    private ?StageRepository $stageRepository = null;
    private ?CRMServiceInterface $crmService = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        /** @var KontorSearch $searchModule */
        $searchModule = $this->wire()->modules->get('KontorSearch');

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'crm',
            version: '1.0',
            contract: CRMServiceInterface::class,
            implementation: $this->crmService(),
            component: 'KontorCRM',
        );

        $kontor->container()->get(ImportProviderRegistry::class)->register(
            'lead',
            new LeadImportProvider($this->leadRepository())
        );
        $kontor->container()->get(ImportProviderRegistry::class)->register(
            'deal',
            new DealImportProvider($this->dealRepository())
        );

        $organizations = $kontor->container()->get(OrganizationRepository::class);

        $kontor->container()->get(ExportProviderRegistry::class)->register(
            'lead',
            new LeadExportProvider($this->pdo(), $organizations)
        );
        $kontor->container()->get(ExportProviderRegistry::class)->register(
            'deal',
            new DealExportProvider($this->pdo(), $organizations)
        );

        $kontor->container()->get(RepositoryRegistry::class)->register('lead', $this->leadRepository());
        $kontor->container()->get(RepositoryRegistry::class)->register('deal', $this->dealRepository());

        $kontor->container()->get(ReportProviderRegistry::class)->register(new PipelineReportProvider($this->pdo(), $organizations));

        $searchModule->providerRegistry()->register(new SqlFullTextSearchProvider(
            pdo: $this->pdo(),
            organizations: $organizations,
            providerName: 'leads',
            entityType: 'lead',
            table: 'kontor_crm_leads',
            uidColumn: 'uid',
            titleColumn: 'title',
            subtitleColumn: 'status',
            fullTextColumns: ['title'],
        ));
        $searchModule->providerRegistry()->register(new SqlFullTextSearchProvider(
            pdo: $this->pdo(),
            organizations: $organizations,
            providerName: 'deals',
            entityType: 'deal',
            table: 'kontor_crm_deals',
            uidColumn: 'uid',
            titleColumn: 'title',
            subtitleColumn: 'status',
            fullTextColumns: ['title'],
        ));

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
            $translations->register('KontorCRM', $language, $strings);
        }
    }

    public function leadRepository(): LeadRepository
    {
        return $this->leadRepository ??= new LeadRepository($this->pdo(), $this->organizations());
    }

    public function dealRepository(): DealRepository
    {
        return $this->dealRepository ??= new DealRepository($this->pdo(), $this->organizations());
    }

    public function pipelineRepository(): PipelineRepository
    {
        return $this->pipelineRepository ??= new PipelineRepository($this->pdo(), $this->organizations());
    }

    public function stageRepository(): StageRepository
    {
        return $this->stageRepository ??= new StageRepository($this->pdo());
    }

    public function crmService(): CRMServiceInterface
    {
        if ($this->crmService !== null) {
            return $this->crmService;
        }

        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $conversion = new LeadConversionService(
            $this->leadRepository(),
            $this->pipelineRepository(),
            $this->stageRepository(),
            $this->dealRepository(),
        );

        return $this->crmService = new CRMService(
            $conversion,
            $this->dealRepository(),
            $this->stageRepository(),
            $kontor->container()->get(EventDispatcher::class),
        );
    }

    public function kanbanBoard(): KanbanBoardService
    {
        return new KanbanBoardService($this->stageRepository(), $this->dealRepository());
    }

    public function healthCheck(): CRMHealthCheck
    {
        return new CRMHealthCheck($this->pdo());
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
            new Migration0001CreateLeadsTable(),
            new Migration0002CreatePipelinesTable(),
            new Migration0003CreateStagesTable(),
            new Migration0004CreateDealsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('crm', self::getModuleInfo()['version'], 'crm');
        $components->enable('crm');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('crm', self::getModuleInfo()['version'], 'crm');
        $components->enable('crm');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor CRM module removed. Lead and deal data was kept intact.'));
    }
}
