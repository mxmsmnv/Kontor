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
trait ProcessKontorModuleAccessTrait
{

    private function contactsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorContacts');
    }

    private function catalogReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorCatalog');
    }

    private function crmReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorCRM');
    }

    private function crmIntakeReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorCRMIntake');
    }

    /** @return array<int, array<string, mixed>> */
    private function crmIntakeFields(string $entityType): array
    {
        if (!$this->crmIntakeReady()) {
            return [];
        }

        return $this->crmIntakeModule()->service()->fieldsFor($this->organizationUid(), $entityType);
    }

    /** @return array<string, mixed> */
    private function crmIntakeValues(string $entityType, string $entityUid): array
    {
        if (!$this->crmIntakeReady()) {
            return [];
        }

        return $this->crmIntakeModule()->service()->valuesFor(
            $this->organizationUid(),
            $entityType,
            $entityUid,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>|null
     */
    private function postedCrmIntakeAnswers(string $entityType, array $fields): ?array
    {
        if (!$this->crmIntakeReady() || $fields === []) {
            return null;
        }
        $answers = [];
        foreach ($fields as $field) {
            $name = 'crm_intake__' . $field['key'];
            $value = $this->wire()->input->post($name);
            if ($field['type'] === 'multiselect') {
                $answers[$field['key']] = array_map(
                    fn (mixed $item): string => $this->wire()->sanitizer->text((string) $item),
                    is_array($value) ? $value : [],
                );
            } elseif ($field['type'] === 'textarea') {
                $answers[$field['key']] = $this->wire()->sanitizer->textarea((string) $value);
            } else {
                $answers[$field['key']] = $this->wire()->sanitizer->text((string) $value);
            }
        }

        return $this->crmIntakeModule()->service()->validateAnswers(
            $this->organizationUid(),
            $entityType,
            $answers,
        );
    }

    /** @param array<string, mixed> $answers */
    private function saveCrmIntakeAnswers(string $entityType, string $entityUid, array $answers): void
    {
        $this->crmIntakeModule()->service()->saveAnswers(
            $this->organizationUid(),
            $entityType,
            $entityUid,
            $answers,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $answers
     */
    private function crmIntakeBoundValue(array $fields, array $answers, string $binding): ?string
    {
        foreach ($fields as $field) {
            if (($field['binding'] ?? null) !== $binding) {
                continue;
            }
            $value = $answers[$field['key']] ?? null;

            return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        }

        return null;
    }

    private function salesReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorSales');
    }

    private function invoicesReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorInvoices');
    }

    private function paymentsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorPayments');
    }

    private function tasksReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorTasks');
    }

    private function collaborationReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorCollaboration');
    }

    private function dashboardReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorDashboard');
    }

    private function reportsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorReports');
    }

    private function searchReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorSearch');
    }

    private function inventoryReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorInventory');
    }

    private function purchasingReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorPurchasing');
    }

    private function expensesReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorExpenses');
    }

    private function projectsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorProjects');
    }

    private function workflowReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorWorkflow');
    }

    private function demoReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorDemo');
    }

    private function automationReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorAutomation');
    }

    private function entitiesReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorEntities');
    }

    private function apiReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorAPI');
    }

    private function graphqlReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorGraphQL');
    }

    private function marketplaceReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorMarketplace');
    }

    private function mailReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorMail');
    }

    private function portalReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorPortal');
    }

    private function filesReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorFiles');
    }

    private function cacheReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorCache');
    }

    private function documentsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorDocuments');
    }

    private function aiReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorAI');
    }

    private function ledgerReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorLedger');
    }

    private function germanyReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorGermany');
    }

    private function settingsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorSettings');
    }

    private function requireCatalog(): void
    {
        if (!$this->catalogReady()) {
            throw new WireException($this->_('The Kontor Catalog component is not installed.'));
        }
    }

    private function requireCrm(): void
    {
        if (!$this->crmReady()) {
            throw new WireException($this->_('The Kontor CRM component is not installed.'));
        }
    }

    private function requireSales(): void
    {
        if (!$this->salesReady()) {
            throw new WireException($this->_('The Kontor Sales component is not installed.'));
        }
    }

    private function requireInvoices(): void
    {
        if (!$this->invoicesReady()) {
            throw new WireException($this->_('The Kontor Invoices component is not installed.'));
        }
    }

    private function requirePayments(): void
    {
        if (!$this->paymentsReady()) {
            throw new WireException($this->_('The Kontor Payments component is not installed.'));
        }
    }

    private function requireTasks(): void
    {
        if (!$this->tasksReady()) {
            throw new WireException($this->_('The Kontor Tasks component is not installed.'));
        }
    }

    private function requireCollaboration(): void
    {
        if (!$this->collaborationReady()) {
            throw new WireException($this->_('The Kontor Collaboration component is not installed.'));
        }
    }

    private function requireDashboard(): void
    {
        if (!$this->dashboardReady()) {
            throw new WireException($this->_('The Kontor Dashboard component is not installed.'));
        }
    }

    private function requireReports(): void
    {
        if (!$this->reportsReady()) {
            throw new WireException($this->_('The Kontor Reports component is not installed.'));
        }
    }

    private function requireSearch(): void
    {
        if (!$this->searchReady()) {
            throw new WireException($this->_('The Kontor Search component is not installed.'));
        }
    }

    private function requireInventory(): void
    {
        if (!$this->inventoryReady()) {
            throw new WireException($this->_('The Kontor Inventory component is not installed.'));
        }
    }

    private function requirePurchasing(): void
    {
        if (!$this->purchasingReady()) {
            throw new WireException($this->_('The Kontor Purchasing component is not installed.'));
        }
    }

    private function requireExpenses(): void
    {
        if (!$this->expensesReady()) {
            throw new WireException($this->_('The Kontor Expenses component is not installed.'));
        }
    }

    private function requireProjects(): void
    {
        if (!$this->projectsReady()) {
            throw new WireException($this->_('The Kontor Projects component is not installed.'));
        }
    }

    private function requireWorkflow(): void
    {
        if (!$this->workflowReady()) {
            throw new WireException($this->_('The Kontor Workflow component is not installed.'));
        }
    }

    private function requireDemo(): void
    {
        if (!$this->demoReady()) {
            throw new WireException($this->_('The Kontor Demo component is not installed.'));
        }
    }

    private function requireAutomation(): void
    {
        if (!$this->automationReady()) {
            throw new WireException($this->_('The Kontor Automation component is not installed.'));
        }
    }

    private function requireEntities(): void
    {
        if (!$this->entitiesReady()) {
            throw new WireException($this->_('The Kontor Custom Entities component is not installed.'));
        }
    }

    private function requireApi(): void
    {
        if (!$this->apiReady()) {
            throw new WireException($this->_('The Kontor API component is not installed.'));
        }
    }

    private function requireGraphql(): void
    {
        if (!$this->graphqlReady()) {
            throw new WireException($this->_('The Kontor GraphQL component is not installed.'));
        }
    }

    private function requireMarketplace(): void
    {
        if (!$this->marketplaceReady()) {
            throw new WireException($this->_('The Kontor Marketplace component is not installed.'));
        }
    }

    private function requireMail(): void
    {
        if (!$this->mailReady()) {
            throw new WireException($this->_('The Kontor Mail component is not installed.'));
        }
    }

    private function requirePortal(): void
    {
        if (!$this->portalReady()) {
            throw new WireException($this->_('The Kontor Portal component is not installed.'));
        }
    }

    private function requireFiles(): void
    {
        if (!$this->filesReady()) {
            throw new WireException($this->_('The Kontor Files component is not installed.'));
        }
    }

    private function requireCache(): void
    {
        if (!$this->cacheReady()) {
            throw new WireException($this->_('The Kontor Cache component is not installed.'));
        }
    }

    private function requireDocuments(): void
    {
        if (!$this->documentsReady()) {
            throw new WireException($this->_('The Kontor Documents component is not installed.'));
        }
    }

    private function requireAI(): void
    {
        if (!$this->aiReady()) {
            throw new WireException($this->_('The Kontor AI component is not installed.'));
        }
    }

    private function requireLedger(): void
    {
        if (!$this->ledgerReady()) {
            throw new WireException($this->_('The Kontor Ledger component is not installed.'));
        }
    }

    private function requireGermany(): void
    {
        if (!$this->germanyReady()) {
            throw new WireException($this->_('The Kontor Germany component is not installed.'));
        }
    }

    private function requireSettings(): void
    {
        if (!$this->settingsReady()) {
            throw new WireException($this->_('The Kontor Settings component is not installed.'));
        }
    }

    private function requireContacts(): void
    {
        if (!$this->contactsReady()) {
            throw new WireException($this->_('The Kontor Contacts component is not installed.'));
        }
    }

    /**
     * @return array<string, string>
     */
    private function salesCustomerLabels(): array
    {
        if (!$this->contactsReady()) {
            return [];
        }

        $labels = [];
        foreach ($this->contactRepository()->findAll($this->organizationUid(), limit: 250) as $contact) {
            $labels['contact:' . $contact->uid->toString()] = $this->_('Contact') . ' · ' . $contact->displayName;
        }
        foreach ($this->companyRepository()->findAll($this->organizationUid(), limit: 250) as $company) {
            $labels['company:' . $company->uid->toString()] = $this->_('Company') . ' · ' . $company->legalName;
        }

        return $labels;
    }

    private function invoiceCustomerEmail(Invoice $invoice): string
    {
        if (!$this->contactsReady()) {
            return '';
        }

        $customer = $invoice->customerType === 'contact'
            ? $this->contactRepository()->find($invoice->customerUid)
            : $this->companyRepository()->find($invoice->customerUid);

        return $customer !== null
            && hash_equals($customer->organizationId, $invoice->organizationId)
            ? (string) ($customer->email ?? '')
            : '';
    }

    private function quotationCustomerEmail(Quotation $quotation): string
    {
        if (!$this->contactsReady()) {
            return '';
        }

        $customer = $quotation->customerType === 'contact'
            ? $this->contactRepository()->find($quotation->customerUid)
            : $this->companyRepository()->find($quotation->customerUid);

        return $customer !== null
            && hash_equals($customer->organizationId, $quotation->organizationId)
            ? (string) ($customer->email ?? '')
            : '';
    }

    /**
     * @param DocumentLine[] $lines
     * @return array<string, mixed>
     */
    private function quotationDocumentData(Quotation $quotation, array $lines): array
    {
        $customerKey = $quotation->customerType . ':' . $quotation->customerUid;

        return [
            'title' => $this->_('Quotation'),
            'number' => $quotation->number,
            'issueDate' => $quotation->issueDate?->format('Y-m-d'),
            'validUntil' => $quotation->validUntil?->format('Y-m-d'),
            'customer' => [
                'type' => $quotation->customerType,
                'uid' => $quotation->customerUid,
                'name' => $this->salesCustomerLabels()[$customerKey] ?? $quotation->customerUid,
            ],
            'currency' => $quotation->currencyCode,
            'lines' => array_map(static fn (DocumentLine $line): array => [
                'title' => $line->title,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unitCode,
                'unitPrice' => number_format($line->unitPrice->amountMinor() / 100, 2, '.', ''),
                'taxRate' => $line->taxRate,
                'total' => number_format($line->total()->amountMinor() / 100, 2, '.', ''),
            ], $lines),
            'subtotal' => number_format($quotation->subtotal->amountMinor() / 100, 2, '.', ''),
            'tax' => number_format($quotation->tax->amountMinor() / 100, 2, '.', ''),
            'total' => number_format($quotation->total->amountMinor() / 100, 2, '.', ''),
        ];
    }

    /**
     * @return array{uid: string, versionNumber: int}
     */
    private function storeIssuedInvoiceDocument(
        KontorInvoices $module,
        Invoice $invoice,
        \Kontor\Documents\Domain\DocumentTemplate $template,
    ): array {
        $documentType = $invoice->kind === 'credit_note' ? 'credit_note' : 'invoice';
        $lines = $module->documentLineRepository()->forDocument(
            $documentType,
            $invoice->uid->toString(),
        );
        $data = $this->invoiceDocumentData($invoice, $lines);
        $snapshot = $this->documentsModule()->snapshotBuilder()->build($template, $data);
        $pdf = $this->documentsModule()->renderService()->renderPdf($template, $data);
        $invoice->attachIssuedDocument($template->uid->toString(), $snapshot);
        $module->invoiceRepository()->save($invoice);

        return $this->filesModule()->fileManager()->upload(
            organizationUid: $invoice->organizationId,
            originalName: ($invoice->number ?? $documentType . '-' . $invoice->uid->toString()) . '.pdf',
            contents: $pdf,
            visibility: 'private',
            classification: 'confidential',
            entityType: $documentType,
            entityUid: $invoice->uid->toString(),
            metadata: [
                'source' => 'invoices',
                'documentType' => $documentType,
                'number' => $invoice->number,
                'documentSnapshot' => $snapshot,
            ],
            actorId: (int) $this->wire()->user->id,
        );
    }

    /**
     * @param DocumentLine[] $lines
     * @return array<string, mixed>
     */
    private function invoiceDocumentData(Invoice $invoice, array $lines): array
    {
        $customerKey = $invoice->customerType . ':' . $invoice->customerUid;

        return [
            'title' => $invoice->kind === 'credit_note' ? $this->_('Credit note') : $this->_('Invoice'),
            'kind' => $invoice->kind,
            'number' => $invoice->number,
            'issueDate' => $invoice->issueDate?->format('Y-m-d'),
            'dueDate' => $invoice->dueDate?->format('Y-m-d'),
            'creditedInvoiceUid' => $invoice->creditedInvoiceUid,
            'customer' => [
                'type' => $invoice->customerType,
                'uid' => $invoice->customerUid,
                'name' => $this->salesCustomerLabels()[$customerKey] ?? $invoice->customerUid,
            ],
            'currency' => $invoice->currencyCode,
            'lines' => array_map(static fn (DocumentLine $line): array => [
                'title' => $line->title,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unitCode,
                'unitPrice' => number_format($line->unitPrice->amountMinor() / 100, 2, '.', ''),
                'taxRate' => $line->taxRate,
                'total' => number_format($line->total()->amountMinor() / 100, 2, '.', ''),
            ], $lines),
            'subtotal' => number_format($invoice->subtotal->amountMinor() / 100, 2, '.', ''),
            'tax' => number_format($invoice->tax->amountMinor() / 100, 2, '.', ''),
            'total' => number_format($invoice->total->amountMinor() / 100, 2, '.', ''),
            'paid' => number_format($invoice->paid->amountMinor() / 100, 2, '.', ''),
            'due' => number_format($invoice->due->amountMinor() / 100, 2, '.', ''),
        ];
    }

    private function invoiceModule(): KontorInvoices
    {
        /** @var KontorInvoices $module */
        $module = $this->wire()->modules->get('KontorInvoices');

        return $module;
    }

    private function salesModule(): KontorSales
    {
        /** @var KontorSales $module */
        $module = $this->wire()->modules->get('KontorSales');

        return $module;
    }

    private function paymentModule(): KontorPayments
    {
        /** @var KontorPayments $module */
        $module = $this->wire()->modules->get('KontorPayments');

        return $module;
    }

    private function taskModule(): KontorTasks
    {
        /** @var KontorTasks $module */
        $module = $this->wire()->modules->get('KontorTasks');

        return $module;
    }

    private function crmModule(): KontorCRM
    {
        /** @var KontorCRM $module */
        $module = $this->wire()->modules->get('KontorCRM');

        return $module;
    }

    private function crmIntakeModule(): KontorCRMIntake
    {
        /** @var KontorCRMIntake $module */
        $module = $this->wire()->modules->get('KontorCRMIntake');

        return $module;
    }

    private function collaborationModule(): KontorCollaboration
    {
        /** @var KontorCollaboration $module */
        $module = $this->wire()->modules->get('KontorCollaboration');

        return $module;
    }

    private function dashboardModule(): KontorDashboard
    {
        /** @var KontorDashboard $module */
        $module = $this->wire()->modules->get('KontorDashboard');

        return $module;
    }

    private function reportsModule(): KontorReports
    {
        /** @var KontorReports $module */
        $module = $this->wire()->modules->get('KontorReports');

        return $module;
    }

    private function inventoryModule(): KontorInventory
    {
        /** @var KontorInventory $module */
        $module = $this->wire()->modules->get('KontorInventory');

        return $module;
    }

    private function purchasingModule(): KontorPurchasing
    {
        /** @var KontorPurchasing $module */
        $module = $this->wire()->modules->get('KontorPurchasing');

        return $module;
    }

    private function expensesModule(): KontorExpenses
    {
        /** @var KontorExpenses $module */
        $module = $this->wire()->modules->get('KontorExpenses');

        return $module;
    }

    private function projectsModule(): KontorProjects
    {
        /** @var KontorProjects $module */
        $module = $this->wire()->modules->get('KontorProjects');

        return $module;
    }

    private function workflowModule(): KontorWorkflow
    {
        /** @var KontorWorkflow $module */
        $module = $this->wire()->modules->get('KontorWorkflow');

        return $module;
    }

    private function demoModule(): KontorDemo
    {
        /** @var KontorDemo $module */
        $module = $this->wire()->modules->get('KontorDemo');

        return $module;
    }

    private function automationModule(): KontorAutomation
    {
        /** @var KontorAutomation $module */
        $module = $this->wire()->modules->get('KontorAutomation');

        return $module;
    }

    private function entitiesModule(): KontorEntities
    {
        /** @var KontorEntities $module */
        $module = $this->wire()->modules->get('KontorEntities');

        return $module;
    }

    private function apiModule(): KontorAPI
    {
        /** @var KontorAPI $module */
        $module = $this->wire()->modules->get('KontorAPI');

        return $module;
    }

    private function graphqlModule(): KontorGraphQL
    {
        /** @var KontorGraphQL $module */
        $module = $this->wire()->modules->get('KontorGraphQL');

        return $module;
    }

    private function marketplaceModule(): KontorMarketplace
    {
        /** @var KontorMarketplace $module */
        $module = $this->wire()->modules->get('KontorMarketplace');

        return $module;
    }

    private function mailModule(): KontorMail
    {
        /** @var KontorMail $module */
        $module = $this->wire()->modules->get('KontorMail');

        return $module;
    }

    private function portalModule(): KontorPortal
    {
        /** @var KontorPortal $module */
        $module = $this->wire()->modules->get('KontorPortal');

        return $module;
    }

    private function filesModule(): KontorFiles
    {
        /** @var KontorFiles $module */
        $module = $this->wire()->modules->get('KontorFiles');

        return $module;
    }

    private function cacheModule(): KontorCache
    {
        /** @var KontorCache $module */
        $module = $this->wire()->modules->get('KontorCache');

        return $module;
    }

    private function documentsModule(): KontorDocuments
    {
        /** @var KontorDocuments $module */
        $module = $this->wire()->modules->get('KontorDocuments');

        return $module;
    }

    private function aiModule(): KontorAI
    {
        /** @var KontorAI $module */
        $module = $this->wire()->modules->get('KontorAI');

        return $module;
    }

    private function ledgerModule(): KontorLedger
    {
        /** @var KontorLedger $module */
        $module = $this->wire()->modules->get('KontorLedger');

        return $module;
    }

    private function germanyModule(): KontorGermany
    {
        /** @var KontorGermany $module */
        $module = $this->wire()->modules->get('KontorGermany');

        return $module;
    }

    private function settingsModule(): KontorSettings
    {
        /** @var KontorSettings $module */
        $module = $this->wire()->modules->get('KontorSettings');

        return $module;
    }
}
