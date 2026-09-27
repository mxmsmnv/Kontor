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
trait ProcessKontorWorkspaceSupportTrait
{

    /**
     * @return array{
     *   available: bool,
     *   actions: array<int, array{label: string, icon: string, route: string, primary: bool}>,
     *   leads: array<int, Lead>,
     *   deals: array<int, Deal>,
     *   tasks: array<int, Task>
     * }
     */
    private function customerWorkspace(string $entityType, string $entityUid): array
    {
        $crmVisible = $this->crmReady() && $this->can('kontor-crm-lead-view');
        $dealsVisible = $this->crmReady() && $this->can('kontor-crm-deal-view');
        $tasksVisible = $this->tasksReady() && $this->can('kontor-tasks-task-view');
        $actions = (new EntityActionResolver())->resolve($entityType, $entityUid, [
            'crm.lead.create' => $this->crmReady() && $this->can('kontor-crm-lead-create'),
            'crm.deal.create' => $this->crmReady() && $this->can('kontor-crm-deal-create'),
            'tasks.task.create' => $this->tasksReady() && $this->can('kontor-tasks-task-create'),
        ]);
        $field = $entityType === 'contact' ? 'contactUid' : 'companyUid';
        $leads = $crmVisible
            ? array_values(array_filter(
                $this->crmModule()->leadRepository()->findMatching(
                    $this->organizationUid(),
                    limit: 250,
                ),
                static fn (Lead $lead): bool => $lead->{$field} === $entityUid,
            ))
            : [];
        $deals = $dealsVisible
            ? array_values(array_filter(
                $this->crmModule()->dealRepository()->findMatching(
                    $this->organizationUid(),
                    limit: 250,
                ),
                static fn (Deal $deal): bool => $deal->{$field} === $entityUid,
            ))
            : [];
        $tasks = [];

        if ($tasksVisible) {
            foreach ($this->taskModule()->relations()->tasksRelatedTo(
                $this->organizationUid(),
                $entityType,
                $entityUid,
            ) as $taskUid) {
                $task = $this->taskModule()->taskRepository()->findActive(
                    $this->organizationUid(),
                    $taskUid,
                );
                if ($task !== null) {
                    $tasks[] = $task;
                }
            }
        }

        return [
            'available' => $crmVisible || $dealsVisible || $tasksVisible || $actions !== [],
            'actions' => $actions,
            'leads' => $leads,
            'deals' => $deals,
            'tasks' => $tasks,
        ];
    }

    private function customerEntityLabel(string $entityType, string $entityUid): string
    {
        if (!$this->contactsReady()) {
            throw new Wire404Exception();
        }

        if ($entityType === 'contact') {
            $this->requirePermission('kontor-contacts-contact-view');
            $contact = $this->contactRepository()->find($entityUid);
            if ($contact === null || !hash_equals($this->organizationUid(), $contact->organizationId)) {
                throw new Wire404Exception();
            }

            return $contact->displayName;
        }

        if ($entityType === 'company') {
            $this->requirePermission('kontor-contacts-company-view');
            $company = $this->companyRepository()->find($entityUid);
            if ($company === null || !hash_equals($this->organizationUid(), $company->organizationId)) {
                throw new Wire404Exception();
            }

            return $company->tradingName ?: $company->legalName;
        }

        throw new Wire404Exception();
    }

    /**
     * @return array<int, array{value: string, label: string, kind: string, route: string}>
     */
    private function mailEntityTargets(): array
    {
        if (!$this->contactsReady()) {
            return [];
        }

        $targets = [];
        if ($this->can('kontor-contacts-contact-view')) {
            foreach ($this->contactRepository()->findAll($this->organizationUid(), limit: 100) as $contact) {
                $uid = $contact->uid->toString();
                $targets[] = [
                    'value' => 'contact:' . $uid,
                    'label' => $contact->displayName,
                    'kind' => 'Contact',
                    'route' => 'contact/?id=' . rawurlencode($uid),
                ];
            }
        }
        if ($this->can('kontor-contacts-company-view')) {
            foreach ($this->companyRepository()->findAll($this->organizationUid(), limit: 100) as $company) {
                $uid = $company->uid->toString();
                $targets[] = [
                    'value' => 'company:' . $uid,
                    'label' => $company->tradingName ?: $company->legalName,
                    'kind' => 'Company',
                    'route' => 'company/?id=' . rawurlencode($uid),
                ];
            }
        }

        usort($targets, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $targets;
    }

    /**
     * @param array<int, array<string, mixed>> $relations
     * @return array<int, array{label: string, kind: string, route: ?string, uid: string}>
     */
    private function mailRelationViews(array $relations): array
    {
        $views = [];
        foreach ($relations as $relation) {
            $type = (string) ($relation['targetType'] ?? '');
            $uid = (string) ($relation['targetUid'] ?? '');
            if ($uid === '') {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $type)) . ' record';
            $kind = ucfirst(str_replace('_', ' ', $type));
            $route = match ($type) {
                'quotation', 'sales_quotation' => $this->salesReady()
                    && $this->can('kontor-sales-quotation-view')
                    ? 'sales-quotation/?id=' . rawurlencode($uid) : null,
                'order', 'sales_order' => $this->salesReady()
                    && $this->can('kontor-sales-order-view')
                    ? 'sales-order/?id=' . rawurlencode($uid) : null,
                'invoice' => $this->invoicesReady()
                    && $this->can('kontor-invoices-invoice-view')
                    ? 'invoice/?id=' . rawurlencode($uid) : null,
                'deal' => $this->crmReady() && $this->can('kontor-crm-deal-view')
                    ? 'crm-deal/?id=' . rawurlencode($uid) : null,
                'task' => $this->tasksReady() && $this->can('kontor-tasks-task-view')
                    ? 'task/?id=' . rawurlencode($uid) : null,
                'project' => $this->projectsReady() && $this->can('kontor-projects-project-view')
                    ? 'project/?id=' . rawurlencode($uid) : null,
                'catalog_item' => $this->catalogReady() && $this->can('kontor-catalog-item-view')
                    ? 'catalog-item/?id=' . rawurlencode($uid) : null,
                'expense' => $this->expensesReady() && $this->can('kontor-expenses-expense-view')
                    ? 'expense/?id=' . rawurlencode($uid) : null,
                default => null,
            };
            if (in_array($type, ['quotation', 'sales_quotation'], true)
                && $this->salesReady() && $this->can('kontor-sales-quotation-view')) {
                $quotation = $this->salesModule()->quotationRepository()->find($uid);
                if ($quotation !== null && hash_equals($this->organizationUid(), $quotation->organizationId)) {
                    $label = $quotation->number !== null
                        ? sprintf($this->_('Quotation %s'), $quotation->number)
                        : $this->_('Draft quotation');
                    $kind = ucfirst($quotation->status) . ' · ' . number_format(
                        $quotation->total->amountMinor() / 100,
                        2,
                        '.',
                        ','
                    ) . ' ' . $quotation->currencyCode;
                }
            } elseif ($this->contactsReady() && $type === 'contact' && $this->can('kontor-contacts-contact-view')) {
                $contact = $this->contactRepository()->find($uid);
                if ($contact !== null && hash_equals($this->organizationUid(), $contact->organizationId)) {
                    $label = $contact->displayName;
                    $kind = 'Contact';
                    $route = 'contact/?id=' . rawurlencode($uid);
                }
            } elseif ($this->contactsReady() && $type === 'company' && $this->can('kontor-contacts-company-view')) {
                $company = $this->companyRepository()->find($uid);
                if ($company !== null && hash_equals($this->organizationUid(), $company->organizationId)) {
                    $label = $company->tradingName ?: $company->legalName;
                    $kind = 'Company';
                    $route = 'company/?id=' . rawurlencode($uid);
                }
            }

            $views[] = ['label' => $label, 'kind' => $kind, 'route' => $route, 'uid' => $uid];
        }

        return $views;
    }

    private function mailAssignedUserLabel(int $userId): string
    {
        $user = $this->wire()->users->get($userId);

        return $user->id ? ($user->get('title') ?: $user->name) : $this->_('Unassigned');
    }

    /**
     * @return array<int, array{label: string, kind: string, route: string}>
     */
    private function taskRelatedRecords(Task $task): array
    {
        if (!$this->contactsReady()) {
            return [];
        }

        $records = [];
        foreach ($this->taskModule()->relations()->relatedEntities(
            $this->organizationUid(),
            $task->uid->toString(),
        ) as $relation) {
            $type = (string) ($relation['targetType'] ?? '');
            $uid = (string) ($relation['targetUid'] ?? '');
            if ($uid === '') {
                continue;
            }

            if ($type === 'contact' && $this->can('kontor-contacts-contact-view')) {
                $contact = $this->contactRepository()->find($uid);
                if ($contact !== null && hash_equals($this->organizationUid(), $contact->organizationId)) {
                    $records[] = [
                        'label' => $contact->displayName,
                        'kind' => 'Contact',
                        'route' => 'contact/?id=' . rawurlencode($uid),
                    ];
                }
            }

            if ($type === 'company' && $this->can('kontor-contacts-company-view')) {
                $company = $this->companyRepository()->find($uid);
                if ($company !== null && hash_equals($this->organizationUid(), $company->organizationId)) {
                    $records[] = [
                        'label' => $company->tradingName ?: $company->legalName,
                        'kind' => 'Company',
                        'route' => 'company/?id=' . rawurlencode($uid),
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * @return array{available: bool, actions: array, leads: array, deals: array, tasks: array}
     */
    private function emptyCustomerWorkspace(): array
    {
        return ['available' => false, 'actions' => [], 'leads' => [], 'deals' => [], 'tasks' => []];
    }

    private function can(string $permission): bool
    {
        return $this->wire()->user->isSuperuser()
            || $this->wire()->user->hasPermission($permission);
    }

    private function requireProjectFromPost(): Project
    {
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('project_uid'));
        $project = $this->projectsModule()->projectRepository()->require($id);
        $this->requireSameOrganization($project->organizationId);

        return $project;
    }

    private function validatedProjectMilestoneUid(string $projectUid, string $milestoneUid): ?string
    {
        $milestoneUid = $this->wire()->sanitizer->text($milestoneUid);
        if ($milestoneUid === '') {
            return null;
        }
        $milestone = $this->projectsModule()->milestoneRepository()->require($milestoneUid);
        if ($milestone->projectUid !== $projectUid || $milestone->organizationId !== $this->organizationUid()) {
            throw new WirePermissionException($this->_('Milestone does not belong to this project.'));
        }

        return $milestoneUid;
    }

    private function redirectToProject(string $projectUid): void
    {
        $this->wire()->session->redirect(
            '../project/?id=' . rawurlencode($projectUid)
        );
    }

    private function requireWorkflowDefinitionFromPost(): \Kontor\Workflow\Domain\WorkflowDefinition
    {
        $id = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('definition_uid')
        );
        $definition = $this->workflowModule()->definitionRepository()->require($id);
        $this->requireSameOrganization($definition->organizationId);

        return $definition;
    }

    private function redirectToWorkflow(string $definitionUid): void
    {
        $this->wire()->session->redirect(
            '../workflow/?id=' . rawurlencode($definitionUid)
        );
    }

    private function requireAutomationRuleFromPost(): \Kontor\Automation\Domain\AutomationRule
    {
        $id = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('rule_uid')
        );
        $rule = $this->automationModule()->ruleRepository()->require($id);
        $this->requireSameOrganization($rule->organizationId);

        return $rule;
    }

    private function redirectToAutomation(string $ruleUid): void
    {
        $this->wire()->session->redirect(
            '../automation/?id=' . rawurlencode($ruleUid)
        );
    }

    private function requireEntityViewPermission(
        \Kontor\Entities\Domain\EntityDefinition $definition,
    ): void {
        $this->requirePermission('kontor-entities-record-view');
        if ($definition->viewPermission !== null) {
            $this->requirePermission($definition->viewPermission);
        }
    }

    private function canEditEntity(
        \Kontor\Entities\Domain\EntityDefinition $definition,
    ): bool {
        return $this->can('kontor-entities-record-manage')
            && ($definition->editPermission === null || $this->can($definition->editPermission));
    }

    private function requireEntityEditPermission(
        \Kontor\Entities\Domain\EntityDefinition $definition,
    ): void {
        $this->requirePermission('kontor-entities-record-manage');
        if ($definition->editPermission !== null) {
            $this->requirePermission($definition->editPermission);
        }
    }

    private function requireEntityDefinitionFromPost(): \Kontor\Entities\Domain\EntityDefinition
    {
        $id = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('definition_uid')
        );
        $definition = $this->entitiesModule()->definitionRepository()->require($id);
        $this->requireSameOrganization($definition->organizationId);

        return $definition;
    }

    private function redirectToEntityDefinition(string $definitionUid): void
    {
        $this->wire()->session->redirect(
            '../custom-entity/?id=' . rawurlencode($definitionUid)
        );
    }

    /**
     * @param \Kontor\Entities\Domain\EntityField[] $fields
     * @return array<string, mixed>
     */
    private function entityRecordDataFromPost(array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $raw = $this->wire()->input->post($field->fieldKey);
            if ($field->fieldType === 'bool') {
                $data[$field->fieldKey] = (bool) $raw;
                continue;
            }
            $value = trim((string) $raw);
            if ($value === '') {
                $data[$field->fieldKey] = null;
                continue;
            }
            $data[$field->fieldKey] = match ($field->fieldType) {
                'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
                    ? (int) $value
                    : throw new \InvalidArgumentException(sprintf(
                        $this->_('%s must be a whole number.'),
                        $field->label,
                    )),
                'decimal' => is_numeric($value)
                    ? (float) $value
                    : throw new \InvalidArgumentException(sprintf(
                        $this->_('%s must be a number.'),
                        $field->label,
                    )),
                'date', 'datetime' => $this->wire()->sanitizer->text($value),
                default => trim($this->wire()->sanitizer->text($value)),
            };
        }

        return $data;
    }

    private function mailboxFromPost(): ?\Kontor\Mail\Domain\Mailbox
    {
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('mailbox_uid')
        );
        if ($uid === '') {
            return null;
        }
        $mailbox = $this->mailModule()->mailboxRepository()->require($uid);
        $this->requireSameOrganization($mailbox->organizationId);

        return $mailbox;
    }

    /**
     * @return string[]
     */
    private function mailAddressesFromPost(string $field, bool $required = true): array
    {
        $raw = (string) $this->wire()->input->post($field);
        $addresses = array_values(array_unique(array_filter(array_map(
            static fn (string $address): string => strtolower(trim($address)),
            preg_split('/[,;\\s]+/', $raw) ?: [],
        ))));
        if (($required && $addresses === [])
            || array_filter(
                $addresses,
                static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) === false,
            ) !== []) {
            throw new WireException($this->_('Recipient addresses are invalid.'));
        }

        return $addresses;
    }

    /**
     * @return array<string, mixed>
     */
    private function aiJsonObjectFromPost(string $field): array
    {
        $raw = trim((string) $this->wire()->input->post($field));
        if ($raw === '') {
            return [];
        }
        if (strlen($raw) > 65536) {
            throw new WireException($this->_('AI JSON input must be at most 64 KB.'));
        }
        try {
            $value = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WireException($this->_('AI JSON input must contain valid JSON.'));
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new WireException($this->_('AI JSON input must be an object.'));
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function aiExtractionSchemaFromPost(): array
    {
        $raw = trim((string) $this->wire()->input->post('schema_fields'));
        if ($raw === '' || strlen($raw) > 8192) {
            throw new WireException($this->_('Add the details to extract using one “Name: type” line per detail.'));
        }

        $types = [
            'text' => 'string',
            'string' => 'string',
            'number' => 'decimal',
            'decimal' => 'decimal',
            'date' => 'date',
            'yes/no' => 'boolean',
            'boolean' => 'boolean',
        ];
        $schema = [];
        foreach (preg_split('/\R+/', $raw) ?: [] as $line) {
            $parts = array_map('trim', explode(':', trim($line), 2));
            if (count($parts) !== 2 || $parts[0] === '' || !isset($types[strtolower($parts[1])])) {
                throw new WireException($this->_('Each extraction detail must use “Name: type”, for example “Amount: number”.'));
            }
            $field = $this->wire()->sanitizer->fieldName(str_replace(' ', '_', strtolower($parts[0])));
            if ($field === '') {
                throw new WireException($this->_('Each extraction detail needs a clear name.'));
            }
            $schema[$field] = $types[strtolower($parts[1])];
        }
        if ($schema === [] || count($schema) > 30) {
            throw new WireException($this->_('Add between 1 and 30 details to extract.'));
        }

        return $schema;
    }

    private function ledgerAmountMinorFromPost(): int
    {
        return $this->positiveMoneyMinorFromPost('amount', 'Amount');
    }

    private function positiveMoneyMinorFromPost(string $field, string $label): int
    {
        $raw = str_replace(',', '.', trim((string) $this->wire()->input->post($field)));
        if (preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $raw) !== 1) {
            throw new WireException(sprintf(
                $this->_('%s must be positive with at most two decimal places.'),
                $label,
            ));
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($minor <= 0) {
            throw new WireException(sprintf($this->_('%s must be greater than zero.'), $label));
        }

        return $minor;
    }

    private function germanyRequiredTextFromPost(string $field, string $label, int $maxLength): string
    {
        $value = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post($field)
        ));
        if ($value === '') {
            throw new WireException(sprintf($this->_('%s is required.'), $label));
        }
        if (mb_strlen($value) > $maxLength) {
            throw new WireException(sprintf(
                $this->_('%s must be at most %d characters.'),
                $label,
                $maxLength,
            ));
        }

        return $value;
    }

    private function germanyDateFromPost(string $field, string $label): \DateTimeImmutable
    {
        $raw = trim((string) $this->wire()->input->post($field));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new WireException(sprintf($this->_('%s must use YYYY-MM-DD.'), $label));
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function fileMetadataFromPost(): array
    {
        $raw = trim((string) $this->wire()->input->post('metadata_json'));
        if ($raw === '') {
            return [];
        }
        if (strlen($raw) > 65536) {
            throw new WireException($this->_('File metadata JSON must be at most 64 KB.'));
        }
        try {
            $metadata = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WireException($this->_('File metadata must be valid JSON.'));
        }
        if (!is_array($metadata) || array_is_list($metadata)) {
            throw new WireException($this->_('File metadata must be a JSON object.'));
        }

        return $metadata;
    }

    /**
     * @return array{
     *   namespace: string,
     *   effectiveNamespace: string,
     *   key: string,
     *   tags: string[],
     *   ttl: int|null,
     *   valueJson: string
     * }
     */
    private function cacheWorkbenchInput(): array
    {
        $namespace = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('namespace')
        )));
        $key = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('key')
        ));
        if (preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $namespace) !== 1) {
            throw new WireException($this->_('Namespace must start with a letter and use up to 40 safe characters.'));
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:._-]{0,127}$/', $key) !== 1) {
            throw new WireException($this->_('Cache key must use 1–128 safe characters.'));
        }
        $tagsRaw = trim((string) $this->wire()->input->post('tags'));
        $tags = [];
        if ($tagsRaw !== '') {
            foreach (array_filter(array_map('trim', explode(',', strtolower($tagsRaw)))) as $tag) {
                if (preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $tag) !== 1) {
                    throw new WireException($this->_('Each tag must start with a letter and use up to 40 safe characters.'));
                }
                $tags[$tag] = $tag;
            }
        }
        $tags = array_values($tags);
        if (count($tags) > 10) {
            throw new WireException($this->_('At most 10 cache tags are allowed.'));
        }
        sort($tags);
        $ttlRaw = trim((string) $this->wire()->input->post('ttl'));
        if ($ttlRaw === '') {
            $ttlRaw = '0';
        }
        if (preg_match('/^\d{1,5}$/', $ttlRaw) !== 1 || (int) $ttlRaw > 86400) {
            throw new WireException($this->_('TTL must be between 0 and 86400 seconds.'));
        }
        $ttl = (int) $ttlRaw;
        $valueJson = trim((string) $this->wire()->input->post('value_json'));
        if (strlen($valueJson) > 65536) {
            throw new WireException($this->_('Cached JSON must be at most 64 KB.'));
        }

        return [
            'namespace' => $namespace,
            'effectiveNamespace' => 'kontor-admin-org-' . $this->organizationInternalId() . '-' . $namespace,
            'key' => $key,
            'tags' => $tags,
            'ttl' => $ttl > 0 ? $ttl : null,
            'valueJson' => $valueJson,
        ];
    }

    private function cacheValueFromPost(string $raw): mixed
    {
        if ($raw === '') {
            throw new WireException($this->_('Cached JSON value is required for set.'));
        }
        try {
            $value = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WireException($this->_('Cached value must be valid JSON.'));
        }
        if ($value === null) {
            throw new WireException($this->_('A JSON null cannot be distinguished from a cache miss.'));
        }

        return $value;
    }

    /**
     * @return array<string, array{label: string, unitCode: string}>
     */
    private function inventoryItemOptions(): array
    {
        if (!$this->catalogReady()) {
            return [];
        }

        $locale = $this->organization()->defaultLanguage;
        $items = [];
        foreach ($this->catalogItemRepository()->findAll(
            $this->organizationUid(),
            limit: 250,
            status: 'active',
            trackInventory: true,
        ) as $item) {
            $items[$item->uid->toString()] = [
                'label' => ($item->sku ? $item->sku . ' · ' : '')
                    . ($item->titleIn($locale) ?? $item->uid->toString()),
                'unitCode' => $item->unitCode,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function inventoryItemLabels(): array
    {
        return array_map(
            static fn (array $item): string => $item['label'],
            $this->inventoryItemOptions(),
        );
    }

    /**
     * @param DocumentLine[] $lines
     * @return DocumentLine[]
     */
    private function salesOrderTrackedLines(array $lines): array
    {
        if (!$this->inventoryReady()) {
            return [];
        }

        $trackedItems = $this->inventoryItemOptions();

        return array_values(array_filter(
            $lines,
            static fn (DocumentLine $line): bool =>
                $line->itemUid !== null && isset($trackedItems[$line->itemUid]),
        ));
    }

    /**
     * @return \Kontor\Inventory\Domain\InventoryMovement[]
     */
    private function salesOrderReservationMovements(string $orderUid): array
    {
        if (!$this->inventoryReady()) {
            return [];
        }

        return array_values(array_filter(
            $this->inventoryModule()->movementRepository()->forReference(
                $this->organizationUid(),
                'sales_order',
                $orderUid,
            ),
            static fn (\Kontor\Inventory\Domain\InventoryMovement $movement): bool =>
                $movement->movementType === 'reserve',
        ));
    }

    /**
     * @param \Kontor\Inventory\Domain\InventoryMovement[] $reservations
     */
    private function reservationWarehouseUid(array $reservations): ?string
    {
        $warehouses = array_values(array_unique(array_filter(array_map(
            static fn (\Kontor\Inventory\Domain\InventoryMovement $movement): ?string =>
                $movement->destinationWarehouseUid,
            $reservations,
        ))));

        return count($warehouses) === 1 ? $warehouses[0] : null;
    }

    private function canPerformAnyInventoryMovement(): bool
    {
        $user = $this->wire()->user;
        if ($user->isSuperuser()) {
            return true;
        }
        foreach ([
            'kontor-inventory-receive',
            'kontor-inventory-transfer',
            'kontor-inventory-adjust',
            'kontor-inventory-reserve',
            'kontor-inventory-release',
        ] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, Note|Comment> $records
     * @return array<string, array{label: string, route: string}>
     */
    private function collaborationEntityLinks(array $records): array
    {
        $links = [];
        foreach ($records as $record) {
            $key = $record->entityType . ':' . $record->entityUid;
            if (isset($links[$key])) {
                continue;
            }

            $entity = null;
            $route = '';
            $label = '';
            if ($record->entityType === 'task' && $this->tasksReady() && $this->can('kontor-tasks-task-view')) {
                $entity = $this->taskModule()->taskRepository()->find($record->entityUid);
                $route = 'task/';
                $label = $entity?->title ?? '';
            } elseif ($record->entityType === 'project' && $this->projectsReady() && $this->can('kontor-projects-project-view')) {
                $entity = $this->projectsModule()->projectRepository()->find($record->entityUid);
                $route = 'project/';
                $label = $entity?->name ?? '';
            } elseif ($record->entityType === 'deal' && $this->crmReady() && $this->can('kontor-crm-deal-view')) {
                $entity = $this->crmModule()->dealRepository()->find($record->entityUid);
                $route = 'crm-deal/';
                $label = $entity?->title ?? '';
            } elseif ($record->entityType === 'lead' && $this->crmReady() && $this->can('kontor-crm-lead-view')) {
                $entity = $this->crmModule()->leadRepository()->find($record->entityUid);
                $route = 'crm-lead/';
                $label = $entity?->title ?? '';
            } elseif ($record->entityType === 'contact' && $this->contactsReady() && $this->can('kontor-contacts-contact-view')) {
                $entity = $this->contactRepository()->find($record->entityUid);
                $route = 'contact/';
                $label = $entity?->displayName ?? '';
            } elseif ($record->entityType === 'company' && $this->contactsReady() && $this->can('kontor-contacts-company-view')) {
                $entity = $this->companyRepository()->find($record->entityUid);
                $route = 'company/';
                $label = $entity?->legalName ?? '';
            }

            if ($entity !== null
                && property_exists($entity, 'organizationId')
                && hash_equals($this->organizationUid(), $entity->organizationId)
                && $label !== '') {
                $links[$key] = [
                    'label' => $label,
                    'route' => $route . '?id=' . rawurlencode($record->entityUid),
                ];
            }
        }

        return $links;
    }

    /**
     * @param array<int, Note|Comment> $records
     * @return array<int, string>
     */
    private function collaborationAuthorLabels(array $records): array
    {
        $labels = [];
        foreach ($records as $record) {
            if ($record->createdBy === null || isset($labels[$record->createdBy])) {
                continue;
            }
            $author = $this->wire()->users->get($record->createdBy);
            if ($author->id > 0) {
                $labels[$record->createdBy] = (string) ($author->get('title') ?: $author->name);
            }
        }

        return $labels;
    }

    /**
     * @return string[] dispatched queue job UIDs
     */
    private function queueCollaborationNotifications(Comment $comment, string $entityLabel): array
    {
        $recipientReasons = [];
        foreach ($this->collaborationModule()->mentionRepository()->forComment($comment->uid->toString()) as $mention) {
            $recipientReasons[$mention->mentionedUserId] = 'mention';
        }
        foreach ($this->collaborationModule()->followerRepository()->followersOf(
            $comment->entityType,
            $comment->entityUid,
        ) as $follower) {
            $recipientReasons[$follower->userId] ??= 'follow';
        }

        $recipients = [];
        foreach ($recipientReasons as $userId => $reason) {
            if ((int) $userId === $comment->createdBy) {
                continue;
            }

            $user = $this->wire()->users->get((int) $userId);
            $email = strtolower(trim((string) $user->email));
            if (!$user->id || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[] = ['userId' => (int) $userId, 'email' => $email, 'reason' => $reason];
        }

        $fromAddress = strtolower(trim((string) $this->wire()->config->adminEmail));
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $fromAddress = 'notifications@kontor.local';
        }
        $authorLabel = trim((string) $this->wire()->user->name) ?: 'A Kontor user';

        return $this->collaborationModule()->notificationDispatcher()->dispatch(
            organizationUid: $comment->organizationId,
            commentUid: $comment->uid->toString(),
            entityType: $comment->entityType,
            entityUid: $comment->entityUid,
            authorUserId: $comment->createdBy,
            authorLabel: $authorLabel,
            fromAddress: $fromAddress,
            subject: mb_substr("New comment on task: {$entityLabel}", 0, 255),
            body: "{$authorLabel} posted a comment on “{$entityLabel}”:\n\n{$comment->body}",
            recipients: $recipients,
        );
    }

    private function requireDataExchangeEntity(string $entityType): void
    {
        $entityType === 'catalog_item'
            ? $this->requireCatalog()
            : $this->requireContacts();
    }

    private function catalogListRedirect(): string
    {
        $query = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_q')
        );
        $type = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_type'),
            ['product', 'service']
        );
        $categoryUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_category')
        );
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_status'),
            ['active', 'inactive', 'discontinued']
        );
        $inventory = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_inventory'),
            ['tracked', 'untracked']
        );
        $unit = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_unit'),
            array_keys((new UnitOfMeasure())->all())
        );
        $tax = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_tax'),
            array_keys((new TaxCode())->all())
        );
        $currency = strtoupper($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_currency')
        ));
        $currency = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
        $pricing = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_pricing'),
            ['priced', 'unpriced']
        );
        $archived = (string) $this->wire()->input->post('return_archived') === '1';
        $page = max(1, (int) $this->wire()->input->post('return_page'));
        $parameters = array_filter([
            'q' => $query,
            'type' => $type,
            'category' => $categoryUid,
            'status' => $status,
            'inventory' => $inventory,
            'unit' => $unit,
            'tax' => $tax,
            'currency' => $currency,
            'pricing' => $pricing,
            'archived' => $archived ? 1 : null,
            'page' => $page > 1 ? $page : null,
        ], static fn (string|int|null $value): bool => $value !== null && $value !== '');

        return '../catalog/' . ($parameters === [] ? '' : '?' . http_build_query($parameters));
    }

    private function catalogCategoryListRedirect(): string
    {
        $query = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_q')
        );
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_status'),
            ['active', 'inactive']
        );
        $archived = (string) $this->wire()->input->post('return_archived') === '1';
        $page = max(1, (int) $this->wire()->input->post('return_page'));
        $parameters = array_filter([
            'q' => $query,
            'status' => $status,
            'archived' => $archived ? 1 : null,
            'page' => $page > 1 ? $page : null,
        ], static fn (string|int|null $value): bool => $value !== null && $value !== '');

        return '../catalog-categories/' . ($parameters === [] ? '' : '?' . http_build_query($parameters));
    }

    private function catalogPriceListRedirect(): string
    {
        $query = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_q')
        );
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_status'),
            ['active', 'inactive']
        );
        $validity = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('return_validity'),
            ['current', 'upcoming', 'expired']
        );
        $currency = strtoupper($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('return_currency')
        ));
        $currency = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
        $page = max(1, (int) $this->wire()->input->post('return_page'));
        $parameters = array_filter([
            'q' => $query,
            'status' => $status,
            'validity' => $validity,
            'currency' => $currency,
            'page' => $page > 1 ? $page : null,
        ], static fn (string|int|null $value): bool => $value !== null && $value !== '');

        return '../catalog-price-lists/' . ($parameters === [] ? '' : '?' . http_build_query($parameters));
    }

    /**
     * @return array<string, string>
     */
    private function availableImportEntityTypes(): array
    {
        $types = [];

        if ($this->contactsReady()) {
            $types['contact'] = $this->_('Contacts');
            $types['company'] = $this->_('Companies');
        }

        if ($this->catalogReady()) {
            $types['catalog_item'] = $this->_('Catalog items');
        }

        return $types;
    }

    private function queueReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorQueue');
    }

    private function requireQueue(): void
    {
        if (!$this->queueReady()) {
            throw new WireException($this->_('The Kontor Queue component is not installed.'));
        }
    }

    private function jobRepository(): JobRepository
    {
        /** @var KontorQueue $module */
        $module = $this->wire()->modules->get('KontorQueue');

        return $module->jobRepository();
    }

    private function catalogItemRepository(): CatalogItemRepository
    {
        /** @var KontorCatalog $module */
        $module = $this->wire()->modules->get('KontorCatalog');

        return $module->itemRepository();
    }

    private function categoryRepository(): CategoryRepository
    {
        /** @var KontorCatalog $module */
        $module = $this->wire()->modules->get('KontorCatalog');

        return $module->categoryRepository();
    }

    private function priceListRepository(): PriceListRepository
    {
        /** @var KontorCatalog $module */
        $module = $this->wire()->modules->get('KontorCatalog');

        return $module->priceListRepository();
    }

    private function priceRepository(): PriceRepository
    {
        /** @var KontorCatalog $module */
        $module = $this->wire()->modules->get('KontorCatalog');

        return $module->priceRepository();
    }

    private function priceListDuplicator(): PriceListDuplicator
    {
        /** @var KontorCatalog $module */
        $module = $this->wire()->modules->get('KontorCatalog');

        return $module->priceListDuplicator();
    }

    private function contactRepository(): ContactRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->contactRepository();
    }

    private function companyRepository(): CompanyRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->companyRepository();
    }

    private function membershipRepository(): MembershipRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->membershipRepository();
    }

    private function addressRepository(): AddressRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->addressRepository();
    }

    private function duplicateDetector(): ContactDuplicateDetector
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->duplicateDetector();
    }

    private function tagService(): TagService
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->tagService();
    }

    /**
     * @return array<int, array{membership: ContactCompanyMembership, entity: Company}>
     */
    private function contactRelationships(Contact $contact): array
    {
        $relationships = [];

        foreach ($this->membershipRepository()->forContact($contact->uid->toString()) as $membership) {
            try {
                $company = $this->companyRepository()->require($membership->companyUid);
            } catch (\RuntimeException) {
                continue;
            }

            if ($company->organizationId === $contact->organizationId) {
                $relationships[] = ['membership' => $membership, 'entity' => $company];
            }
        }

        return $relationships;
    }

    /**
     * @return array<int, array{membership: ContactCompanyMembership, entity: Contact}>
     */
    private function companyRelationships(Company $company): array
    {
        $relationships = [];

        foreach ($this->membershipRepository()->forCompany($company->uid->toString()) as $membership) {
            try {
                $contact = $this->contactRepository()->require($membership->contactUid);
            } catch (\RuntimeException) {
                continue;
            }

            if ($contact->organizationId === $company->organizationId) {
                $relationships[] = ['membership' => $membership, 'entity' => $contact];
            }
        }

        return $relationships;
    }

    private function organizationUid(): string
    {
        return $this->organization()->uid->toString();
    }

    private function organization(): Organization
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()
            ->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
    }

    private function organizationRepository(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function organizationInternalId(): int
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()
            ->get(OrganizationRepository::class)
            ->internalIdOf($this->organizationUid());
    }

    private function componentRegistry(): ComponentRegistry
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ComponentRegistry::class);
    }

    private function auditEventRepository(): AuditEventRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(AuditEventRepository::class);
    }

    private function healthCheckRunner(): HealthCheckRunner
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(HealthCheckRunner::class);
    }

    private function coreHealthCheck(): CoreHealthCheck
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(CoreHealthCheck::class);
    }

    private function exportManager(): ExportManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ExportManager::class);
    }

    private function importManager(): ImportManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ImportManager::class);
    }

    private function backupManager(): BackupManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(BackupManager::class);
    }

    private function backupArchiveBuilder(): BackupArchiveBuilder
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(BackupArchiveBuilder::class);
    }

    private function searchService(): GlobalSearchService
    {
        /** @var KontorSearch $module */
        $module = $this->wire()->modules->get('KontorSearch');

        return $module->globalSearchService();
    }
}
