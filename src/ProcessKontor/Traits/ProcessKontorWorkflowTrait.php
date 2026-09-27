<?php

namespace ProcessWire;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Note;
use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\Core\Application\AuditChangePresenter;
use Kontor\Core\Application\AuditCsvExporter;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Application\BackupOverviewBuilder;
use Kontor\Core\Application\ComponentOverviewBuilder;
use Kontor\Core\Application\EntityActionResolver;
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\HealthCheckRunner;
use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Application\NavigationAvailability;
use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Health\CoreHealthCheck;
use Kontor\Core\Infrastructure\Backup\BackupArchiveBuilder;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Invoices\Application\LedgerInvoicePostingService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;
use Kontor\Ledger\Domain\Account;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Domain\Payment;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\Task;
use Kontor\Workflow\Domain\ApprovalRequest;

/**
 * Administrative feature slice composed by ProcessKontor.
 *
 * @internal
 */
trait ProcessKontorWorkflowTrait
{

    public function ___executeWorkflows(): string
    {
        $this->requireWorkflow();
        $this->requirePermission('kontor-workflow-definition-view');
        $module = $this->workflowModule();
        $pending = $module->approvalRequestRepository()->pendingFor($this->organizationUid());
        $approvalInstances = [];
        foreach ($pending as $request) {
            $approvalInstances[$request->uid->toString()] = $module->instanceRepository()
                ->require($request->instanceUid);
        }
        $this->setPageTitle($this->_('Kontor · Workflows'));

        return $this->renderTemplate('workflows', [
            'definitions' => $module->definitionRepository()->forOrganization(
                $this->organizationUid()
            ),
            'pendingApprovals' => $pending,
            'approvalInstances' => $approvalInstances,
            'canManage' => $this->can('kontor-workflow-definition-manage'),
            'canApprove' => $this->can('kontor-workflow-approve'),
        ]);
    }

    public function ___executeDemo(): string
    {
        $this->requireDemo();
        $this->requirePermission('kontor-demo-view');
        $module = $this->demoModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $scenarios = $module->scenarioRepository()->forOrganization($this->organizationUid());
        $scenario = $id !== ''
            ? $module->scenarioRepository()->require($id)
            : ($scenarios[0] ?? null);
        if ($scenario !== null) {
            $this->requireSameOrganization($scenario->organizationId);
        }
        $pendingApproval = $scenario?->pendingApprovalUid !== null
            ? $this->workflowModule()->approvalRequestRepository()->require(
                $scenario->pendingApprovalUid
            )
            : null;
        $history = $scenario !== null
            ? $this->workflowModule()->engine()->history(
                'demo_scenario',
                $scenario->uid->toString(),
            )
            : [];
        $this->setPageTitle($this->_('Kontor · Connected demo'));

        return $this->renderTemplate('demo', [
            'scenario' => $scenario,
            'scenarios' => $scenarios,
            'pendingApproval' => $pendingApproval,
            'history' => $history,
            'nextAction' => $scenario !== null ? $module->scenarios()->nextAction($scenario) : null,
            'componentChecks' => $module->componentChecks(),
            'canRun' => $this->can('kontor-demo-run'),
            'canApprove' => $this->can('kontor-demo-approve'),
            'entityLinks' => $scenario !== null ? $this->demoEntityLinks($scenario->entities) : [],
        ]);
    }

    public function ___executeDemoStart(): void
    {
        $this->requirePost();
        $this->requireDemo();
        $this->requirePermission('kontor-demo-run');

        try {
            $scenario = $this->demoModule()->startScenario(
                $this->organizationUid(),
                (int) $this->wire()->user->id,
            );
            $this->audit(
                'demo',
                'demo_scenario',
                $scenario->uid->toString(),
                'started',
                current: ['state' => $scenario->currentState, 'entities' => $scenario->entities],
            );
            $this->message($this->_('Connected demo started with real customer, CRM and task records.'));
            $this->wire()->session->redirect(
                '../demo/?id=' . rawurlencode($scenario->uid->toString())
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            $this->wire()->session->redirect('../demo/');
        }
    }

    public function ___executeDemoAction(): void
    {
        $this->requirePost();
        $this->requireDemo();
        $scenarioUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('scenario_uid')
        );
        $scenario = $this->demoModule()->scenarioRepository()->require($scenarioUid);
        $this->requireSameOrganization($scenario->organizationId);
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['prepare_proposal', 'request_approval', 'approve', 'reject', 'start_delivery', 'issue_invoice', 'settle']
        );
        $this->requireAction($action, [
            'prepare_proposal', 'request_approval', 'approve', 'reject',
            'start_delivery', 'issue_invoice', 'settle',
        ]);
        $this->requirePermission(in_array($action, ['approve', 'reject'], true)
            ? 'kontor-demo-approve'
            : 'kontor-demo-run');

        try {
            if ($action === 'approve') {
                $updated = $this->demoModule()->approveScenario(
                    $scenarioUid,
                    (int) $this->wire()->user->id,
                );
            } elseif ($action === 'reject') {
                $reason = trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('reason')
                ));
                if ($reason === '') {
                    throw new WireException($this->_('A rejection reason is required.'));
                }
                $updated = $this->demoModule()->scenarios()->reject(
                    $scenarioUid,
                    (int) $this->wire()->user->id,
                    $reason,
                );
            } else {
                $updated = $this->demoModule()->advanceScenario(
                    $scenarioUid,
                    $action,
                    (int) $this->wire()->user->id,
                );
            }
            $this->audit(
                'demo',
                'demo_scenario',
                $scenarioUid,
                $action,
                current: ['state' => $updated->currentState, 'entities' => $updated->entities],
            );
            $this->message(sprintf(
                $this->_('Demo action completed. Current state: %s.'),
                $updated->currentState,
            ));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        }

        $this->wire()->session->redirect(
            '../demo/?id=' . rawurlencode($scenarioUid)
        );
    }

    public function ___executeWorkflow(): string
    {
        $this->requireWorkflow();
        $module = $this->workflowModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $definition = $id !== '' ? $module->definitionRepository()->require($id) : null;
        if ($definition !== null) {
            $this->requireSameOrganization($definition->organizationId);
            $this->requirePermission('kontor-workflow-definition-view');
        } else {
            $this->requirePermission('kontor-workflow-definition-manage');
        }
        $entityTypeOptions = [];
        if ($this->contactsReady()) {
            $entityTypeOptions += ['contact' => $this->_('Contact'), 'company' => $this->_('Company')];
        }
        if ($this->crmReady()) {
            $entityTypeOptions += ['crm_lead' => $this->_('CRM lead'), 'crm_deal' => $this->_('CRM deal')];
        }
        if ($this->salesReady()) {
            $entityTypeOptions += ['sales_quotation' => $this->_('Sales quotation'), 'sales_order' => $this->_('Sales order')];
        }
        if ($this->tasksReady()) {
            $entityTypeOptions['task'] = $this->_('Task');
        }
        if ($this->projectsReady()) {
            $entityTypeOptions['project'] = $this->_('Project');
        }
        if ($this->expensesReady()) {
            $entityTypeOptions['expense'] = $this->_('Expense');
        }
        if ($this->purchasingReady()) {
            $entityTypeOptions['purchase_order'] = $this->_('Purchase order');
        }
        if ($this->invoicesReady()) {
            $entityTypeOptions['invoice'] = $this->_('Invoice');
        }
        if ($this->paymentsReady()) {
            $entityTypeOptions['payment'] = $this->_('Payment');
        }
        if ($this->demoReady()) {
            $entityTypeOptions['demo_scenario'] = $this->_('Demo scenario');
        }
        $entityTypeOptions['custom'] = $this->_('Other business record');

        $values = [
            'workflowKey' => '',
            'entityType' => array_key_first($entityTypeOptions) ?? 'custom',
            'entityTypeChoice' => array_key_first($entityTypeOptions) ?? 'custom',
            'customEntityType' => '',
            'name' => '',
            'initialState' => 'Draft',
            'states' => "Draft\nIn review\nApproved\nRejected",
        ];
        $error = '';

        if ($definition === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $name = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('name')
            ));
            $selectedEntityType = $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('entity_type'),
                array_keys($entityTypeOptions),
            ) ?? '';
            $customEntityType = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('custom_entity_type')
            ));
            $normalizeIdentifier = static function (string $value, int $limit = 64): string {
                $value = mb_strtolower(trim($value));
                $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';

                return mb_substr(trim($value, '_'), 0, $limit);
            };
            $resolvedEntityType = $selectedEntityType === 'custom'
                ? $normalizeIdentifier($customEntityType, 128)
                : $selectedEntityType;
            $workflowKey = $normalizeIdentifier($resolvedEntityType . ' ' . $name);
            if ($workflowKey === '') {
                $workflowKey = 'workflow_' . substr(hash('sha256', $name), 0, 12);
            }
            $rawStates = (string) $this->wire()->input->post('states');
            $values = [
                'workflowKey' => $workflowKey,
                'entityType' => $resolvedEntityType,
                'entityTypeChoice' => $selectedEntityType,
                'customEntityType' => $customEntityType,
                'name' => $name,
                'initialState' => $normalizeIdentifier((string) $this->wire()->input->post('initial_state')),
                'states' => $rawStates,
            ];
            $states = array_values(array_unique(array_filter(array_map(
                static fn (string $state): string => $normalizeIdentifier($state),
                preg_split('/[,\r\n]+/', $rawStates) ?: [],
            ))));
            if ($values['name'] === '') {
                $error = $this->_('Workflow name is required.');
            } elseif (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $values['entityType']) !== 1) {
                $error = $this->_('Choose the kind of business record this workflow controls.');
            } elseif ($states === [] || array_filter(
                $states,
                static fn (string $state): bool => preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $state) !== 1,
            ) !== []) {
                $error = $this->_('Add one or more stage names using letters and numbers.');
            } elseif (!in_array($values['initialState'], $states, true)) {
                $error = $this->_('The starting stage must match one of the stages in the list.');
            }
            if ($error === '') {
                try {
                    $definition = $module->definitions()->defineWorkflow(
                        $this->organizationUid(),
                        $values['workflowKey'],
                        $values['entityType'],
                        mb_substr($values['name'], 0, 191),
                        $values['initialState'],
                        $states,
                        (int) $this->wire()->user->id,
                    );
                } catch (\InvalidArgumentException|\PDOException $exception) {
                    $error = $exception instanceof \PDOException && $exception->getCode() === '23000'
                        ? $this->_('A workflow with this name already exists. Choose a more specific name.')
                        : $exception->getMessage();
                }
                if ($error === '') {
                    $this->audit(
                        'workflow',
                        'definition',
                        $definition->uid->toString(),
                        'created',
                        current: ['key' => $definition->workflowKey, 'states' => $definition->states],
                    );
                    $this->message($this->_('Workflow definition created.'));
                    $this->wire()->session->redirect(
                        '../workflow/?id=' . rawurlencode($definition->uid->toString())
                    );
                }
            }
        }

        $this->setPageTitle($definition === null
            ? $this->_('Kontor · New workflow')
            : sprintf($this->_('Kontor · %s'), $definition->name));

        $transitions = $definition !== null
            ? $module->transitionRepository()->forDefinition($definition->uid->toString())
            : [];
        $instances = $definition !== null
            ? $module->instanceRepository()->forDefinition($definition->uid->toString())
            : [];
        $recordTypeLabel = $definition !== null
            ? ($entityTypeOptions[$definition->entityType]
                ?? ucfirst(str_replace(['_', '-', '.'], ' ', $definition->entityType)))
            : '';
        $workflowTargets = [];
        if ($definition?->entityType === 'demo_scenario'
            && $this->demoReady()
            && $this->can('kontor-demo-view')) {
            $startedEntityUids = array_fill_keys(array_map(
                static fn ($instance): string => $instance->entityUid,
                $instances,
            ), true);
            foreach ($this->demoModule()->scenarioRepository()->forOrganization(
                $this->organizationUid(),
                100,
            ) as $scenario) {
                $uid = $scenario->uid->toString();
                $workflowTargets[$uid] = [
                    'label' => $scenario->name,
                    'route' => 'demo/?id=' . rawurlencode($uid),
                    'available' => !isset($startedEntityUids[$uid]),
                ];
            }
        }
        $instanceViews = [];
        foreach ($instances as $instance) {
            $target = $workflowTargets[$instance->entityUid] ?? null;
            $instanceViews[$instance->uid->toString()] = [
                'label' => is_array($target)
                    ? (string) $target['label']
                    : sprintf($this->_('%s workflow run'), $recordTypeLabel),
                'route' => is_array($target) ? (string) $target['route'] : null,
            ];
        }

        return $this->renderTemplate('workflow', [
            'definition' => $definition,
            'values' => $values,
            'error' => $error,
            'transitions' => $transitions,
            'instances' => $instances,
            'canManage' => $this->can('kontor-workflow-definition-manage'),
            'canTransition' => $this->can('kontor-workflow-transition'),
            'entityTypeOptions' => $entityTypeOptions,
            'recordTypeLabel' => $recordTypeLabel,
            'workflowTargets' => $workflowTargets,
            'instanceViews' => $instanceViews,
        ]);
    }

    public function ___executeWorkflowTransition(): void
    {
        $this->requirePost();
        $this->requireWorkflow();
        $this->requirePermission('kontor-workflow-definition-manage');
        $definition = $this->requireWorkflowDefinitionFromPost();
        $actionKey = mb_strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action_key')
        )));
        $actionKey = preg_replace('/[^a-z0-9]+/u', '_', $actionKey) ?? '';
        $actionKey = mb_substr(trim($actionKey, '_'), 0, 64);
        $fromState = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('from_state')
        ));
        $toState = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('to_state')
        ));
        $permission = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('required_permission')
        ));
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $actionKey) !== 1) {
            $this->error($this->_('Enter an action name using letters and numbers.'));
            $this->redirectToWorkflow($definition->uid->toString());
            return;
        }
        $transition = $this->workflowModule()->definitions()->addTransition(
            $definition->uid->toString(),
            $actionKey,
            $fromState,
            $toState,
            $permission !== '' ? $permission : null,
            (bool) $this->wire()->input->post('requires_approval'),
        );
        $this->audit(
            'workflow',
            'transition',
            $transition->uid->toString(),
            'created',
            current: ['action' => $actionKey, 'from' => $fromState, 'to' => $toState],
        );
        $this->message($this->_('Workflow transition added.'));
        $this->redirectToWorkflow($definition->uid->toString());
    }

    public function ___executeWorkflowInstance(): string
    {
        $this->requireWorkflow();
        $module = $this->workflowModule();
        if ($this->wire()->input->post('submit_start')) {
            $this->requirePost();
            $this->requirePermission('kontor-workflow-transition');
            $definition = $this->requireWorkflowDefinitionFromPost();
            $entityUid = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('entity_uid')
            ));
            if ($entityUid === '') {
                throw new WireException($this->_('Entity UID is required.'));
            }
            $instance = $module->engine()->start(
                $this->organizationUid(),
                $definition->uid->toString(),
                $definition->entityType,
                mb_substr($entityUid, 0, 191),
            );
            $this->audit('workflow', 'instance', $instance->uid->toString(), 'started');
            $this->message($this->_('Workflow instance started.'));
            $this->wire()->session->redirect(
                '../workflow-instance/?id=' . rawurlencode($instance->uid->toString())
            );
        }
        $this->requirePermission('kontor-workflow-definition-view');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $instance = $module->instanceRepository()->require($id);
        $this->requireSameOrganization($instance->organizationId);
        $definition = $module->definitionRepository()->require($instance->definitionUid);
        $transitions = $module->transitionRepository()->fromState(
            $definition->uid->toString(),
            $instance->currentState,
        );
        $this->setPageTitle($this->_('Kontor · Workflow instance'));

        $canViewHistory = $this->can('kontor-workflow-history-view');

        return $this->renderTemplate('workflow-instance', [
            'instance' => $instance,
            'definition' => $definition,
            'transitions' => $transitions,
            'history' => $canViewHistory
                ? $module->engine()->history($instance->entityType, $instance->entityUid)
                : [],
            'canViewHistory' => $canViewHistory,
            'canTransition' => $this->can('kontor-workflow-transition'),
        ]);
    }

    public function ___executeWorkflowInstanceAction(): void
    {
        $this->requirePost();
        $this->requireWorkflow();
        $this->requirePermission('kontor-workflow-transition');
        $module = $this->workflowModule();
        $instanceUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('instance_uid')
        );
        $instance = $module->instanceRepository()->require($instanceUid);
        $this->requireSameOrganization($instance->organizationId);
        $actionKey = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action_key')
        ));
        $transition = $module->transitionRepository()->findByFromStateAndAction(
            $instance->definitionUid,
            $instance->currentState,
            $actionKey,
        );
        if ($transition === null) {
            throw new WireException($this->_('That transition is no longer available.'));
        }
        $result = $module->engine()->transition(
            $this->organizationUid(),
            $instance->entityType,
            $instance->entityUid,
            $actionKey,
            (int) $this->wire()->user->id,
            $transition->requiredPermission === null || $this->can($transition->requiredPermission),
        );
        $this->audit(
            'workflow',
            'instance',
            $instanceUid,
            $result instanceof ApprovalRequest ? 'approval_requested' : 'transitioned',
            current: ['action' => $actionKey],
        );
        $this->message($result instanceof ApprovalRequest
            ? $this->_('Approval requested. The state has not changed yet.')
            : $this->_('Workflow transition completed.'));
        $this->wire()->session->redirect(
            '../workflow-instance/?id=' . rawurlencode($instanceUid)
        );
    }

    public function ___executeWorkflowApprovalAction(): void
    {
        $this->requirePost();
        $this->requireWorkflow();
        $this->requirePermission('kontor-workflow-approve');
        $module = $this->workflowModule();
        $requestUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('request_uid')
        );
        $request = $module->approvalRequestRepository()->require($requestUid);
        $this->requireSameOrganization($request->organizationId);
        $instance = $module->instanceRepository()->require($request->instanceUid);
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['approve', 'reject']
        );
        $this->requireAction($action, ['approve', 'reject']);
        if ($action === 'approve') {
            $module->engine()->approve($requestUid, (int) $this->wire()->user->id);
        } else {
            $reason = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('reason')
            ));
            if ($reason === '') {
                throw new WireException($this->_('A rejection reason is required.'));
            }
            $module->engine()->reject($requestUid, (int) $this->wire()->user->id, $reason);
        }
        $this->audit('workflow', 'approval', $requestUid, $action);
        $this->message($this->_('Approval request decided.'));
        $this->wire()->session->redirect(
            '../workflow-instance/?id=' . rawurlencode($instance->uid->toString())
        );
    }

    public function ___executeAutomations(): string
    {
        $this->requireAutomation();
        $this->requirePermission('kontor-automation-rule-view');
        $module = $this->automationModule();
        $this->setPageTitle($this->_('Kontor · Automations'));
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $selectedStatus = $this->wire()->sanitizer->text((string) $this->wire()->input->get('status'));
        $selectedStatus = in_array($selectedStatus, ['active', 'paused'], true) ? $selectedStatus : '';
        $allRules = $module->ruleRepository()->forOrganization($this->organizationUid());
        $canViewLogs = $this->can('kontor-automation-log-view');
        $allLogs = $canViewLogs
            ? $module->executionLogRepository()->forOrganization($this->organizationUid())
            : [];
        $rules = array_values(array_filter(
            $allRules,
            static function ($rule) use ($query, $selectedStatus): bool {
                if ($selectedStatus !== '' && $rule->status !== $selectedStatus) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }

                return str_contains(
                    mb_strtolower($rule->name . ' ' . $rule->triggerEvent),
                    mb_strtolower($query),
                );
            },
        ));
        $ruleSummaries = [];
        foreach ($allRules as $rule) {
            $ruleUid = $rule->uid->toString();
            $ruleLogs = array_values(array_filter(
                $allLogs,
                static fn ($log): bool => $log->ruleUid === $ruleUid,
            ));
            $lastLog = $ruleLogs[0] ?? null;
            $ruleSummaries[$ruleUid] = [
                'conditions' => count($module->conditionRepository()->forRule($ruleUid)),
                'actions' => count($module->actionRepository()->forRule($ruleUid)),
                'executions' => count($ruleLogs),
                'lastLog' => $lastLog,
            ];
        }

        return $this->renderTemplate('automations', [
            'rules' => $rules,
            'allRules' => $allRules,
            'logs' => array_slice($allLogs, 0, 25),
            'ruleSummaries' => $ruleSummaries,
            'ruleLabels' => array_combine(
                array_map(static fn ($rule): string => $rule->uid->toString(), $allRules),
                array_map(static fn ($rule): string => $rule->name, $allRules),
            ) ?: [],
            'query' => $query,
            'selectedStatus' => $selectedStatus,
            'canManage' => $this->can('kontor-automation-rule-manage'),
            'canViewLogs' => $canViewLogs,
        ]);
    }

    public function ___executeAutomation(): string
    {
        $this->requireAutomation();
        $module = $this->automationModule();
        $triggerEvents = [];
        if ($this->crmReady()) {
            $triggerEvents += [
                'crm.lead.converted' => $this->_('CRM lead converted'),
                'crm.deal.stage_changed' => $this->_('CRM deal stage changed'),
                'crm.deal.won' => $this->_('CRM deal won'),
                'crm.deal.lost' => $this->_('CRM deal lost'),
            ];
        }
        if ($this->mailReady()) {
            $triggerEvents += [
                'mail.received' => $this->_('Mail received'),
                'mail.sent' => $this->_('Mail sent'),
                'mail.delivery_failed' => $this->_('Mail delivery failed'),
            ];
        }
        if ($this->filesReady()) {
            $triggerEvents += [
                'file.uploaded' => $this->_('File uploaded'),
                'file.version_created' => $this->_('New file version created'),
                'file.shared' => $this->_('File shared'),
                'file.archived' => $this->_('File archived'),
                'file.restored' => $this->_('File restored'),
            ];
        }
        if ($this->documentsReady()) {
            $triggerEvents += [
                'document_template.published' => $this->_('Document template published'),
                'document_template.version_published' => $this->_('New document template version published'),
            ];
        }
        if ($this->inventoryReady()) {
            $triggerEvents['inventory.movement.completed'] = $this->_('Inventory movement completed');
        }
        if ($this->queueReady()) {
            $triggerEvents += [
                'queue.job.completed' => $this->_('Background job completed'),
                'queue.job.retrying' => $this->_('Background job retrying'),
                'queue.job.dead' => $this->_('Background job failed permanently'),
            ];
        }
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $rule = $id !== '' ? $module->ruleRepository()->require($id) : null;
        if ($rule !== null) {
            $this->requireSameOrganization($rule->organizationId);
            $this->requirePermission('kontor-automation-rule-view');
        } else {
            $this->requirePermission('kontor-automation-rule-manage');
        }
        $values = ['name' => '', 'triggerEvent' => array_key_first($triggerEvents) ?? 'custom.event.received'];
        $error = '';
        if ($rule === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $customTriggerEvent = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('custom_trigger_event')
            ));
            $values = [
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
                'triggerEvent' => strtolower($customTriggerEvent !== ''
                    ? $customTriggerEvent
                    : $this->wire()->sanitizer->text(
                        (string) $this->wire()->input->post('trigger_event')
                    )),
            ];
            if ($values['name'] === '') {
                $error = $this->_('Rule name is required.');
            } elseif (preg_match('/^[a-z][a-z0-9_.-]{2,190}$/', $values['triggerEvent']) !== 1) {
                $error = $this->_('Trigger event must be a dot-separated lowercase identifier.');
            }
            if ($error === '') {
                $rule = $module->definitions()->defineRule(
                    $this->organizationUid(),
                    mb_substr($values['name'], 0, 191),
                    $values['triggerEvent'],
                    (int) $this->wire()->user->id,
                );
                $this->audit(
                    'automation',
                    'rule',
                    $rule->uid->toString(),
                    'created',
                    current: ['name' => $rule->name, 'triggerEvent' => $rule->triggerEvent],
                );
                $this->message($this->_('Automation rule created.'));
                $this->wire()->session->redirect(
                    '../automation/?id=' . rawurlencode($rule->uid->toString())
                );
            }
        }
        $this->setPageTitle($rule === null
            ? $this->_('Kontor · New automation')
            : sprintf($this->_('Kontor · %s'), $rule->name));

        return $this->renderTemplate('automation', [
            'rule' => $rule,
            'values' => $values,
            'error' => $error,
            'conditions' => $rule !== null
                ? $module->conditionRepository()->forRule($rule->uid->toString())
                : [],
            'actions' => $rule !== null
                ? $module->actionRepository()->forRule($rule->uid->toString())
                : [],
            'logs' => $rule !== null
                ? $module->executionLogRepository()->forRule($rule->uid->toString())
                : [],
            'actionHandlers' => array_keys($module->actionHandlerRegistry()->all()),
            'triggerEvents' => $triggerEvents,
            'canManage' => $this->can('kontor-automation-rule-manage'),
            'canDryRun' => $this->can('kontor-automation-dry-run'),
        ]);
    }

    public function ___executeAutomationCondition(): void
    {
        $this->requirePost();
        $this->requireAutomation();
        $this->requirePermission('kontor-automation-rule-manage');
        $rule = $this->requireAutomationRuleFromPost();
        $field = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('field')
        ));
        $operator = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('operator'),
            \Kontor\Automation\Domain\RuleCondition::OPERATORS,
        );
        $value = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('value')
        ));
        if ($field === '' || $operator === null) {
            throw new WireException($this->_('Condition field and operator are required.'));
        }
        $condition = $this->automationModule()->definitions()->addCondition(
            $rule->uid->toString(),
            $field,
            $operator,
            $value !== '' ? $value : null,
        );
        $this->audit('automation', 'condition', $condition->uid->toString(), 'created');
        $this->message($this->_('Condition added.'));
        $this->redirectToAutomation($rule->uid->toString());
    }

    public function ___executeAutomationAction(): void
    {
        $this->requirePost();
        $this->requireAutomation();
        $this->requirePermission('kontor-automation-rule-manage');
        $rule = $this->requireAutomationRuleFromPost();
        $actionKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action_key')
        );
        if ($actionKey === 'tasks.create') {
            $title = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('task_title')
            ));
            if ($title === '') {
                throw new WireException($this->_('Task title is required.'));
            }
            $priority = $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('task_priority'),
                ['low', 'normal', 'high', 'urgent'],
            ) ?? 'normal';
            $dueInMinutes = max(0, (int) $this->wire()->input->post('due_in_minutes'));
            $description = trim($this->wire()->sanitizer->textarea(
                (string) $this->wire()->input->post('task_description')
            ));
            $params = array_filter([
                'title' => mb_substr($title, 0, 255),
                'description' => $description !== '' ? mb_substr($description, 0, 2000) : null,
                'priority' => $priority,
                'dueInMinutes' => $dueInMinutes > 0 ? $dueInMinutes : null,
                'linkToTrigger' => (bool) $this->wire()->input->post('link_to_trigger'),
            ], static fn (mixed $value): bool => $value !== null);
        } elseif ($actionKey === 'log') {
            $params = [];
        } else {
            $paramsJson = trim((string) $this->wire()->input->post('params_json'));
            try {
                $params = $paramsJson !== ''
                    ? json_decode($paramsJson, associative: true, flags: JSON_THROW_ON_ERROR)
                    : [];
            } catch (\JsonException $exception) {
                throw new WireException($this->_('Action parameters must be valid JSON.'));
            }
            if (!is_array($params)) {
                throw new WireException($this->_('Action parameters must be a JSON object.'));
            }
        }
        $action = $this->automationModule()->definitions()->addAction(
            $rule->uid->toString(),
            $actionKey,
            $params,
        );
        $this->audit('automation', 'action', $action->uid->toString(), 'created');
        $this->message($this->_('Action added.'));
        $this->redirectToAutomation($rule->uid->toString());
    }

    public function ___executeAutomationRun(): void
    {
        $this->requirePost();
        $this->requireAutomation();
        $this->requirePermission('kontor-automation-dry-run');
        $rule = $this->requireAutomationRuleFromPost();
        $payloadJson = trim((string) $this->wire()->input->post('payload_json'));
        try {
            $payload = json_decode($payloadJson, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new WireException($this->_('Event payload must be valid JSON.'));
        }
        if (!is_array($payload)) {
            throw new WireException($this->_('Event payload must be a JSON object.'));
        }
        $dryRun = (bool) $this->wire()->input->post('dry_run');
        $results = $this->automationModule()->engine()->handleEvent(
            KontorEvent::create(
                $rule->triggerEvent,
                $this->organizationUid(),
                'automation_probe',
                $rule->uid->toString(),
                'user',
                (string) $this->wire()->user->id,
                $payload,
            ),
            $dryRun,
        );
        $matched = array_filter($results, static fn (array $result): bool => $result['matched']);
        $this->audit(
            'automation',
            'rule',
            $rule->uid->toString(),
            $dryRun ? 'dry_run' : 'executed',
            current: ['evaluated' => count($results), 'matched' => count($matched)],
        );
        $this->message(sprintf(
            $dryRun
                ? $this->_('Dry run completed: %d matching rule(s).')
                : $this->_('Automation run completed: %d matching rule(s).'),
            count($matched),
        ));
        $this->redirectToAutomation($rule->uid->toString());
    }
}
