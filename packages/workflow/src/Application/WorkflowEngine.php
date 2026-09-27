<?php

declare(strict_types=1);

namespace Kontor\Workflow\Application;

use Kontor\Workflow\Domain\ApprovalRequest;
use Kontor\Workflow\Domain\HistoryEntry;
use Kontor\Workflow\Domain\WorkflowInstance;
use Kontor\Workflow\Domain\WorkflowTransition;
use Kontor\Workflow\Infrastructure\Persistence\ApprovalRequestRepository;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\HistoryRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * The "state machine", "transition permissions", "approvals" and
 * "history" milestones. A generic, entity-agnostic engine — like
 * kontor/dashboard's WidgetRegistry, it's not retrofitted into any
 * already-shipped component's own hardcoded workflow (QuotationWorkflowService,
 * InvoiceWorkflowService, ExpenseWorkflowService, …), which is exactly
 * what kontor.md#18 calls a component's own "safe default workflow" that
 * must keep working "even if KontorWorkflow is not installed". Other
 * components could opt an entity into a configured workflow instead —
 * none do in this substage; see the README.
 *
 * transition_permissions: kontor.md#19.8's own list puts permission
 * checks in admin controllers/API endpoints, not this engine — the caller
 * passes $actorHasPermission after checking it themselves; the engine
 * only refuses to proceed when told it wasn't granted.
 */
final class WorkflowEngine
{
    public function __construct(
        private readonly DefinitionRepository $definitions,
        private readonly TransitionRepository $transitions,
        private readonly InstanceRepository $instances,
        private readonly ApprovalRequestRepository $approvalRequests,
        private readonly HistoryRepository $history,
    ) {
    }

    public function start(string $organizationUid, string $definitionUid, string $entityType, string $entityUid): WorkflowInstance
    {
        $definition = $this->definitions->require($definitionUid);

        if ($definition->entityType !== $entityType) {
            throw new InvalidArgumentException("Workflow \"{$definition->workflowKey}\" is defined for entity type \"{$definition->entityType}\", not \"{$entityType}\".");
        }

        if ($this->instances->findForEntity($organizationUid, $entityType, $entityUid) !== null) {
            throw new RuntimeException("{$entityType} \"{$entityUid}\" already has an active workflow instance.");
        }

        $instance = WorkflowInstance::create($organizationUid, $definitionUid, $entityType, $entityUid, $definition->initialState);
        $this->instances->save($instance);

        return $instance;
    }

    public function currentState(string $organizationUid, string $entityType, string $entityUid): string
    {
        return $this->instances->requireForEntity($organizationUid, $entityType, $entityUid)->currentState;
    }

    /**
     * The "visual editor" milestone's backend: which actions a UI should
     * offer from wherever this entity currently is.
     *
     * @return string[]
     */
    public function availableActions(string $organizationUid, string $entityType, string $entityUid): array
    {
        $instance = $this->instances->requireForEntity($organizationUid, $entityType, $entityUid);

        return array_map(
            static fn (WorkflowTransition $t): string => $t->actionKey,
            $this->transitions->fromState($instance->definitionUid, $instance->currentState),
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function transition(
        string $organizationUid,
        string $entityType,
        string $entityUid,
        string $actionKey,
        ?int $actorUserId = null,
        bool $actorHasPermission = true,
        array $metadata = [],
    ): WorkflowInstance|ApprovalRequest {
        $instance = $this->instances->requireForEntity($organizationUid, $entityType, $entityUid);
        $transition = $this->transitions->findByFromStateAndAction($instance->definitionUid, $instance->currentState, $actionKey);

        if ($transition === null) {
            throw new RuntimeException("\"{$actionKey}\" is not a valid action from state \"{$instance->currentState}\".");
        }

        if ($transition->requiredPermission !== null && !$actorHasPermission) {
            throw new RuntimeException("Transition \"{$actionKey}\" requires permission \"{$transition->requiredPermission}\".");
        }

        if ($transition->requiresApproval) {
            $request = ApprovalRequest::create($organizationUid, $instance->uid->toString(), $actionKey, $instance->currentState, $transition->toState, $actorUserId);
            $this->approvalRequests->save($request);

            return $request;
        }

        return $this->applyTransition($instance, $transition, $actorUserId, $metadata);
    }

    public function approve(string $approvalRequestUid, int $approvedBy): WorkflowInstance
    {
        $request = $this->approvalRequests->require($approvalRequestUid);

        if (!$request->isPending()) {
            throw new RuntimeException("Approval request \"{$approvalRequestUid}\" has already been decided.");
        }

        $instance = $this->instances->require($request->instanceUid);

        if ($instance->currentState !== $request->fromState) {
            throw new RuntimeException("{$instance->entityType} \"{$instance->entityUid}\" has moved since this approval was requested.");
        }

        $transition = $this->transitions->findByFromStateAndAction($instance->definitionUid, $request->fromState, $request->actionKey);

        if ($transition === null) {
            throw new RuntimeException("Transition \"{$request->actionKey}\" no longer exists on this workflow.");
        }

        $request->status = 'approved';
        $request->decidedBy = $approvedBy;
        $request->decidedAt = new \DateTimeImmutable();
        $this->approvalRequests->save($request);

        return $this->applyTransition($instance, $transition, $approvedBy, []);
    }

    public function reject(string $approvalRequestUid, int $decidedBy, string $reason): ApprovalRequest
    {
        $request = $this->approvalRequests->require($approvalRequestUid);

        if (!$request->isPending()) {
            throw new RuntimeException("Approval request \"{$approvalRequestUid}\" has already been decided.");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $request->status = 'rejected';
        $request->decidedBy = $decidedBy;
        $request->decidedAt = new \DateTimeImmutable();
        $request->rejectionReason = $reason;
        $this->approvalRequests->save($request);

        return $request;
    }

    /**
     * @return HistoryEntry[] oldest first
     */
    public function history(string $entityType, string $entityUid): array
    {
        return $this->history->forEntity($entityType, $entityUid);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyTransition(WorkflowInstance $instance, WorkflowTransition $transition, ?int $actorUserId, array $metadata): WorkflowInstance
    {
        $fromState = $instance->currentState;

        $instance->currentState = $transition->toState;
        $instance->updatedAt = new \DateTimeImmutable();
        $this->instances->save($instance);

        $this->history->insert(HistoryEntry::create(
            $instance->organizationId, $instance->definitionUid, $instance->entityType, $instance->entityUid,
            $transition->actionKey, $fromState, $transition->toState, $actorUserId, $metadata,
        ));

        return $instance;
    }
}
