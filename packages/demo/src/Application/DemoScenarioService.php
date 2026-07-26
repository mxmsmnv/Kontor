<?php

declare(strict_types=1);

namespace Kontor\Demo\Application;

use Kontor\Demo\Domain\DemoScenario;
use Kontor\Demo\Infrastructure\Persistence\DemoScenarioRepository;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Domain\ApprovalRequest;
use Kontor\Workflow\Domain\WorkflowInstance;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\TransitionRepository;

final class DemoScenarioService
{
    public const WORKFLOW_KEY = 'kontor_demo_order_to_cash';
    public const ENTITY_TYPE = 'demo_scenario';

    /**
     * @var array<string, array{from: string, to: string, approval: bool}>
     */
    public const TRANSITIONS = [
        'prepare_proposal' => ['from' => 'intake', 'to' => 'proposal', 'approval' => false],
        'request_approval' => ['from' => 'proposal', 'to' => 'approval', 'approval' => true],
        'start_delivery' => ['from' => 'approval', 'to' => 'delivery', 'approval' => false],
        'issue_invoice' => ['from' => 'delivery', 'to' => 'billing', 'approval' => false],
        'settle' => ['from' => 'billing', 'to' => 'completed', 'approval' => false],
    ];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly DemoScenarioRepository $scenarios,
        private readonly DefinitionRepository $definitions,
        private readonly TransitionRepository $transitions,
        private readonly WorkflowDefinitionService $definitionService,
        private readonly WorkflowEngine $workflow,
    ) {
    }

    /**
     * @param callable(DemoScenario): array<string, string> $intake
     */
    public function start(
        string $organizationUid,
        string $name,
        ?int $createdBy,
        callable $intake,
    ): DemoScenario {
        return $this->transaction(function () use ($organizationUid, $name, $createdBy, $intake): DemoScenario {
            $definitionUid = $this->ensureWorkflow($organizationUid, $createdBy);
            $scenario = DemoScenario::create($organizationUid, $name, $createdBy);
            $scenario->record($intake($scenario));
            $this->scenarios->save($scenario);
            $instance = $this->workflow->start(
                $organizationUid,
                $definitionUid,
                self::ENTITY_TYPE,
                $scenario->uid->toString(),
            );
            $scenario->workflowInstanceUid = $instance->uid->toString();
            $this->scenarios->save($scenario);

            return $scenario;
        });
    }

    /**
     * @param callable(DemoScenario): array<string, string> $effect
     */
    public function advance(
        string $scenarioUid,
        string $action,
        int $actorUserId,
        bool $actorHasPermission,
        callable $effect,
    ): DemoScenario {
        if (!isset(self::TRANSITIONS[$action])) {
            throw new \InvalidArgumentException("Unknown demo action \"{$action}\".");
        }

        return $this->transaction(function () use ($scenarioUid, $action, $actorUserId, $actorHasPermission, $effect): DemoScenario {
            $scenario = $this->scenarios->require($scenarioUid);
            $expected = self::TRANSITIONS[$action]['from'];
            if ($scenario->currentState !== $expected) {
                throw new \RuntimeException(
                    "Demo action \"{$action}\" requires state \"{$expected}\", current state is \"{$scenario->currentState}\"."
                );
            }

            $scenario->record($effect($scenario));
            $result = $this->workflow->transition(
                $scenario->organizationId,
                self::ENTITY_TYPE,
                $scenario->uid->toString(),
                $action,
                $actorUserId,
                $actorHasPermission,
                ['demoScenarioUid' => $scenario->uid->toString()],
            );

            if ($result instanceof ApprovalRequest) {
                $scenario->status = 'awaiting_approval';
                $scenario->pendingApprovalUid = $result->uid->toString();
            } else {
                $this->syncFromInstance($scenario, $result);
            }
            $this->scenarios->save($scenario);

            return $scenario;
        });
    }

    /**
     * @param callable(DemoScenario): array<string, string> $effect
     */
    public function approve(string $scenarioUid, int $actorUserId, callable $effect): DemoScenario
    {
        return $this->transaction(function () use ($scenarioUid, $actorUserId, $effect): DemoScenario {
            $scenario = $this->scenarios->require($scenarioUid);
            if ($scenario->pendingApprovalUid === null) {
                throw new \RuntimeException('The demo scenario has no pending approval.');
            }

            $instance = $this->workflow->approve($scenario->pendingApprovalUid, $actorUserId);
            $scenario->record($effect($scenario));
            $this->syncFromInstance($scenario, $instance);
            $this->scenarios->save($scenario);

            return $scenario;
        });
    }

    public function reject(string $scenarioUid, int $actorUserId, string $reason): DemoScenario
    {
        return $this->transaction(function () use ($scenarioUid, $actorUserId, $reason): DemoScenario {
            $scenario = $this->scenarios->require($scenarioUid);
            if ($scenario->pendingApprovalUid === null) {
                throw new \RuntimeException('The demo scenario has no pending approval.');
            }

            $this->workflow->reject($scenario->pendingApprovalUid, $actorUserId, $reason);
            $scenario->status = 'active';
            $scenario->pendingApprovalUid = null;
            $scenario->lastError = 'Approval rejected: ' . $reason;
            $scenario->updatedAt = new \DateTimeImmutable();
            $this->scenarios->save($scenario);

            return $scenario;
        });
    }

    public function nextAction(DemoScenario $scenario): ?string
    {
        foreach (self::TRANSITIONS as $action => $transition) {
            if ($transition['from'] === $scenario->currentState) {
                return $action;
            }
        }

        return null;
    }

    private function ensureWorkflow(string $organizationUid, ?int $createdBy): string
    {
        $definition = $this->definitions->findByKey($organizationUid, self::WORKFLOW_KEY);
        if ($definition === null) {
            $definition = $this->definitionService->defineWorkflow(
                $organizationUid,
                self::WORKFLOW_KEY,
                self::ENTITY_TYPE,
                'Demo: order to cash',
                'intake',
                ['intake', 'proposal', 'approval', 'delivery', 'billing', 'completed'],
                $createdBy,
            );
        }

        $existing = [];
        foreach ($this->transitions->forDefinition($definition->uid->toString()) as $transition) {
            $existing[$transition->actionKey] = true;
        }
        foreach (self::TRANSITIONS as $action => $transition) {
            if (!isset($existing[$action])) {
                $this->definitionService->addTransition(
                    $definition->uid->toString(),
                    $action,
                    $transition['from'],
                    $transition['to'],
                    requiredPermission: 'kontor-demo-run',
                    requiresApproval: $transition['approval'],
                );
            }
        }

        return $definition->uid->toString();
    }

    private function syncFromInstance(DemoScenario $scenario, WorkflowInstance $instance): void
    {
        $scenario->currentState = $instance->currentState;
        $scenario->status = $instance->currentState === 'completed' ? 'completed' : 'active';
        $scenario->pendingApprovalUid = null;
        $scenario->lastError = null;
        $scenario->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
