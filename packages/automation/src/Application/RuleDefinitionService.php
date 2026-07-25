<?php

declare(strict_types=1);

namespace Kontor\Automation\Application;

use Kontor\Automation\Domain\AutomationRule;
use Kontor\Automation\Domain\RuleAction;
use Kontor\Automation\Domain\RuleCondition;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use InvalidArgumentException;

/**
 * The authoring side of "triggers"/"conditions"/"actions". addAction()
 * validates the action key is actually registered before it can be
 * attached to a rule — the same "fail fast on a bad reference" instinct
 * kontor/dashboard's DashboardService::addWidget() applies to widget keys.
 */
final class RuleDefinitionService
{
    public function __construct(
        private readonly RuleRepository $rules,
        private readonly ConditionRepository $conditions,
        private readonly ActionRepository $actions,
        private readonly ActionHandlerRegistry $actionHandlers,
    ) {
    }

    public function defineRule(string $organizationUid, string $name, string $triggerEvent, ?int $createdBy = null): AutomationRule
    {
        $rule = AutomationRule::create($organizationUid, $name, $triggerEvent, $createdBy);
        $this->rules->save($rule);

        return $rule;
    }

    public function addCondition(string $ruleUid, string $field, string $operator, ?string $value, int $sortOrder = 0): RuleCondition
    {
        $rule = $this->rules->require($ruleUid);
        $condition = RuleCondition::create($rule->organizationId, $ruleUid, $field, $operator, $value, $sortOrder);
        $this->conditions->save($condition);

        return $condition;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function addAction(string $ruleUid, string $actionKey, array $params = [], int $sortOrder = 0): RuleAction
    {
        if (!$this->actionHandlers->has($actionKey)) {
            throw new InvalidArgumentException("\"{$actionKey}\" is not a registered action handler.");
        }

        $rule = $this->rules->require($ruleUid);
        $action = RuleAction::create($rule->organizationId, $ruleUid, $actionKey, $params, $sortOrder);
        $this->actions->save($action);

        return $action;
    }
}
