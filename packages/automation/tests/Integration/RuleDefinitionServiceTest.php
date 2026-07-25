<?php

declare(strict_types=1);

namespace Kontor\Automation\Tests\Integration;

use Kontor\Automation\ActionHandlers\LogActionHandler;
use Kontor\Automation\Application\RuleDefinitionService;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class RuleDefinitionServiceTest extends DatabaseTestCase
{
    private RuleDefinitionService $definitions;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $registry = new ActionHandlerRegistry();
        $registry->register(new LogActionHandler());

        $this->definitions = new RuleDefinitionService(
            new RuleRepository($this->pdo, $organizations),
            new ConditionRepository($this->pdo, $organizations),
            new ActionRepository($this->pdo, $organizations),
            $registry,
        );
    }

    public function test_define_rule_then_add_condition_and_action(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'My rule', 'inventory.movement.completed');
        $condition = $this->definitions->addCondition($rule->uid->toString(), 'movementType', 'equals', 'receive');
        $action = $this->definitions->addAction($rule->uid->toString(), 'log', ['note' => 'test']);

        $this->assertSame('equals', $condition->operator);
        $this->assertSame('log', $action->actionKey);
        $this->assertSame(['note' => 'test'], $action->params);
    }

    public function test_add_condition_rejects_an_unsupported_operator(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'My rule', 'inventory.movement.completed');

        $this->expectException(\InvalidArgumentException::class);
        $this->definitions->addCondition($rule->uid->toString(), 'movementType', 'starts_with', 'rec');
    }

    public function test_add_action_rejects_an_unregistered_action_key(): void
    {
        $rule = $this->definitions->defineRule($this->organizationUid, 'My rule', 'inventory.movement.completed');

        $this->expectException(\InvalidArgumentException::class);
        $this->definitions->addAction($rule->uid->toString(), 'not_a_real_action');
    }
}
