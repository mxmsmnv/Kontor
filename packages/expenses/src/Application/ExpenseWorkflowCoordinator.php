<?php

declare(strict_types=1);

namespace Kontor\Expenses\Application;

use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\Workflow\Application\WorkflowDefinitionService;
use Kontor\Workflow\Application\WorkflowEngine;
use Kontor\Workflow\Domain\ApprovalRequest;
use Kontor\Workflow\Domain\HistoryEntry;
use Kontor\Workflow\Domain\WorkflowDefinition;
use Kontor\Workflow\Infrastructure\Persistence\DefinitionRepository;
use Kontor\Workflow\Infrastructure\Persistence\InstanceRepository;
use RuntimeException;

final class ExpenseWorkflowCoordinator
{
    private const WORKFLOW_KEY = 'expenses.standard';
    private const ENTITY_TYPE = 'expense';

    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly ExpenseWorkflowService $expenseWorkflow,
        private readonly DefinitionRepository $definitions,
        private readonly WorkflowDefinitionService $definitionService,
        private readonly InstanceRepository $instances,
        private readonly WorkflowEngine $engine,
    ) {
    }

    public function perform(
        string $expenseUid,
        string $action,
        int $actorUserId,
        string $reason = '',
    ): Expense {
        $expense = $this->expenses->require($expenseUid);
        if ($action === 'reject' && trim($reason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        $this->synchronize($expense, $actorUserId);
        $this->transition($expense, $action, $actorUserId, $reason);

        return match ($action) {
            'submit' => $this->expenseWorkflow->submit($expenseUid, $actorUserId),
            'approve' => $this->expenseWorkflow->approve($expenseUid, $actorUserId),
            'reject' => $this->expenseWorkflow->reject($expenseUid, $actorUserId, $reason),
            'reimburse' => $this->expenseWorkflow->reimburse($expenseUid, $actorUserId),
            'cancel' => $this->expenseWorkflow->cancel($expenseUid),
            default => throw new \InvalidArgumentException("\"{$action}\" is not an expense workflow action."),
        };
    }

    public function currentState(Expense $expense): ?string
    {
        $instance = $this->instances->findForEntity(
            $expense->organizationId,
            self::ENTITY_TYPE,
            $expense->uid->toString(),
        );

        return $instance?->currentState;
    }

    /**
     * @return HistoryEntry[]
     */
    public function history(Expense $expense): array
    {
        return $this->engine->history(self::ENTITY_TYPE, $expense->uid->toString());
    }

    private function synchronize(Expense $expense, int $actorUserId): void
    {
        $definition = $this->ensureDefinition($expense->organizationId, $actorUserId);
        $instance = $this->instances->findForEntity(
            $expense->organizationId,
            self::ENTITY_TYPE,
            $expense->uid->toString(),
        ) ?? $this->engine->start(
            $expense->organizationId,
            $definition->uid->toString(),
            self::ENTITY_TYPE,
            $expense->uid->toString(),
        );

        while ($instance->currentState !== $expense->status) {
            $action = match ($instance->currentState) {
                'draft' => $expense->status === 'cancelled' ? 'cancel' : 'submit',
                'submitted' => match ($expense->status) {
                    'approved', 'reimbursed' => 'approve',
                    'rejected' => 'reject',
                    'cancelled' => 'cancel',
                    default => null,
                },
                'approved' => $expense->status === 'reimbursed' ? 'reimburse' : null,
                default => null,
            };
            if ($action === null) {
                throw new RuntimeException(
                    "Expense status \"{$expense->status}\" cannot be synchronized from workflow state \"{$instance->currentState}\"."
                );
            }
            $instance = $this->transition($expense, $action, $actorUserId, 'Imported existing expense state.');
        }
    }

    private function transition(
        Expense $expense,
        string $action,
        int $actorUserId,
        string $reason = '',
    ): \Kontor\Workflow\Domain\WorkflowInstance {
        $result = $this->engine->transition(
            $expense->organizationId,
            self::ENTITY_TYPE,
            $expense->uid->toString(),
            $action,
            $actorUserId,
            actorHasPermission: true,
            metadata: array_filter([
                'component' => 'expenses',
                'reason' => trim($reason) !== '' ? trim($reason) : null,
            ]),
        );

        if ($result instanceof ApprovalRequest) {
            return $this->engine->approve($result->uid->toString(), $actorUserId);
        }

        return $result;
    }

    private function ensureDefinition(string $organizationUid, int $createdBy): WorkflowDefinition
    {
        $existing = $this->definitions->findByKey($organizationUid, self::WORKFLOW_KEY);
        if ($existing !== null) {
            return $existing;
        }

        $definition = $this->definitionService->defineWorkflow(
            $organizationUid,
            self::WORKFLOW_KEY,
            self::ENTITY_TYPE,
            'Standard expense approval',
            'draft',
            ['draft', 'submitted', 'approved', 'rejected', 'reimbursed', 'cancelled'],
            $createdBy,
        );
        foreach (
            [
                ['submit', 'draft', 'submitted', 'kontor-expenses-expense-submit', false],
                ['cancel', 'draft', 'cancelled', 'kontor-expenses-expense-edit-draft', false],
                ['approve', 'submitted', 'approved', 'kontor-expenses-expense-approve', true],
                ['reject', 'submitted', 'rejected', 'kontor-expenses-expense-approve', false],
                ['cancel', 'submitted', 'cancelled', 'kontor-expenses-expense-edit-draft', false],
                ['reimburse', 'approved', 'reimbursed', 'kontor-expenses-expense-reimburse', false],
            ] as [$action, $from, $to, $permission, $requiresApproval]
        ) {
            $this->definitionService->addTransition(
                $definition->uid->toString(),
                $action,
                $from,
                $to,
                $permission,
                $requiresApproval,
            );
        }

        return $definition;
    }
}
