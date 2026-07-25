<?php

declare(strict_types=1);

namespace Kontor\Workflow\Application;

use Kontor\Workflow\Domain\WorkflowDefinition;
use Kontor\Workflow\Domain\WorkflowTransition;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;
use InvalidArgumentException;

/**
 * The "state machine" milestone's authoring side — kontor.md#18's
 * workflow-definition shape (key, entity type, states, transitions).
 * addTransition() validates from/to states against the definition's own
 * declared state list before storing anything, the same "fail fast on a
 * bad field" instinct kontor/reports' ReportBuilderService::validateFor()
 * already applied to report queries.
 */
final class WorkflowDefinitionService
{
    public function __construct(
        private readonly DefinitionRepository $definitions,
        private readonly TransitionRepository $transitions,
    ) {
    }

    /**
     * @param string[] $states
     */
    public function defineWorkflow(
        string $organizationUid,
        string $workflowKey,
        string $entityType,
        string $name,
        string $initialState,
        array $states,
        ?int $createdBy = null,
    ): WorkflowDefinition {
        $definition = WorkflowDefinition::create($organizationUid, $workflowKey, $entityType, $name, $initialState, $states, $createdBy);
        $this->definitions->save($definition);

        return $definition;
    }

    public function addTransition(
        string $definitionUid,
        string $actionKey,
        string $fromState,
        string $toState,
        ?string $requiredPermission = null,
        bool $requiresApproval = false,
    ): WorkflowTransition {
        $definition = $this->definitions->require($definitionUid);

        if (!$definition->hasState($fromState)) {
            throw new InvalidArgumentException("\"{$fromState}\" is not a state declared on workflow \"{$definition->workflowKey}\".");
        }

        if (!$definition->hasState($toState)) {
            throw new InvalidArgumentException("\"{$toState}\" is not a state declared on workflow \"{$definition->workflowKey}\".");
        }

        $transition = WorkflowTransition::create($definition->organizationId, $definitionUid, $actionKey, $fromState, $toState, $requiredPermission, $requiresApproval);
        $this->transitions->save($transition);

        return $transition;
    }
}
