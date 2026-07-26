<?php

namespace ProcessWire;

use Kontor\Automation\ActionHandlers\LogActionHandler;
use Kontor\Automation\Application\AutomationEngine;
use Kontor\Automation\Application\ConditionEvaluator;
use Kontor\Automation\Application\RuleDefinitionService;
use Kontor\Automation\Health\AutomationHealthCheck;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\ExecutionLogRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\Automation\Migrations\Migration0001CreateRulesTable;
use Kontor\Automation\Migrations\Migration0002CreateConditionsTable;
use Kontor\Automation\Migrations\Migration0003CreateActionsTable;
use Kontor\Automation\Migrations\Migration0004CreateExecutionLogsTable;
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;

/**
 * KontorAutomation bootstrap module (kontor.md Substage 7.2). Depends
 * only on kontor/core. init() actually wires AutomationEngine::handleEvent()
 * onto Core's real EventDispatcher — for every distinct trigger_event
 * across every active rule (in any organization), subscribe once; the
 * listener re-resolves which organization's rules apply from the event
 * itself. This is a real integration, not deferred, the same choice
 * kontor/purchasing and kontor/projects made for their own
 * "integration" milestones. New rules using an event name no rule
 * currently uses take effect from the next request (module init runs
 * once per request) — acceptable for a synchronous, in-process event bus.
 */
class KontorAutomation extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Automation',
            'summary' => 'Triggers, conditions, actions, dry run, logs, recursion protection.',
            'version' => '003',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorAutomation',
            'icon' => 'bolt',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-automation-rule-view' => 'View automation rules',
                'kontor-automation-rule-manage' => 'Create and edit automation rules',
                'kontor-automation-dry-run' => 'Dry-run automation rules',
                'kontor-automation-log-view' => 'View automation execution logs',
            ],
        ];
    }

    private ?RuleRepository $ruleRepository = null;
    private ?ConditionRepository $conditionRepository = null;
    private ?ActionRepository $actionRepository = null;
    private ?ExecutionLogRepository $executionLogRepository = null;
    private ?ActionHandlerRegistry $actionHandlerRegistry = null;
    private ?AutomationEngine $engine = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $this->actionHandlerRegistry()->register(new LogActionHandler());
        $this->subscribeToTriggerEvents($kontor->container()->get(EventDispatcher::class));
    }

    private function subscribeToTriggerEvents(EventDispatcher $dispatcher): void
    {
        foreach ($this->ruleRepository()->distinctActiveTriggerEvents() as $eventName) {
            $dispatcher->subscribe($eventName, fn ($event) => $this->engine()->handleEvent($event));
        }
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorAutomation', $language, $strings);
        }
    }

    public function ruleRepository(): RuleRepository
    {
        return $this->ruleRepository ??= new RuleRepository($this->pdo(), $this->organizations());
    }

    public function conditionRepository(): ConditionRepository
    {
        return $this->conditionRepository ??= new ConditionRepository($this->pdo(), $this->organizations());
    }

    public function actionRepository(): ActionRepository
    {
        return $this->actionRepository ??= new ActionRepository($this->pdo(), $this->organizations());
    }

    public function executionLogRepository(): ExecutionLogRepository
    {
        return $this->executionLogRepository ??= new ExecutionLogRepository($this->pdo(), $this->organizations());
    }

    public function actionHandlerRegistry(): ActionHandlerRegistry
    {
        return $this->actionHandlerRegistry ??= new ActionHandlerRegistry();
    }

    public function definitions(): RuleDefinitionService
    {
        return new RuleDefinitionService($this->ruleRepository(), $this->conditionRepository(), $this->actionRepository(), $this->actionHandlerRegistry());
    }

    public function engine(): AutomationEngine
    {
        return $this->engine ??= new AutomationEngine(
            $this->ruleRepository(), $this->conditionRepository(), $this->actionRepository(), $this->executionLogRepository(),
            $this->actionHandlerRegistry(), new ConditionEvaluator(),
        );
    }

    public function healthCheck(): AutomationHealthCheck
    {
        return new AutomationHealthCheck($this->pdo(), $this->actionHandlerRegistry());
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
            new Migration0001CreateRulesTable(),
            new Migration0002CreateConditionsTable(),
            new Migration0003CreateActionsTable(),
            new Migration0004CreateExecutionLogsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('automation', self::getModuleInfo()['version'], 'automation');
        $components->enable('automation');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('automation', self::getModuleInfo()['version'], 'automation');
        $components->enable('automation');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Automation module removed. Rules and execution logs were kept intact.'));
    }
}
