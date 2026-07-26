<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Health\WorkflowHealthCheck;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;
use Kontor\Workflow\Migrations\Migration0001CreateDefinitionsTable;
use Kontor\Workflow\Migrations\Migration0002CreateTransitionsTable;
use Kontor\Workflow\Migrations\Migration0003CreateInstancesTable;
use Kontor\Workflow\Migrations\Migration0004CreateApprovalRequestsTable;
use Kontor\Workflow\Migrations\Migration0005CreateHistoryTable;

/**
 * KontorWorkflow bootstrap module (kontor.md Substage 7.1). First
 * component of Stage 7 (Extensibility). Depends only on kontor/core — a
 * generic, entity-agnostic engine, same shape as kontor/dashboard's
 * widget registry.
 */
class KontorWorkflow extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Workflow',
            'summary' => 'State machine, transition permissions, approvals, history.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorWorkflow',
            'icon' => 'sitemap',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-workflow-definition-view' => 'View workflow definitions',
                'kontor-workflow-definition-manage' => 'Create and edit workflow definitions',
                'kontor-workflow-transition' => 'Perform workflow transitions',
                'kontor-workflow-approve' => 'Approve or reject pending workflow transitions',
                'kontor-workflow-history-view' => 'View workflow history',
            ],
        ];
    }

    private ?DefinitionRepository $definitionRepository = null;
    private ?TransitionRepository $transitionRepository = null;
    private ?InstanceRepository $instanceRepository = null;
    private ?ApprovalRequestRepository $approvalRequestRepository = null;
    private ?HistoryRepository $historyRepository = null;

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
            $translations->register('KontorWorkflow', $language, $strings);
        }
    }

    public function definitionRepository(): DefinitionRepository
    {
        return $this->definitionRepository ??= new DefinitionRepository($this->pdo(), $this->organizations());
    }

    public function transitionRepository(): TransitionRepository
    {
        return $this->transitionRepository ??= new TransitionRepository($this->pdo(), $this->organizations());
    }

    public function instanceRepository(): InstanceRepository
    {
        return $this->instanceRepository ??= new InstanceRepository($this->pdo(), $this->organizations());
    }

    public function approvalRequestRepository(): ApprovalRequestRepository
    {
        return $this->approvalRequestRepository ??= new ApprovalRequestRepository($this->pdo(), $this->organizations());
    }

    public function historyRepository(): HistoryRepository
    {
        return $this->historyRepository ??= new HistoryRepository($this->pdo(), $this->organizations());
    }

    public function definitions(): WorkflowDefinitionService
    {
        return new WorkflowDefinitionService($this->definitionRepository(), $this->transitionRepository());
    }

    public function engine(): WorkflowEngine
    {
        return new WorkflowEngine(
            $this->definitionRepository(), $this->transitionRepository(), $this->instanceRepository(),
            $this->approvalRequestRepository(), $this->historyRepository(),
        );
    }

    public function healthCheck(): WorkflowHealthCheck
    {
        return new WorkflowHealthCheck($this->pdo());
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
            new Migration0001CreateDefinitionsTable(),
            new Migration0002CreateTransitionsTable(),
            new Migration0003CreateInstancesTable(),
            new Migration0004CreateApprovalRequestsTable(),
            new Migration0005CreateHistoryTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('workflow', self::getModuleInfo()['version'], 'workflow');
        $components->enable('workflow');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('workflow', self::getModuleInfo()['version'], 'workflow');
        $components->enable('workflow');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Workflow module removed. Workflow definitions and history were kept intact.'));
    }
}
