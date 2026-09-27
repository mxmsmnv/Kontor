<?php

declare(strict_types=1);

namespace Kontor\Automation\Tests\Integration;

use Kontor\Automation\ActionHandlers\LogActionHandler;
use Kontor\Automation\Application\AutomationEngine;
use Kontor\Automation\Application\ConditionEvaluator;
use Kontor\Automation\Application\RuleDefinitionService;
use Kontor\Automation\Contracts\ActionHandlerInterface;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\ExecutionLogRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Events\KontorEvent;

final class AutomationEngineTest extends DatabaseTestCase
{
    private RuleDefinitionService $definitions;
    private AutomationEngine $engine;
    private ExecutionLogRepository $logs;
    private ActionHandlerRegistry $actionHandlers;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $ruleRepository = new RuleRepository($this->pdo, $organizations);
        $conditionRepository = new ConditionRepository($this->pdo, $organizations);
        $actionRepository = new ActionRepository($this->pdo, $organizations);
        $this->logs = new ExecutionLogRepository($this->pdo, $organizations);
        $this->actionHandlers = new ActionHandlerRegistry();
        $this->actionHandlers->register(new LogActionHandler());

        $this->definitions = new RuleDefinitionService($ruleRepository, $conditionRepository, $actionRepository, $this->actionHandlers);
        $this->engine = new AutomationEngine($ruleRepository, $conditionRepository, $actionRepository, $this->logs, $this->actionHandlers, new ConditionEvaluator());
    }

    private function event(array $data = ['movementType' => 'receive', 'quantity' => 10.0]): KontorEvent
    {
        return KontorEvent::create(
            event: 'inventory.movement.completed',
            organizationId: $this->organizationUid,
            entityType: 'inventory_movement',
            entityId: 'mov_01',
            actorType: 'system',
            actorId: null,
            data: $data,
        );
    }

    public function test_a_matching_rule_executes_its_actions_and_logs_the_result(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'Log all receives', 'inventory.movement.completed');
        $this->definitions->addCondition($rule->uid->toString(), 'movementType', 'equals', 'receive');
        $this->definitions->addAction($rule->uid->toString(), 'log');

        $results = $this->engine->handleEvent($this->event());

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['matched']);
        $this->assertSame('log', $results[0]['actionsResult'][0]['actionKey']);
        $this->assertSame(
            $this->organizationUid,
            $results[0]['actionsResult'][0]['result']['loggedEventData']['_event']['organizationId'],
        );
        $this->assertSame(
            'inventory_movement',
            $results[0]['actionsResult'][0]['result']['loggedEventData']['_event']['entityType'],
        );

        $logs = $this->logs->forRule($rule->uid->toString());
        $this->assertCount(1, $logs);
        $this->assertTrue($logs[0]->matched);
        $this->assertFalse($logs[0]->dryRun);
    }

    public function test_a_non_matching_condition_skips_actions_but_still_logs(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'Log all transfers', 'inventory.movement.completed');
        $this->definitions->addCondition($rule->uid->toString(), 'movementType', 'equals', 'transfer');
        $this->definitions->addAction($rule->uid->toString(), 'log');

        $results = $this->engine->handleEvent($this->event());

        $this->assertFalse($results[0]['matched']);
        $this->assertSame([], $results[0]['actionsResult']);

        $logs = $this->logs->forRule($rule->uid->toString());
        $this->assertCount(1, $logs);
        $this->assertFalse($logs[0]->matched);
    }

    public function test_dry_run_reports_a_match_without_executing_any_action(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'Log all receives', 'inventory.movement.completed');
        $this->definitions->addCondition($rule->uid->toString(), 'movementType', 'equals', 'receive');
        $this->definitions->addAction($rule->uid->toString(), 'log');

        $results = $this->engine->handleEvent($this->event(), dryRun: true);

        $this->assertTrue($results[0]['matched']);
        $this->assertSame([], $results[0]['actionsResult']);

        $logs = $this->logs->forRule($rule->uid->toString());
        $this->assertTrue($logs[0]->dryRun);
    }

    public function test_a_rule_with_no_conditions_always_matches(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'Log everything', 'inventory.movement.completed');
        $this->definitions->addAction($rule->uid->toString(), 'log');

        $results = $this->engine->handleEvent($this->event(['anything' => 'goes']));

        $this->assertTrue($results[0]['matched']);
    }

    public function test_an_inactive_rule_is_never_evaluated(): void
    {
        $this->definitions->defineRule($this->organizationUid, 'Disabled rule', 'inventory.movement.completed');
        // No direct "disable" API is exposed by RuleDefinitionService (not
        // a listed milestone) — flip status straight through the repository
        // to prove activeForTrigger() actually filters on it.
        $this->pdo->exec("UPDATE kontor_automation_rules SET status = 'inactive'");

        $results = $this->engine->handleEvent($this->event());

        $this->assertSame([], $results);
    }

    public function test_addAction_rejects_an_unregistered_action_key(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'Broken rule', 'inventory.movement.completed');

        $this->expectException(\InvalidArgumentException::class);
        $this->definitions->addAction($rule->uid->toString(), 'send_carrier_pigeon');
    }

    public function test_a_failing_action_does_not_prevent_other_actions_from_running(): void
    {
        $this->actionHandlers->register(new class implements ActionHandlerInterface {
            public function key(): string
            {
                return 'always_fails';
            }

            public function execute(array $eventData, array $params): array
            {
                throw new \RuntimeException('boom');
            }
        });

        $rule = $this->definitions->defineRule($this->organizationUid, 'Two actions', 'inventory.movement.completed');
        $this->definitions->addAction($rule->uid->toString(), 'always_fails', sortOrder: 0);
        $this->definitions->addAction($rule->uid->toString(), 'log', sortOrder: 1);

        $results = $this->engine->handleEvent($this->event());

        $this->assertCount(2, $results[0]['actionsResult']);
        $this->assertSame('always_fails', $results[0]['actionsResult'][0]['actionKey']);
        $this->assertArrayHasKey('error', $results[0]['actionsResult'][0]);
        $this->assertSame('log', $results[0]['actionsResult'][1]['actionKey']);
    }

    public function test_recursion_protection_stops_a_re_entrant_event_chain(): void
    {
        $recursingHandler = new class($this->engine) implements ActionHandlerInterface {
            public int $callCount = 0;

            public function __construct(private readonly AutomationEngine $engine)
            {
            }

            public function key(): string
            {
                return 'recurse';
            }

            public function execute(array $eventData, array $params): array
            {
                $this->callCount++;

                $this->engine->handleEvent(KontorEvent::create('inventory.movement.completed', $eventData['organizationId'], 'x', 'x', 'system', null, $eventData));

                return [];
            }
        };
        $this->actionHandlers->register($recursingHandler);

        $rule = $this->definitions->defineRule($this->organizationUid, 'Recursive rule', 'inventory.movement.completed');
        $this->definitions->addAction($rule->uid->toString(), 'recurse');

        $this->engine->handleEvent($this->event(['organizationId' => $this->organizationUid]));

        // MAX_DEPTH is 5: the outer call is depth 1, and each action
        // re-enters one level deeper, so it must stop well short of
        // unbounded recursion.
        $this->assertLessThanOrEqual(5, $recursingHandler->callCount);

        $recursionBlocked = array_filter($this->logs->forOrganization($this->organizationUid), fn ($l) => $l->recursionBlocked);
        $this->assertNotEmpty($recursionBlocked);
    }
}
