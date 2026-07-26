<?php

declare(strict_types=1);

namespace Kontor\Automation\Application;

use Kontor\Automation\Domain\AutomationRule;
use Kontor\Automation\Domain\ExecutionLog;
use Kontor\Automation\Infrastructure\Persistence\ActionRepository;
use Kontor\Automation\Infrastructure\Persistence\ConditionRepository;
use Kontor\Automation\Infrastructure\Persistence\ExecutionLogRepository;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Automation\Infrastructure\Registry\ActionHandlerRegistry;
use Kontor\SDK\Events\KontorEvent;

/**
 * kontor.md#30: "Trigger -> Conditions -> Actions". handleEvent() is
 * meant to be the callback KontorAutomation::init() subscribes to Core's
 * real EventDispatcherInterface for every distinct trigger_event — see
 * that module's doc comment. It's also exactly what an action handler
 * should call if it wants to cause further automation as a side effect
 * (by dispatching a new KontorEvent through the same dispatcher, which
 * synchronously re-invokes this same subscribed callback) — recursion
 * protection below is what keeps that safe.
 *
 * "recursion protection": Core's EventDispatcher::dispatch() is fully
 * synchronous (kontor.md#9.3) and has no notion of call depth of its own
 * — it just invokes whatever's subscribed. If an action handler causes a
 * new dispatch() that re-enters this same engine instance's handleEvent()
 * before the outer call has returned, that's still the same PHP call
 * stack, so a plain instance counter correctly tracks it.
 */
final class AutomationEngine
{
    private const MAX_DEPTH = 5;

    private int $depth = 0;

    public function __construct(
        private readonly RuleRepository $rules,
        private readonly ConditionRepository $conditions,
        private readonly ActionRepository $actions,
        private readonly ExecutionLogRepository $logs,
        private readonly ActionHandlerRegistry $actionHandlers,
        private readonly ConditionEvaluator $evaluator,
    ) {
    }

    /**
     * @return array<int, array{ruleUid: string, matched: bool, actionsResult: array<int, array<string, mixed>>}>
     */
    public function handleEvent(KontorEvent $event, bool $dryRun = false): array
    {
        if ($this->depth >= self::MAX_DEPTH) {
            $this->logs->insert(ExecutionLog::create(
                $event->organizationId, null, $event->event, matched: false, dryRun: $dryRun, recursionBlocked: true,
            ));

            return [];
        }

        $this->depth++;

        try {
            $results = [];

            foreach ($this->rules->activeForTrigger($event->organizationId, $event->event) as $rule) {
                $results[] = $this->evaluateRule($rule, $event, $dryRun);
            }

            return $results;
        } finally {
            $this->depth--;
        }
    }

    /**
     * @return array{ruleUid: string, matched: bool, actionsResult: array<int, array<string, mixed>>}
     */
    private function evaluateRule(AutomationRule $rule, KontorEvent $event, bool $dryRun): array
    {
        $ruleUid = $rule->uid->toString();
        $matched = $this->evaluator->evaluate($this->conditions->forRule($ruleUid), $event->data);

        $actionsResult = [];
        $error = null;

        if ($matched && !$dryRun) {
            $actionData = $event->data;
            $actionData['_event'] = $event->toArray();
            foreach ($this->actions->forRule($ruleUid) as $action) {
                try {
                    $result = $this->actionHandlers->get($action->actionKey)->execute($actionData, $action->params);
                    $actionsResult[] = ['actionKey' => $action->actionKey, 'result' => $result];
                } catch (\Throwable $e) {
                    // One action failing does not stop the rest, mirroring
                    // EventDispatcher's own "a listener throwing does not
                    // stop the remaining listeners" behavior.
                    $error = $e->getMessage();
                    $actionsResult[] = ['actionKey' => $action->actionKey, 'result' => null, 'error' => $e->getMessage()];
                }
            }
        }

        $this->logs->insert(ExecutionLog::create($rule->organizationId, $ruleUid, $event->event, $matched, $dryRun, false, $actionsResult, $error));

        return ['ruleUid' => $ruleUid, 'matched' => $matched, 'actionsResult' => $actionsResult];
    }
}
