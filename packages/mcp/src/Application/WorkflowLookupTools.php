<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyService;
use Kontor\Automation\Domain\AutomationRule;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Workflow\Domain\ApprovalRequest;
use Kontor\Workflow\Domain\HistoryEntry;
use Kontor\Workflow\Domain\WorkflowDefinition;
use Kontor\Workflow\Domain\WorkflowInstance;
use PDO;
use ProcessWire\KontorWorkflow;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait WorkflowLookupTools
{
    private function workflowLookupTools(): array
    {
        $modules = $this->wire()->modules;
        $tools = [];
        if ($modules->isInstalled('KontorWorkflow')) {
            $tools[] = $this->tool(
                'kontor_workflows_list', 'List Kontor workflows',
                'List up to 50 workflow definitions in the active organization.',
                $this->mcpWorkflowsList(...),
            );
            array_push($tools, ...$this->workflowInstanceTools());
        }
        if ($modules->isInstalled('KontorAutomation')) {
            $tools[] = $this->tool(
                'kontor_automation_rules_list', 'List Kontor automation rules',
                'List up to 50 automation rule summaries in the active organization.',
                $this->mcpAutomationRulesList(...),
            );
        }
        return $tools;
    }

    public function mcpWorkflowsList(): array
    {
        $organization = $this->mcpWorkflowOrganization('kontor-workflow-definition-view');
        $module = $this->wire()->modules->get('KontorWorkflow');

        return ['items' => array_map(static fn (WorkflowDefinition $definition): array => [
            'uid' => $definition->uid->toString(),
            'key' => $definition->workflowKey,
            'entity_type' => $definition->entityType,
            'name' => $definition->name,
            'initial_state' => $definition->initialState,
            'status' => $definition->status,
        ], $module->definitionRepository()->forOrganization($organization, 50))];
    }

    public function mcpAutomationRulesList(): array
    {
        $organization = $this->mcpWorkflowOrganization('kontor-automation-rule-view');
        $module = $this->wire()->modules->get('KontorAutomation');

        return ['items' => array_map(static fn (AutomationRule $rule): array => [
            'uid' => $rule->uid->toString(),
            'name' => $rule->name,
            'trigger_event' => $rule->triggerEvent,
            'status' => $rule->status,
        ], $module->ruleRepository()->forOrganization($organization, 50))];
    }

    private function workflowInstanceTools(): array
    {
        $uid = $this->uidSchema();
        $key = $this->idempotencySchema();
        $offset = ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000];
        return [
            $this->tool('kontor_workflow_instance_get', 'Read a workflow instance',
                'Look up the workflow for a visible business record and its permitted next actions.',
                $this->mcpWorkflowInstanceGet(...), 'read', [
                    'entity_type' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_.-]{0,127}$'],
                    'entity_uid' => $uid,
                ], ['entity_type', 'entity_uid']),
            $this->tool('kontor_workflow_history_list', 'Read workflow transition history',
                'List scoped transition history for an accessible workflow instance.',
                $this->mcpWorkflowHistoryList(...), 'read', [
                    'instance_uid' => $uid, 'offset' => $offset,
                ], ['instance_uid']),
            $this->tool('kontor_workflow_approvals_list', 'List pending workflow approvals',
                'List pending approvals for records visible to the linked approver.',
                $this->mcpWorkflowApprovalsList(...), 'read', ['offset' => $offset]),
            $this->tool('kontor_workflow_instance_start', 'Start a workflow instance',
                'Start an active workflow definition for an accessible business record.',
                $this->mcpWorkflowInstanceStart(...), 'draft', [
                    'definition_uid' => $uid, 'entity_uid' => $uid, 'idempotency_key' => $key,
                ], ['definition_uid', 'entity_uid', 'idempotency_key']),
            $this->tool('kontor_workflow_transition', 'Apply a reviewed workflow transition',
                'Perform a permitted transition or request approval after user review.',
                $this->mcpWorkflowTransition(...), 'draft', [
                    'instance_uid' => $uid,
                    'action_key' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_.-]{0,99}$'],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_WORKFLOW_TRANSITION'],
                ], ['instance_uid', 'action_key', 'idempotency_key', 'confirmation']),
            $this->tool('kontor_workflow_approval_decide', 'Approve or reject a reviewed transition',
                'Decide a pending workflow approval for an accessible record after user review.',
                $this->mcpWorkflowApprovalDecide(...), 'draft', [
                    'request_uid' => $uid,
                    'action' => ['type' => 'string', 'enum' => ['approve', 'reject']],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'DECIDE_WORKFLOW_APPROVAL'],
                    'reason' => ['type' => 'string', 'maxLength' => 255],
                ], ['request_uid', 'action', 'idempotency_key', 'confirmation'], destructive: true),
        ];
    }

    public function mcpWorkflowInstanceGet(string $entity_type, string $entity_uid): array
    {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-definition-view');
        $organizationUid = $this->workflowMcpOrganizationUid();
        $this->requireWorkflowEntity($actor, $organizationUid, $entity_type, $entity_uid);
        $module = $this->workflowMcpModule();
        $instance = $module->instanceRepository()->findForEntity($organizationUid, $entity_type, $entity_uid);
        if ($instance === null) {
            throw new Wire404Exception('Workflow instance was not found.');
        }
        $transitions = $module->transitionRepository()->fromState($instance->definitionUid, $instance->currentState);
        $available = [];
        if ($this->workflowMcpCan($actor, 'kontor-workflow-transition')) {
            foreach ($transitions as $transition) {
                if ($transition->requiredPermission === null || $this->workflowMcpCan($actor, $transition->requiredPermission)) {
                    $available[] = [
                        'action_key' => $transition->actionKey, 'to_state' => $transition->toState,
                        'requires_approval' => $transition->requiresApproval,
                    ];
                }
                if (count($available) === 50) {
                    break;
                }
            }
        }

        return $this->workflowMcpInstanceSummary($instance) + ['actions' => $available];
    }

    public function mcpWorkflowHistoryList(string $instance_uid, int $offset = 0): array
    {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-definition-view', 'kontor-workflow-history-view');
        $this->validateWorkflowMcpOffset($offset);
        $organizationUid = $this->workflowMcpOrganizationUid();
        $module = $this->workflowMcpModule();
        $instance = $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $instance_uid);
        $history = $module->historyRepository()->pageForEntity($organizationUid, $instance->entityType, $instance->entityUid, $offset);

        return [
            'items' => array_map(static fn (HistoryEntry $entry): array => [
                'action_key' => $entry->actionKey, 'from_state' => $entry->fromState, 'to_state' => $entry->toState,
                'occurred_at' => $entry->occurredAt->format(DATE_ATOM),
            ], array_slice($history, 0, 50)),
            'nextOffset' => count($history) > 50 && $offset < 10000 ? $offset + 50 : null,
        ];
    }

    public function mcpWorkflowApprovalsList(int $offset = 0): array
    {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-definition-view', 'kontor-workflow-approve');
        $this->validateWorkflowMcpOffset($offset);
        $organizationUid = $this->workflowMcpOrganizationUid();
        $module = $this->workflowMcpModule();
        $pending = $module->approvalRequestRepository()->pendingFor($organizationUid, 51, $offset);
        $items = [];
        foreach (array_slice($pending, 0, 50) as $request) {
            try {
                $instance = $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $request->instanceUid);
            } catch (Wire404Exception|WirePermissionException) {
                continue;
            }
            $items[] = $this->workflowMcpInstanceSummary($instance) + [
                'request_uid' => $request->uid->toString(),
                'action_key' => $request->actionKey, 'from_state' => $request->fromState, 'to_state' => $request->toState,
            ];
        }

        return ['items' => $items, 'nextOffset' => count($pending) > 50 && $offset < 10000 ? $offset + 50 : null];
    }

    public function mcpWorkflowInstanceStart(string $definition_uid, string $entity_uid, string $idempotency_key): array
    {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-transition');
        $this->validateWorkflowMcpUid($definition_uid);
        $module = $this->workflowMcpModule();
        $organizationUid = $this->workflowMcpOrganizationUid();
        $definition = $module->definitionRepository()->find($definition_uid);
        if ($definition === null || !hash_equals($organizationUid, $definition->organizationId) || $definition->status !== 'active') {
            throw new Wire404Exception('Active workflow definition was not found.');
        }
        $this->requireWorkflowEntity($actor, $organizationUid, $definition->entityType, $entity_uid);

        return $this->writeWorkflowMcpAction($actor, $organizationUid, $idempotency_key, ['start', $definition_uid, $entity_uid],
            function () use ($module, $organizationUid, $definition, $definition_uid, $entity_uid): array {
                $instance = $module->engine()->start($organizationUid, $definition_uid, $definition->entityType, $entity_uid);
                return ['instance', $instance->uid->toString(), 'started', $this->workflowMcpInstanceSummary($instance)];
            });
    }

    public function mcpWorkflowTransition(string $instance_uid, string $action_key, string $idempotency_key, string $confirmation): array
    {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-transition');
        if ($confirmation !== 'APPLY_WORKFLOW_TRANSITION' || preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $action_key) !== 1) {
            throw new WireException('Confirm a valid workflow action.');
        }
        $module = $this->workflowMcpModule();
        $organizationUid = $this->workflowMcpOrganizationUid();
        $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $instance_uid);

        return $this->writeWorkflowMcpAction($actor, $organizationUid, $idempotency_key, ['transition', $instance_uid, $action_key],
            function () use ($module, $actor, $organizationUid, $instance_uid, $action_key): array {
                $instance = $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $instance_uid, true);
                $transition = $module->transitionRepository()->findByFromStateAndAction(
                    $instance->definitionUid, $instance->currentState, $action_key,
                );
                if ($transition === null) {
                    throw new WireException('That workflow transition is no longer available.');
                }
                if ($transition->requiredPermission !== null && !$this->workflowMcpCan($actor, $transition->requiredPermission)) {
                    throw new WirePermissionException('The linked user cannot perform this workflow transition.');
                }
                $result = $module->engine()->transition($organizationUid, $instance->entityType, $instance->entityUid,
                    $action_key, (int) $actor->id, true);
                if ($result instanceof ApprovalRequest) {
                    return ['instance', $instance_uid, 'approval_requested', [
                        'instance_uid' => $instance_uid, 'approval_request_uid' => $result->uid->toString(), 'state' => $instance->currentState,
                    ]];
                }
                return ['instance', $instance_uid, 'transitioned', $this->workflowMcpInstanceSummary($result)];
            }, fn () => $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $instance_uid, true));
    }

    public function mcpWorkflowApprovalDecide(
        string $request_uid,
        string $action,
        string $idempotency_key,
        string $confirmation,
        ?string $reason = null,
    ): array {
        $actor = $this->requireWorkflowMcpActor('kontor-workflow-approve');
        $this->validateWorkflowMcpUid($request_uid);
        $reason = $reason === null ? null : trim(strip_tags($reason));
        if ($confirmation !== 'DECIDE_WORKFLOW_APPROVAL' || !in_array($action, ['approve', 'reject'], true)
            || ($action === 'reject' && ($reason === null || $reason === '' || mb_strlen($reason) > 255))
            || ($action === 'approve' && $reason !== null)) {
            throw new WireException('Confirm approve or reject and provide a reason only when rejecting.');
        }
        $module = $this->workflowMcpModule();
        $organizationUid = $this->workflowMcpOrganizationUid();
        $request = $module->approvalRequestRepository()->find($request_uid);
        if ($request === null || !hash_equals($organizationUid, $request->organizationId)) {
            throw new Wire404Exception('Workflow approval was not found.');
        }
        $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $request->instanceUid);

        return $this->writeWorkflowMcpAction($actor, $organizationUid, $idempotency_key, ['decision', $request_uid, $action, $reason],
            function () use ($module, $actor, $organizationUid, $request, $request_uid, $action, $reason): array {
                $instance = $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $request->instanceUid, true);
                $locked = $module->approvalRequestRepository()->find($request_uid, true);
                if ($locked === null || !hash_equals($organizationUid, $locked->organizationId)
                    || !hash_equals($instance->uid->toString(), $locked->instanceUid) || !$locked->isPending()) {
                    throw new WireException('That workflow approval is no longer pending.');
                }
                if ($action === 'approve') {
                    $transition = $module->transitionRepository()->findByFromStateAndAction(
                        $instance->definitionUid, $locked->fromState, $locked->actionKey,
                    );
                    if ($transition === null || !$transition->requiresApproval || $transition->toState !== $locked->toState
                        || $instance->currentState !== $locked->fromState) {
                        throw new WireException('The workflow changed after this approval was requested.');
                    }
                    $instance = $module->engine()->approve($request_uid, (int) $actor->id);
                } else {
                    $module->engine()->reject($request_uid, (int) $actor->id, $reason);
                }
                return ['approval', $request_uid, $action, [
                    'request_uid' => $request_uid, 'instance_uid' => $instance->uid->toString(),
                    'status' => $action === 'approve' ? 'approved' : 'rejected', 'state' => $instance->currentState,
                ]];
            }, fn () => $this->requireWorkflowMcpInstance($module, $actor, $organizationUid, $request->instanceUid, true));
    }

    private function requireWorkflowMcpInstance(
        KontorWorkflow $module,
        User $actor,
        string $organizationUid,
        string $uid,
        bool $forUpdate = false,
    ): WorkflowInstance {
        $this->validateWorkflowMcpUid($uid);
        $instance = $module->instanceRepository()->find($uid, $forUpdate);
        if ($instance === null || !hash_equals($organizationUid, $instance->organizationId)) {
            throw new Wire404Exception('Workflow instance was not found.');
        }
        $this->requireWorkflowEntity($actor, $organizationUid, $instance->entityType, $instance->entityUid);
        return $instance;
    }

    private function requireWorkflowEntity(User $actor, string $organizationUid, string $entityType, string $entityUid): void
    {
        $this->validateWorkflowMcpUid($entityUid);
        $modules = $this->wire()->modules;
        if ($entityType === 'task') {
            if (!$modules->isInstalled('KontorTasks')) {
                throw new Wire404Exception('Workflow record was not found.');
            }
            $tasks = $modules->get('KontorTasks');
            $task = $tasks->taskRepository()->find($entityUid);
            if ($task === null || !hash_equals($organizationUid, $task->organizationId) || !$tasks->accessFor($actor)->allows($task)) {
                throw new Wire404Exception('Workflow record was not found.');
            }
            return;
        }
        $entity = match ($entityType) {
            'contact' => ['KontorContacts', 'contactRepository', 'kontor-contacts-contact-view'],
            'company' => ['KontorContacts', 'companyRepository', 'kontor-contacts-company-view'],
            'crm_lead' => ['KontorCRM', 'leadRepository', 'kontor-crm-lead-view'],
            'crm_deal' => ['KontorCRM', 'dealRepository', 'kontor-crm-deal-view'],
            'sales_quotation' => ['KontorSales', 'quotationRepository', 'kontor-sales-quotation-view'],
            'sales_order' => ['KontorSales', 'orderRepository', 'kontor-sales-order-view'],
            'project' => ['KontorProjects', 'projectRepository', 'kontor-projects-project-view'],
            'expense' => ['KontorExpenses', 'expenseRepository', 'kontor-expenses-expense-view'],
            'purchase_order' => ['KontorPurchasing', 'purchaseOrderRepository', 'kontor-purchasing-po-view'],
            'invoice' => ['KontorInvoices', 'invoiceRepository', 'kontor-invoices-invoice-view'],
            'payment' => ['KontorPayments', 'paymentRepository', 'kontor-payments-payment-view'],
            default => null,
        };
        if ($entity === null) {
            if (!$actor->isSuperuser()) {
                throw new WirePermissionException('The linked user cannot access this custom workflow record type.');
            }
            return;
        }
        [$component, $method, $permission] = $entity;
        if (!$this->workflowMcpCan($actor, $permission) || !$modules->isInstalled($component)) {
            throw new Wire404Exception('Workflow record was not found.');
        }
        $record = $modules->get($component)->{$method}()->find($entityUid);
        if ($record === null || !hash_equals($organizationUid, $record->organizationId)) {
            throw new Wire404Exception('Workflow record was not found.');
        }
    }

    private function writeWorkflowMcpAction(
        User $actor,
        string $organizationUid,
        string $key,
        array $args,
        callable $operation,
        ?callable $lock = null,
    ): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('Use an idempotency key of 8 to 160 safe characters.');
        }
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $pdo = $container->get(PDO::class);
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $lock?->__invoke();
            $response = $idempotency->remember(
                $organizationUid,
                'mcp:workflow:' . $actor->id . ':' . hash('sha256', $key),
                $args,
                static function () use ($actor, $organizationUid, $container, $operation): array {
                    [$entity, $uid, $action, $result] = $operation();
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organizationUid),
                        'workflow', $entity, $uid, $action, 'user', (string) $actor->id, metadata: ['transport' => 'mcp'],
                    );
                    return ['status' => 200, 'body' => $result];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            return $this->safe(static fn () => throw $error);
        }
    }

    private function workflowMcpInstanceSummary(WorkflowInstance $instance): array
    {
        return [
            'instance_uid' => $instance->uid->toString(), 'definition_uid' => $instance->definitionUid,
            'entity_type' => $instance->entityType, 'entity_uid' => $instance->entityUid, 'state' => $instance->currentState,
        ];
    }

    private function validateWorkflowMcpUid(string $uid): void
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('A valid record UID is required.');
        }
    }

    private function validateWorkflowMcpOffset(int $offset): void
    {
        if ($offset < 0 || $offset > 10000) {
            throw new WireException('Invalid offset.');
        }
    }

    private function workflowMcpModule(): KontorWorkflow
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorWorkflow')) {
            throw new Wire404Exception('Kontor Workflow is unavailable.');
        }
        return $modules->get('KontorWorkflow');
    }

    private function requireWorkflowMcpActor(string ...$permissions): User
    {
        $actor = $this->mcpActor();
        if ($actor === null) {
            throw new WirePermissionException('An authenticated Kontor MCP user is required.');
        }
        foreach ($permissions as $permission) {
            if (!$this->workflowMcpCan($actor, $permission)) {
                throw new WirePermissionException('The linked user lacks the required Workspace permission.');
            }
        }
        return $actor;
    }

    private function workflowMcpCan(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function workflowMcpOrganizationUid(): string
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
    }

    private function mcpWorkflowOrganization(string $permission): string
    {
        $this->requireWorkflowMcpActor($permission);
        return $this->workflowMcpOrganizationUid();
    }
}
