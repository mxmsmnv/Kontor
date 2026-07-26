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
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\HealthCheckRunner;
use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\Core\Application\ImportManager;
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
 * The single Kontor admin application. Business components provide the
 * domain services while this Process module owns navigation and UI.
 */
class ProcessKontor extends Process
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor',
            'summary' => 'Kontor ERP, CRM and business operations admin.',
            'version' => '134',
            'author' => 'Maxim Semenov',
            'icon' => 'cubes',
            'permission' => 'kontor-access',
            'requires' => ['Kontor'],
            'page' => [
                'name' => 'kontor',
                'title' => 'Kontor',
            ],
            'useNavJSON' => false,
            'nav' => [
                ['url' => '', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                ['url' => 'search/', 'label' => 'Search', 'icon' => 'search'],
                [
                    'url' => 'activity/',
                    'label' => 'Activity',
                    'icon' => 'history',
                    'permission' => 'kontor-audit-view',
                ],
                [
                    'url' => 'backups/',
                    'label' => 'Backups',
                    'icon' => 'database',
                    'permission' => 'kontor-backups-view',
                ],
                [
                    'url' => 'health/',
                    'label' => 'Health',
                    'icon' => 'heartbeat',
                    'permission' => 'kontor-health-view',
                ],
                [
                    'url' => 'queue/',
                    'label' => 'Queue',
                    'icon' => 'tasks',
                    'permission' => 'kontor-queue-view',
                ],
                [
                    'url' => 'organization/',
                    'label' => 'Organization',
                    'icon' => 'briefcase',
                    'permission' => 'kontor-admin',
                ],
                [
                    'url' => 'catalog/',
                    'label' => 'Catalog',
                    'icon' => 'cubes',
                    'permission' => 'kontor-catalog-item-view',
                ],
                [
                    'url' => 'inventory/',
                    'label' => 'Inventory',
                    'icon' => 'cube',
                    'permission' => 'kontor-inventory-stock-view',
                ],
                [
                    'url' => 'purchasing/',
                    'label' => 'Purchasing',
                    'icon' => 'truck',
                    'permission' => 'kontor-purchasing-po-view',
                ],
                [
                    'url' => 'expenses/',
                    'label' => 'Expenses',
                    'icon' => 'credit-card',
                    'permission' => 'kontor-expenses-expense-view',
                ],
                [
                    'url' => 'projects/',
                    'label' => 'Projects',
                    'icon' => 'tasks',
                    'permission' => 'kontor-projects-project-view',
                ],
                [
                    'url' => 'workflows/',
                    'label' => 'Workflows',
                    'icon' => 'sitemap',
                    'permission' => 'kontor-workflow-definition-view',
                ],
                [
                    'url' => 'automations/',
                    'label' => 'Automations',
                    'icon' => 'bolt',
                    'permission' => 'kontor-automation-rule-view',
                ],
                [
                    'url' => 'custom-entities/',
                    'label' => 'Custom entities',
                    'icon' => 'cube',
                    'permission' => 'kontor-entities-record-view',
                ],
                [
                    'url' => 'api/',
                    'label' => 'API',
                    'icon' => 'plug',
                    'permission' => 'kontor-api-token-manage',
                ],
                [
                    'url' => 'graphql/',
                    'label' => 'GraphQL',
                    'icon' => 'share-alt',
                    'permission' => 'kontor-api-token-manage',
                ],
                [
                    'url' => 'marketplace/',
                    'label' => 'Marketplace',
                    'icon' => 'shopping-cart',
                    'permission' => 'kontor-marketplace-advisory-view',
                ],
                [
                    'url' => 'mail/',
                    'label' => 'Mail',
                    'icon' => 'envelope',
                    'permission' => 'kontor-mail-message-view',
                ],
                [
                    'url' => 'portal/',
                    'label' => 'Portal',
                    'icon' => 'user-circle',
                    'permission' => 'kontor-portal-account-manage',
                ],
                [
                    'url' => 'files/',
                    'label' => 'Files',
                    'icon' => 'folder-open',
                    'permission' => 'kontor-files-file-view',
                ],
                [
                    'url' => 'cache/',
                    'label' => 'Cache',
                    'icon' => 'bolt',
                    'permission' => 'kontor-cache-view',
                ],
                [
                    'url' => 'documents/',
                    'label' => 'Documents',
                    'icon' => 'file-pdf-o',
                    'permission' => 'kontor-documents-template-view',
                ],
                [
                    'url' => 'ai/',
                    'label' => 'AI',
                    'icon' => 'magic',
                    'permission' => 'kontor-ai-action-approve',
                ],
                [
                    'url' => 'ledger/',
                    'label' => 'Ledger',
                    'icon' => 'balance-scale',
                    'permission' => 'kontor-ledger-entry-view',
                ],
                [
                    'url' => 'germany/',
                    'label' => 'Germany',
                    'icon' => 'flag',
                    'permission' => 'kontor-germany-view',
                ],
                [
                    'url' => 'contacts/',
                    'label' => 'Contacts',
                    'icon' => 'address-book',
                    'permission' => 'kontor-contacts-contact-view',
                ],
                [
                    'url' => 'companies/',
                    'label' => 'Companies',
                    'icon' => 'building',
                    'permission' => 'kontor-contacts-company-view',
                ],
                [
                    'url' => 'crm/',
                    'label' => 'CRM',
                    'icon' => 'handshake-o',
                    'permission' => 'kontor-crm-lead-view',
                ],
                [
                    'url' => 'sales/',
                    'label' => 'Sales',
                    'icon' => 'file-text-o',
                    'permission' => 'kontor-sales-quotation-view',
                ],
                [
                    'url' => 'invoices/',
                    'label' => 'Invoices',
                    'icon' => 'file-text',
                    'permission' => 'kontor-invoices-invoice-view',
                ],
                [
                    'url' => 'payments/',
                    'label' => 'Payments',
                    'icon' => 'money',
                    'permission' => 'kontor-payments-payment-view',
                ],
                [
                    'url' => 'tasks/',
                    'label' => 'Tasks',
                    'icon' => 'check-square-o',
                    'permission' => 'kontor-tasks-task-view',
                ],
                [
                    'url' => 'collaboration/',
                    'label' => 'Collaboration',
                    'icon' => 'comments-o',
                    'permission' => 'kontor-collaboration-comment-view',
                ],
                [
                    'url' => 'reports/',
                    'label' => 'Reports',
                    'icon' => 'bar-chart',
                    'permission' => 'kontor-reports-report-view',
                ],
                [
                    'url' => 'components/',
                    'label' => 'Components',
                    'icon' => 'cubes',
                    'permission' => 'kontor-components-view',
                ],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $moduleUrl = $this->wire()->config->urls->get('ProcessKontor');
        $version = (string) max(
            @filemtime(__DIR__ . '/assets/kontor.admin.css') ?: 0,
            @filemtime(__DIR__ . '/assets/kontor.admin.js') ?: 0,
            (int) self::getModuleInfo()['version'],
        );
        $this->wire()->config->styles->add($moduleUrl . 'assets/kontor.admin.css?v=' . $version);
        $this->wire()->config->scripts->add($moduleUrl . 'assets/kontor.admin.js?v=' . $version);
    }

    public function ___execute(): string
    {
        $this->setPageTitle($this->_('Kontor · Dashboard'));
        $components = $this->componentRegistry()->all();
        $contactsReady = $this->contactsReady();
        $catalogReady = $this->catalogReady();
        $dashboardReady = $this->dashboardReady();
        $organizationUid = $contactsReady || $catalogReady || $dashboardReady ? $this->organizationUid() : null;
        $contacts = $contactsReady ? $this->contactRepository()->countActive($organizationUid) : 0;
        $companies = $contactsReady ? $this->companyRepository()->countActive($organizationUid) : 0;
        $recentContacts = $contactsReady ? $this->contactRepository()->findAll($organizationUid, '', 6) : [];
        $user = $this->wire()->user;
        $canViewCatalog = $catalogReady
            && ($user->isSuperuser() || $user->hasPermission('kontor-catalog-item-view'));
        $catalogSummary = $canViewCatalog
            ? $this->catalogItemRepository()->summary($organizationUid)
            : [
                'products' => 0,
                'services' => 0,
                'archived' => 0,
                'inventoryTracked' => 0,
                'unpriced' => 0,
                'uncategorized' => 0,
            ];

        if ($canViewCatalog) {
            $catalogSummary['priceLists'] = $this->priceListRepository()->countMatching($organizationUid);
            $catalogSummary['expiredPriceLists'] = $this->priceListRepository()->countMatching(
                $organizationUid,
                validity: 'expired',
            );
            $catalogSummary['upcomingPriceLists'] = $this->priceListRepository()->countMatching(
                $organizationUid,
                validity: 'upcoming',
            );
        }
        $canViewActivity = $user->isSuperuser() || $user->hasPermission('kontor-audit-view');
        $canViewQueue = $user->isSuperuser() || $user->hasPermission('kontor-queue-view');
        $queueReady = $this->queueReady();
        $canViewPersonalDashboard = $dashboardReady
            && ($user->isSuperuser() || $user->hasPermission('kontor-dashboard-view'));
        $personalDashboard = null;
        $renderedPersonalDashboard = null;
        $availableDashboardWidgets = [];

        if ($canViewPersonalDashboard) {
            $dashboardModule = $this->dashboardModule();
            $personalDashboard = $dashboardModule->dashboardService()->dashboardFor(
                $organizationUid,
                (int) $user->id,
            );
            if ($personalDashboard !== null) {
                $renderedPersonalDashboard = $dashboardModule->dashboardService()->render(
                    $personalDashboard->uid->toString(),
                    $organizationUid,
                    (int) $user->id,
                );
            }
            $availableDashboardWidgets = $dashboardModule->widgetRegistry()->all();
        }

        return $this->renderTemplate('dashboard', [
            'components' => $components,
            'contactsReady' => $contactsReady,
            'contactCount' => $contacts,
            'companyCount' => $companies,
            'recentContacts' => $recentContacts,
            'catalogReady' => $catalogReady,
            'canViewCatalog' => $canViewCatalog,
            'canCreateCatalogItems' => $catalogReady
                && ($user->isSuperuser() || $user->hasPermission('kontor-catalog-item-create')),
            'catalogSummary' => $catalogSummary,
            'catalogLanguage' => $organizationUid !== null
                ? $this->organization()->defaultLanguage
                : 'en',
            'recentCatalogItems' => $canViewCatalog
                ? $this->catalogItemRepository()->findAll($organizationUid, limit: 5)
                : [],
            'canViewActivity' => $canViewActivity,
            'canViewBackups' => $user->isSuperuser() || $user->hasPermission('kontor-backups-view'),
            'canViewHealth' => $user->isSuperuser() || $user->hasPermission('kontor-health-view'),
            'canManageOrganization' => $user->isSuperuser() || $user->hasPermission('kontor-admin'),
            'queueReady' => $queueReady,
            'canViewQueue' => $canViewQueue,
            'recentActivity' => $canViewActivity
                ? $this->auditEventRepository()->findRecent($this->organizationInternalId(), '', 6)
                : [],
            'queueCounts' => $queueReady && $canViewQueue
                ? $this->jobRepository()->summaryCounts()
                : [],
            'dashboardReady' => $dashboardReady,
            'canViewPersonalDashboard' => $canViewPersonalDashboard,
            'canCreatePersonalDashboard' => $dashboardReady
                && ($user->isSuperuser() || $user->hasPermission('kontor-dashboard-create')),
            'canEditPersonalDashboard' => $dashboardReady
                && ($user->isSuperuser() || $user->hasPermission('kontor-dashboard-edit')),
            'personalDashboard' => $personalDashboard,
            'renderedPersonalDashboard' => $renderedPersonalDashboard,
            'availableDashboardWidgets' => $availableDashboardWidgets,
        ]);
    }

    public function ___executeDashboardSave(): void
    {
        $this->requirePost();
        $this->requireDashboard();
        $this->requirePermission('kontor-dashboard-create');
        $name = trim($this->wire()->sanitizer->text((string) $this->wire()->input->post('name')));
        if ($name === '') {
            throw new WireException($this->_('Dashboard name is required.'));
        }
        $dashboard = $this->dashboardModule()->dashboardService()->createPersonalDashboard(
            $this->organizationUid(),
            (int) $this->wire()->user->id,
            mb_substr($name, 0, 191),
            isDefault: true,
            createdBy: (int) $this->wire()->user->id,
        );
        $this->audit(
            'dashboard',
            'dashboard',
            $dashboard->uid->toString(),
            'created',
            current: ['name' => $dashboard->name, 'scope' => 'personal', 'isDefault' => true],
        );
        $this->message($this->_('Personal dashboard created.'));
        $this->wire()->session->redirect('../');
    }

    public function ___executeDashboardWidgetAction(): void
    {
        $this->requirePost();
        $this->requireDashboard();
        $this->requirePermission('kontor-dashboard-edit');
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['add', 'remove', 'move_left', 'move_right', 'wider', 'narrower']
        );
        $this->requireAction($action, ['add', 'remove', 'move_left', 'move_right', 'wider', 'narrower']);
        $dashboardUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('dashboard_uid')
        );
        $module = $this->dashboardModule();
        $dashboard = $module->dashboardRepository()->require($dashboardUid);
        if (
            !hash_equals($dashboard->organizationId, $this->organizationUid())
            || !$dashboard->isPersonal()
            || $dashboard->ownerUserId !== (int) $this->wire()->user->id
        ) {
            throw new WirePermissionException($this->_('This dashboard does not belong to the current user.'));
        }

        if ($action === 'add') {
            $widgetKey = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('widget_key')
            );
            $widget = $module->dashboardService()->addWidget(
                $this->organizationUid(),
                $dashboardUid,
                $widgetKey,
                sortOrder: count($module->widgetRepository()->forDashboard($dashboardUid)),
            );
        } else {
            $widgetUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('widget_uid')
            );
            $widget = $module->widgetRepository()->require($widgetUid);
            if (
                !hash_equals($widget->organizationId, $this->organizationUid())
                || !hash_equals($widget->dashboardUid, $dashboardUid)
            ) {
                throw new WirePermissionException($this->_('Widget does not belong to this dashboard.'));
            }

            if ($action === 'remove') {
                $module->dashboardService()->removeWidget($widgetUid);
            } elseif ($action === 'move_left' || $action === 'move_right') {
                $module->dashboardService()->moveWidget(
                    $widgetUid,
                    max(0, min(8, $widget->positionX + ($action === 'move_left' ? -1 : 1))),
                    $widget->positionY,
                );
            } else {
                $module->dashboardService()->resizeWidget(
                    $widgetUid,
                    max(2, min(12, $widget->width + ($action === 'narrower' ? -1 : 1))),
                    $widget->height,
                );
            }
        }

        $this->audit(
            'dashboard',
            'widget',
            $widget->uid->toString(),
            $action,
            current: ['dashboardUid' => $dashboardUid, 'widgetKey' => $widget->widgetKey],
        );
        $this->message($this->_('Dashboard layout updated.'));
        $this->wire()->session->redirect('../');
    }

    public function ___executeContacts(): string
    {
        $this->requirePermission('kontor-contacts-contact-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Contacts'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $status = $showArchived ? '' : ($this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'inactive']
        ) ?? '');
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalRecords = $this->contactRepository()->countMatching(
            $organizationUid,
            $query,
            $showArchived,
            $status,
        );
        $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));

        return $this->renderTemplate('contacts', [
            'contacts' => $showArchived
                ? $this->contactRepository()->findArchived(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                    $status,
                )
                : $this->contactRepository()->findAll(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                    $status,
                ),
            'query' => $query,
            'selectedStatus' => $status,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
        ]);
    }

    public function ___executeContact(): string
    {
        $this->requireContacts();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $contact = $id !== '' ? $this->contactRepository()->require($id) : null;
        $this->requirePermission($contact === null
            ? 'kontor-contacts-contact-create'
            : 'kontor-contacts-contact-edit');
        $this->setPageTitle($contact === null
            ? $this->_('Kontor · New contact')
            : sprintf($this->_('Kontor · %s'), $contact->displayName));

        $isNew = $contact === null;
        $previous = $contact === null ? null : $this->contactAuditSnapshot($contact);
        $form = $this->buildContactForm($contact);
        $duplicates = [];

        if ($this->wire()->input->post('submit_save')) {
            $form->processInput($this->wire()->input->post);

            if (!$form->getErrors()) {
                if ($contact === null) {
                    $duplicates = $this->duplicateDetector()->findDuplicates(
                        $this->organizationUid(),
                        $this->formValue($form, 'email'),
                        $this->formValue($form, 'phone')
                    );
                }

                if ($duplicates !== [] && !$this->wire()->input->post('confirm_duplicate')) {
                    $this->addDuplicateConfirmation($form);
                } else {
                    $contact = $this->saveContactFromForm($form, $contact);
                    $this->audit(
                        component: 'contacts',
                        entityType: 'contact',
                        entityUid: $contact->uid->toString(),
                        action: $isNew ? 'created' : 'updated',
                        previous: $previous,
                        current: $this->contactAuditSnapshot($contact),
                    );
                    $this->message($this->_('Contact saved.'));
                    $this->wire()->session->redirect('../contact/?id=' . rawurlencode($contact->uid->toString()));
                }
            }
        }

        $relationships = $contact === null ? [] : $this->contactRelationships($contact);
        $aiSummary = $contact === null
            ? null
            : $this->wire()->session->get('kontorContactAISummary:' . $contact->uid->toString());
        if ($contact !== null) {
            $this->wire()->session->set('kontorContactAISummary:' . $contact->uid->toString(), null);
        }

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../contacts/',
            'backLabel' => $this->_('Back to contacts'),
            'eyebrow' => $this->_('Contacts'),
            'title' => $contact?->displayName ?: $this->_('Create contact'),
            'description' => $contact === null
                ? $this->_('Add a person to your shared customer directory.')
                : $this->_('Keep identity, communication and assignment details in one place.'),
            'entity' => $contact,
            'entityType' => 'contact',
            'relationships' => $relationships,
            'availableCompanies' => $contact === null
                ? []
                : $this->companyRepository()->findAll($this->organizationUid(), '', 250),
            'addresses' => $contact === null
                ? []
                : $this->addressRepository()->forOwner('contact', $contact->uid->toString()),
            'duplicates' => $duplicates,
            'tags' => $contact === null
                ? []
                : $this->tagService()->tagsFor(
                    $this->organizationUid(),
                    'contact',
                    $contact->uid->toString()
                ),
            'aiReady' => $this->aiReady(),
            'aiSummary' => is_array($aiSummary) ? $aiSummary : null,
        ]);
    }

    public function ___executeContactAISummary(): void
    {
        $this->requirePost();
        $this->requireContacts();
        $this->requireAI();
        $this->requirePermission('kontor-contacts-contact-edit');
        $this->requirePermission('kontor-ai-action-approve');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('contact_uid')
        );
        $contact = $this->contactRepository()->require($uid);
        $this->requireSameOrganization($contact->organizationId);
        $relationships = $this->contactRelationships($contact);
        $tags = $this->tagService()->tagsFor(
            $contact->organizationId,
            'contact',
            $contact->uid->toString(),
        );
        $text = implode("\n", array_filter([
            'Contact: ' . $contact->displayName,
            $contact->email !== null ? 'Email: ' . $contact->email : null,
            $contact->phone !== null ? 'Phone: ' . $contact->phone : null,
            $contact->jobTitle !== null ? 'Job title: ' . $contact->jobTitle : null,
            $contact->notes !== null ? 'Notes: ' . $contact->notes : null,
            $tags !== [] ? 'Tags: ' . implode(', ', $tags) : null,
            $relationships !== [] ? 'Companies: ' . implode(', ', array_map(
                static fn (array $relationship): string => $relationship['entity']->legalName,
                $relationships,
            )) : null,
        ]));
        $simulate = (bool) $this->wire()->input->post('simulate');
        $gateway = $simulate
            ? $this->aiModule()->previewGateway()
            : $this->aiModule()->gateway();
        $response = (new \Kontor\AI\Application\SummaryService($gateway))->summarize(
            $contact->organizationId,
            $text,
            (string) $this->wire()->user->id,
        );
        if (!$response->success) {
            throw new WireException(sprintf(
                $this->_('Contact summary failed: %s'),
                $response->errorMessage ?? $this->_('no AI provider is available'),
            ));
        }
        $this->wire()->session->set('kontorContactAISummary:' . $uid, [
            'summary' => (string) ($response->output['summary'] ?? ''),
            'characters' => (int) ($response->output['characters'] ?? mb_strlen($text)),
            'simulated' => $simulate,
        ]);
        $this->audit('ai', 'contact', $uid, 'summarized', metadata: [
            'simulated' => $simulate,
            'characters' => (int) ($response->output['characters'] ?? mb_strlen($text)),
        ]);
        $this->message($simulate
            ? $this->_('Local AI contact brief generated.')
            : $this->_('AI contact brief generated.'));
        $this->wire()->session->redirect('../contact/?id=' . rawurlencode($uid));
    }

    public function ___executeCompanies(): string
    {
        $this->requirePermission('kontor-contacts-company-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Companies'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $status = $showArchived ? '' : ($this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'inactive']
        ) ?? '');
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalRecords = $this->companyRepository()->countMatching(
            $organizationUid,
            $query,
            $showArchived,
            $status,
        );
        $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));

        return $this->renderTemplate('companies', [
            'companies' => $showArchived
                ? $this->companyRepository()->findArchived(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                    $status,
                )
                : $this->companyRepository()->findAll(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                    $status,
                ),
            'query' => $query,
            'selectedStatus' => $status,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
        ]);
    }

    public function ___executeCrm(): string
    {
        $this->requirePermission('kontor-crm-lead-view');
        $this->requireCrm();
        $this->setPageTitle($this->_('Kontor · CRM'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['new', 'contacted', 'qualified', 'converted', 'lost']
        );
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $repository = $crm->leadRepository();
        $pageSize = 25;
        $totalRecords = $repository->countMatching(
            $this->organizationUid(),
            $query,
            $status,
            $showArchived,
        );
        $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));

        return $this->renderTemplate('crm', [
            'leads' => $repository->findMatching(
                $this->organizationUid(),
                $query,
                $status,
                $showArchived,
                $pageSize,
                ($page - 1) * $pageSize,
            ),
            'query' => $query,
            'selectedStatus' => $status,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
        ]);
    }

    public function ___executeCrmLead(): string
    {
        $this->requireCrm();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $lead = $id !== '' ? $crm->leadRepository()->require($id) : null;

        if ($lead !== null) {
            $this->requireSameOrganization($lead->organizationId);
        }

        $this->requirePermission($lead === null
            ? 'kontor-crm-lead-create'
            : 'kontor-crm-lead-edit');
        $this->setPageTitle($lead === null
            ? $this->_('Kontor · New lead')
            : sprintf($this->_('Kontor · %s'), $lead->title));
        $values = [
            'title' => $lead?->title ?? '',
            'contactUid' => $lead?->contactUid ?? '',
            'companyUid' => $lead?->companyUid ?? '',
            'source' => $lead?->source ?? '',
            'status' => $lead?->status ?? 'new',
            'priority' => $lead?->priority ?? 'medium',
            'estimatedAmount' => $lead?->estimatedValue !== null
                ? number_format($lead->estimatedValue->amountMinor() / 100, 2, '.', '')
                : '',
            'currency' => $lead?->estimatedValue?->currencyCode() ?? 'EUR',
            'nextActionAt' => $lead?->nextActionAt?->format('Y-m-d\TH:i') ?? '',
            'description' => $lead?->description ?? '',
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'title' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('title')),
                'contactUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('contact_uid')),
                'companyUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('company_uid')),
                'source' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('source')),
                'status' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('status'),
                    ['new', 'contacted', 'qualified', 'converted', 'lost']
                ) ?? 'new',
                'priority' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('priority'),
                    ['low', 'medium', 'high', 'urgent']
                ) ?? 'medium',
                'estimatedAmount' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('estimated_amount')
                ),
                'currency' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency')
                )),
                'nextActionAt' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('next_action_at')
                ),
                'description' => $this->wire()->sanitizer->textarea(
                    (string) $this->wire()->input->post('description')
                ),
            ];

            if ($values['title'] === '') {
                $error = $this->_('Lead title is required.');
            } elseif ($values['estimatedAmount'] !== ''
                && !is_numeric(str_replace(',', '.', $values['estimatedAmount']))) {
                $error = $this->_('Estimated value must be a number.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currency']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            }

            if ($error === '' && $values['contactUid'] !== '') {
                $contact = $this->contactRepository()->find($values['contactUid']);
                if ($contact === null || !hash_equals($this->organizationUid(), $contact->organizationId)) {
                    $error = $this->_('Selected contact is invalid.');
                }
            }

            if ($error === '' && $values['companyUid'] !== '') {
                $company = $this->companyRepository()->find($values['companyUid']);
                if ($company === null || !hash_equals($this->organizationUid(), $company->organizationId)) {
                    $error = $this->_('Selected company is invalid.');
                }
            }

            $estimatedValue = null;
            $nextActionAt = null;

            if ($error === '' && $values['estimatedAmount'] !== '') {
                $estimatedValue = Money::ofMinor(
                    (int) round((float) str_replace(',', '.', $values['estimatedAmount']) * 100),
                    $values['currency']
                );
            }

            if ($error === '' && $values['nextActionAt'] !== '') {
                try {
                    $nextActionAt = new \DateTimeImmutable($values['nextActionAt']);
                } catch (\Throwable) {
                    $error = $this->_('Next action date is invalid.');
                }
            }

            if ($error === '') {
                $isNew = $lead === null;
                $lead ??= Lead::create($this->organizationUid(), $values['title']);
                $lead->title = $values['title'];
                $lead->contactUid = $values['contactUid'] ?: null;
                $lead->companyUid = $values['companyUid'] ?: null;
                $lead->source = $values['source'] ?: null;
                $lead->status = $values['status'];
                $lead->priority = $values['priority'];
                $lead->estimatedValue = $estimatedValue;
                $lead->nextActionAt = $nextActionAt;
                $lead->description = $values['description'] ?: null;
                $crm->leadRepository()->save($lead);
                $this->audit(
                    'crm',
                    'lead',
                    $lead->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    current: [
                        'title' => $lead->title,
                        'status' => $lead->status,
                        'priority' => $lead->priority,
                    ],
                );
                $this->message($this->_('Lead saved.'));
                $this->wire()->session->redirect(
                    '../crm-lead/?id=' . rawurlencode($lead->uid->toString())
                );
            }
        }

        return $this->renderTemplate('crm-lead', [
            'lead' => $lead,
            'values' => $values,
            'error' => $error,
            'contacts' => $this->contactRepository()->findAll($this->organizationUid(), limit: 250),
            'companies' => $this->companyRepository()->findAll($this->organizationUid(), limit: 250),
        ]);
    }

    public function ___executeCrmLeadAction(): void
    {
        $this->requirePost();
        $this->requireCrm();
        $this->requirePermission('kontor-crm-lead-archive');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $lead = $crm->leadRepository()->require($id);
        $this->requireSameOrganization($lead->organizationId);
        $action === 'restore'
            ? $crm->leadRepository()->restore($id)
            : $crm->leadRepository()->archive($id);
        $this->audit('crm', 'lead', $id, $action === 'restore' ? 'restored' : 'archived');
        $this->message($action === 'restore' ? $this->_('Lead restored.') : $this->_('Lead archived.'));
        $parameters = array_filter([
            'q' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('return_q')),
            'status' => $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('return_status'),
                ['new', 'contacted', 'qualified', 'converted', 'lost']
            ),
            'archived' => (string) $this->wire()->input->post('return_archived') === '1' ? 1 : null,
        ], static fn (string|int|null $value): bool => $value !== null && $value !== '');
        $this->wire()->session->redirect(
            '../crm/' . ($parameters === [] ? '' : '?' . http_build_query($parameters))
        );
    }

    public function ___executeCrmDeals(): string
    {
        $this->requireCrm();
        $this->requirePermission('kontor-crm-deal-view');
        $this->setPageTitle($this->_('Kontor · CRM deals'));
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $pipelines = $crm->pipelineRepository()->forOrganization($this->organizationUid());
        $pipelineId = $this->wire()->sanitizer->text((string) $this->wire()->input->get('pipeline'));
        $pipeline = null;

        if ($pipelineId !== '') {
            $pipeline = $crm->pipelineRepository()->require($pipelineId);
            $this->requireSameOrganization($pipeline->organizationId);
        } elseif ($pipelines !== []) {
            $pipeline = $pipelines[0];
        }

        return $this->renderTemplate('crm-deals', [
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
            'columns' => $pipeline !== null
                ? $crm->kanbanBoard()->board($pipeline->uid->toString())
                : [],
        ]);
    }

    public function ___executeCrmPipeline(): string
    {
        $this->requireCrm();
        $this->requirePermission('kontor-crm-pipeline-admin');
        $this->setPageTitle($this->_('Kontor · New CRM pipeline'));
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $values = [
            'name' => '',
            'isDefault' => $crm->pipelineRepository()->forOrganization($this->organizationUid()) === [],
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'name' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('name')),
                'isDefault' => (string) $this->wire()->input->post('is_default') === '1',
            ];

            if ($values['name'] === '') {
                $error = $this->_('Pipeline name is required.');
            }

            if ($error === '') {
                $pipeline = Pipeline::create(
                    $this->organizationUid(),
                    $values['name'],
                    isDefault: $values['isDefault'],
                );
                $crm->pipelineRepository()->save($pipeline);
                $stages = [
                    ['incoming', 'Incoming', 10, 'open', '#6b7280'],
                    ['qualified', 'Qualified', 30, 'open', '#2563eb'],
                    ['proposal', 'Proposal', 65, 'open', '#7c3aed'],
                    ['won', 'Won', 100, 'won', '#15803d'],
                    ['lost', 'Lost', 0, 'lost', '#b91c1c'],
                ];
                foreach ($stages as $sortOrder => [$key, $label, $probability, $stateType, $color]) {
                    $crm->stageRepository()->save(Stage::create(
                        $pipeline->uid->toString(),
                        $key,
                        ['en' => $label],
                        $probability,
                        $sortOrder + 1,
                        $stateType,
                        $color,
                    ));
                }
                $this->audit(
                    'crm',
                    'pipeline',
                    $pipeline->uid->toString(),
                    'created',
                    current: ['name' => $pipeline->name, 'stages' => count($stages)],
                );
                $this->message($this->_('Pipeline created with five standard stages.'));
                $this->wire()->session->redirect(
                    '../crm-deals/?pipeline=' . rawurlencode($pipeline->uid->toString())
                );
            }
        }

        return $this->renderTemplate('crm-pipeline', [
            'values' => $values,
            'error' => $error,
        ]);
    }

    public function ___executeCrmDeal(): string
    {
        $this->requireCrm();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $deal = $id !== '' ? $crm->dealRepository()->require($id) : null;

        if ($deal !== null) {
            $this->requireSameOrganization($deal->organizationId);
        }

        $this->requirePermission($deal === null
            ? 'kontor-crm-deal-create'
            : 'kontor-crm-deal-edit');
        $pipelines = $crm->pipelineRepository()->forOrganization($this->organizationUid());
        $requestedPipeline = $this->wire()->sanitizer->text(
            (string) ($this->wire()->input->post('pipeline_uid') ?: $this->wire()->input->get('pipeline'))
        );
        $pipelineUid = $deal?->pipelineUid
            ?? ($requestedPipeline !== ''
                ? $requestedPipeline
                : (($pipelines[0] ?? null)?->uid->toString() ?? ''));
        $stages = $pipelineUid !== '' ? $crm->stageRepository()->forPipeline($pipelineUid) : [];
        $values = [
            'title' => $deal?->title ?? '',
            'pipelineUid' => $pipelineUid,
            'stageUid' => $deal?->stageUid ?? ($stages[0]?->uid->toString() ?? ''),
            'contactUid' => $deal?->contactUid ?? '',
            'companyUid' => $deal?->companyUid ?? '',
            'source' => $deal?->source ?? '',
            'valueAmount' => $deal?->value !== null
                ? number_format($deal->value->amountMinor() / 100, 2, '.', '')
                : '',
            'currency' => $deal?->value?->currencyCode() ?? 'EUR',
            'probability' => $deal?->probability !== null ? (string) $deal->probability : '',
            'expectedCloseDate' => $deal?->expectedCloseDate?->format('Y-m-d') ?? '',
            'description' => $deal?->description ?? '',
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'title' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('title')),
                'pipelineUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('pipeline_uid')),
                'stageUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('stage_uid')),
                'contactUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('contact_uid')),
                'companyUid' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('company_uid')),
                'source' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('source')),
                'valueAmount' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('value_amount')),
                'currency' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency')
                )),
                'probability' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('probability')),
                'expectedCloseDate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('expected_close_date')
                ),
                'description' => $this->wire()->sanitizer->textarea(
                    (string) $this->wire()->input->post('description')
                ),
            ];

            $pipeline = $crm->pipelineRepository()->find($values['pipelineUid']);
            $stage = $crm->stageRepository()->find($values['stageUid']);
            if ($values['title'] === '') {
                $error = $this->_('Deal title is required.');
            } elseif ($pipeline === null || !hash_equals($this->organizationUid(), $pipeline->organizationId)) {
                $error = $this->_('Selected pipeline is invalid.');
            } elseif ($deal !== null && !hash_equals($deal->pipelineUid, $values['pipelineUid'])) {
                $error = $this->_('An existing deal cannot be moved to another pipeline.');
            } elseif ($stage === null || !hash_equals($values['pipelineUid'], $stage->pipelineUid)) {
                $error = $this->_('Selected stage is invalid.');
            } elseif (($deal === null || $deal->isOpen()) && $stage->stateType !== 'open') {
                $error = $this->_('Open deals must use an open pipeline stage.');
            } elseif ($values['valueAmount'] !== ''
                && !is_numeric(str_replace(',', '.', $values['valueAmount']))) {
                $error = $this->_('Deal value must be a number.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currency']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            } elseif ($values['probability'] !== ''
                && (!ctype_digit($values['probability'])
                    || (int) $values['probability'] < 0
                    || (int) $values['probability'] > 100)) {
                $error = $this->_('Probability must be between 0 and 100.');
            }

            if ($error === '' && $values['contactUid'] !== '') {
                $contact = $this->contactRepository()->find($values['contactUid']);
                if ($contact === null || !hash_equals($this->organizationUid(), $contact->organizationId)) {
                    $error = $this->_('Selected contact is invalid.');
                }
            }
            if ($error === '' && $values['companyUid'] !== '') {
                $company = $this->companyRepository()->find($values['companyUid']);
                if ($company === null || !hash_equals($this->organizationUid(), $company->organizationId)) {
                    $error = $this->_('Selected company is invalid.');
                }
            }

            $value = null;
            $expectedCloseDate = null;
            if ($error === '' && $values['valueAmount'] !== '') {
                $value = Money::ofMinor(
                    (int) round((float) str_replace(',', '.', $values['valueAmount']) * 100),
                    $values['currency']
                );
            }
            if ($error === '' && $values['expectedCloseDate'] !== '') {
                try {
                    $expectedCloseDate = new \DateTimeImmutable($values['expectedCloseDate']);
                } catch (\Throwable) {
                    $error = $this->_('Expected close date is invalid.');
                }
            }

            if ($error === '') {
                $isNew = $deal === null;
                $deal ??= Deal::create(
                    $this->organizationUid(),
                    $values['pipelineUid'],
                    $values['stageUid'],
                    $values['title'],
                );
                $deal->pipelineUid = $values['pipelineUid'];
                $deal->stageUid = $values['stageUid'];
                $deal->title = $values['title'];
                $deal->contactUid = $values['contactUid'] ?: null;
                $deal->companyUid = $values['companyUid'] ?: null;
                $deal->source = $values['source'] ?: null;
                $deal->value = $value;
                $deal->probability = $values['probability'] !== '' ? (int) $values['probability'] : null;
                $deal->expectedCloseDate = $expectedCloseDate;
                $deal->description = $values['description'] ?: null;
                $crm->dealRepository()->save($deal);
                $this->audit(
                    'crm',
                    'deal',
                    $deal->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    current: ['title' => $deal->title, 'stageUid' => $deal->stageUid],
                );
                $this->message($this->_('Deal saved.'));
                $this->wire()->session->redirect(
                    '../crm-deal/?id=' . rawurlencode($deal->uid->toString())
                );
            }
            $pipelineUid = $values['pipelineUid'];
            $stages = $pipelineUid !== '' ? $crm->stageRepository()->forPipeline($pipelineUid) : [];
        }

        $this->setPageTitle($deal === null
            ? $this->_('Kontor · New deal')
            : sprintf($this->_('Kontor · %s'), $deal->title));

        return $this->renderTemplate('crm-deal', [
            'deal' => $deal,
            'values' => $values,
            'pipelines' => $pipelines,
            'stages' => $stages,
            'contacts' => $this->contactRepository()->findAll($this->organizationUid(), limit: 250),
            'companies' => $this->companyRepository()->findAll($this->organizationUid(), limit: 250),
            'dealQuotations' => $deal !== null
                && $this->salesReady()
                && $this->can('kontor-sales-quotation-view')
                ? $this->salesModule()->quotationRepository()->forDeal(
                    $this->organizationUid(),
                    $deal->uid->toString(),
                )
                : [],
            'canCreateQuotation' => $deal !== null
                && $deal->status === 'won'
                && ($deal->companyUid !== null || $deal->contactUid !== null)
                && $this->salesReady()
                && $this->can('kontor-sales-quotation-create'),
            'error' => $error,
        ]);
    }

    public function ___executeCrmDealAction(): void
    {
        $this->requirePost();
        $this->requireCrm();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['move', 'won', 'lost', 'archive', 'restore']
        );
        $this->requireAction($action, ['move', 'won', 'lost', 'archive', 'restore']);
        $permissions = [
            'move' => 'kontor-crm-deal-move',
            'won' => 'kontor-crm-deal-close-won',
            'lost' => 'kontor-crm-deal-close-lost',
            'archive' => 'kontor-crm-deal-edit',
            'restore' => 'kontor-crm-deal-edit',
        ];
        $this->requirePermission($permissions[$action]);
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $deal = $crm->dealRepository()->require($id);
        $this->requireSameOrganization($deal->organizationId);

        if ($action === 'move') {
            $stageUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('stage_uid'));
            $crm->crmService()->moveDealToStage($id, $stageUid);
        } elseif ($action === 'won') {
            $crm->crmService()->closeDealWon($id);
        } elseif ($action === 'lost') {
            $crm->crmService()->closeDealLost(
                $id,
                $this->wire()->sanitizer->text((string) $this->wire()->input->post('lost_reason')) ?: null,
            );
        } elseif ($action === 'restore') {
            $crm->dealRepository()->restore($id);
        } else {
            $crm->dealRepository()->archive($id);
        }

        $this->audit('crm', 'deal', $id, $action);
        $this->message($this->_('Deal updated.'));
        $this->wire()->session->redirect(
            '../crm-deals/?pipeline=' . rawurlencode($deal->pipelineUid)
        );
    }

    public function ___executeSales(): string
    {
        $this->requireSales();
        $this->requirePermission('kontor-sales-quotation-view');
        $this->setPageTitle($this->_('Kontor · Sales'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');

        return $this->renderTemplate('sales', [
            'quotations' => $sales->quotationRepository()->findMatching(
                $this->organizationUid(),
                limit: 50,
            ),
            'orders' => $this->wire()->user->hasPermission('kontor-sales-order-view')
                ? $sales->orderRepository()->findMatching($this->organizationUid(), limit: 50)
                : [],
            'customerLabels' => $this->salesCustomerLabels(),
        ]);
    }

    public function ___executeSalesQuotation(): string
    {
        $this->requireSales();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $quotation = $id !== '' ? $sales->quotationRepository()->require($id) : null;

        if ($quotation !== null) {
            $this->requirePermission('kontor-sales-quotation-view');
            $this->requireSameOrganization($quotation->organizationId);
        } else {
            $this->requirePermission('kontor-sales-quotation-create');
        }

        $sourceDeal = null;
        $sourceDealError = '';
        if ($quotation !== null && $quotation->dealUid !== null
            && $this->crmReady() && $this->can('kontor-crm-deal-view')) {
            /** @var KontorCRM $crm */
            $crm = $this->wire()->modules->get('KontorCRM');
            $candidate = $crm->dealRepository()->find($quotation->dealUid);
            if ($candidate !== null && hash_equals($this->organizationUid(), $candidate->organizationId)) {
                $sourceDeal = $candidate;
            }
        } elseif ($quotation === null) {
            $dealUid = $this->wire()->sanitizer->text((string) (
                $this->wire()->input->post('deal_uid') ?: $this->wire()->input->get('deal')
            ));
            if ($dealUid !== '') {
                $this->requireCrm();
                $this->requirePermission('kontor-crm-deal-view');
                /** @var KontorCRM $crm */
                $crm = $this->wire()->modules->get('KontorCRM');
                $sourceDeal = $crm->dealRepository()->require($dealUid);
                $this->requireSameOrganization($sourceDeal->organizationId);
                if ($sourceDeal->status !== 'won') {
                    $sourceDealError = $this->_('Only won deals can become quotations.');
                } elseif ($sourceDeal->companyUid === null && $sourceDeal->contactUid === null) {
                    $sourceDealError = $this->_('Add a contact or company to the won deal first.');
                }
            }
        }

        $values = [
            'customer' => $sourceDeal?->companyUid !== null
                ? 'company:' . $sourceDeal->companyUid
                : ($sourceDeal?->contactUid !== null ? 'contact:' . $sourceDeal->contactUid : ''),
            'currency' => $sourceDeal?->value?->currencyCode() ?? 'EUR',
            'validUntil' => '',
            'language' => 'en',
            'lineTitle' => $sourceDeal?->title ?? '',
            'quantity' => '1',
            'unitCode' => 'pcs',
            'unitPrice' => $sourceDeal?->value !== null
                ? number_format($sourceDeal->value->amountMinor() / 100, 2, '.', '')
                : '',
            'taxRate' => '0',
        ];
        $error = $sourceDealError;

        if ($quotation === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'customer' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('customer')),
                'currency' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency')
                )),
                'validUntil' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('valid_until')
                ),
                'language' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('language'),
                    ['en', 'de', 'fr', 'es']
                ) ?? 'en',
                'lineTitle' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('line_title')
                ),
                'quantity' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('quantity')
                ),
                'unitCode' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_code')
                ),
                'unitPrice' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_price')
                ),
                'taxRate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('tax_rate')
                ),
            ];
            [$customerType, $customerUid] = array_pad(explode(':', $values['customer'], 2), 2, '');

            if ($error !== '') {
                // Keep the source-deal validation error.
            } elseif (!isset($this->salesCustomerLabels()[$values['customer']])) {
                $error = $this->_('Select a valid customer.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currency']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            } elseif ($values['lineTitle'] === '') {
                $error = $this->_('The first line title is required.');
            } elseif (!is_numeric($values['quantity']) || (float) $values['quantity'] <= 0) {
                $error = $this->_('Quantity must be greater than zero.');
            } elseif (!is_numeric(str_replace(',', '.', $values['unitPrice']))
                || (float) str_replace(',', '.', $values['unitPrice']) < 0) {
                $error = $this->_('Unit price must be zero or greater.');
            } elseif (!is_numeric($values['taxRate'])
                || (float) $values['taxRate'] < 0
                || (float) $values['taxRate'] > 100) {
                $error = $this->_('Tax rate must be between 0 and 100.');
            } elseif ($values['unitCode'] === '') {
                $error = $this->_('Unit code is required.');
            }

            $validUntil = null;
            if ($error === '' && $values['validUntil'] !== '') {
                try {
                    $validUntil = new \DateTimeImmutable($values['validUntil']);
                } catch (\Throwable) {
                    $error = $this->_('Valid-until date is invalid.');
                }
            }

            if ($error === '') {
                $quotation = Quotation::create(
                    $this->organizationUid(),
                    $customerType,
                    $customerUid,
                    $values['currency'],
                    contactUid: $sourceDeal?->contactUid
                        ?? ($customerType === 'contact' ? $customerUid : null),
                    dealUid: $sourceDeal?->uid->toString(),
                    validUntil: $validUntil,
                    documentLanguage: $values['language'],
                );
                $line = DocumentLine::create(
                    $this->organizationUid(),
                    'quotation',
                    $quotation->uid->toString(),
                    $values['lineTitle'],
                    (float) $values['quantity'],
                    Money::ofMinor(
                        (int) round((float) str_replace(',', '.', $values['unitPrice']) * 100),
                        $values['currency']
                    ),
                    unitCode: $values['unitCode'],
                    taxRate: (float) $values['taxRate'],
                );
                $pdo = $this->wire()->database->pdo();
                $pdo->beginTransaction();
                try {
                    $sales->quotationRepository()->save($quotation);
                    $sales->documentLineRepository()->save($line);
                    $quotation->applyTotalsFromLines([$line]);
                    $sales->quotationRepository()->save($quotation);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->audit(
                    'sales',
                    'quotation',
                    $quotation->uid->toString(),
                    'created',
                    current: [
                        'customerType' => $quotation->customerType,
                        'customerUid' => $quotation->customerUid,
                        'dealUid' => $quotation->dealUid,
                        'totalMinor' => $quotation->total->amountMinor(),
                    ],
                );
                $this->message($this->_('Quotation draft created.'));
                $this->wire()->session->redirect(
                    '../sales-quotation/?id=' . rawurlencode($quotation->uid->toString())
                );
            }
        }

        $lines = $quotation !== null
            ? $sales->documentLineRepository()->forDocument('quotation', $quotation->uid->toString())
            : [];
        $existingOrder = $quotation !== null
            ? $sales->orderRepository()->findByQuotation($quotation->uid->toString())
            : null;
        $quotationTemplate = null;
        if ($quotation !== null && $this->documentsReady()) {
            $quotationTemplate = $quotation->templateUid !== null
                ? $this->documentsModule()->templateRepository()->find($quotation->templateUid)
                : $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $quotation->organizationId,
                    'quotation.standard',
                    $quotation->documentLanguage,
                );
        }
        $issuedFiles = $quotation !== null && $this->filesReady()
            ? $this->filesModule()->fileManager()->forEntity(
                'quotation',
                $quotation->uid->toString(),
                $quotation->organizationId,
            )
            : [];
        $this->setPageTitle($quotation === null
            ? $this->_('Kontor · New quotation')
            : sprintf($this->_('Kontor · %s'), $quotation->number ?? $this->_('Draft quotation')));

        return $this->renderTemplate('sales-quotation', [
            'quotation' => $quotation,
            'lines' => $lines,
            'existingOrder' => $existingOrder,
            'quotationTemplate' => $quotationTemplate,
            'issuedFile' => $issuedFiles[0] ?? null,
            'values' => $values,
            'customers' => $this->salesCustomerLabels(),
            'sourceDeal' => $sourceDeal,
            'mailReady' => $this->mailReady(),
            'mailboxes' => $quotation !== null && $this->mailReady()
                ? array_values(array_filter(
                    $this->mailModule()->mailboxRepository()->forOrganization($quotation->organizationId),
                    static fn (\Kontor\Mail\Domain\Mailbox $mailbox): bool => $mailbox->isActive(),
                ))
                : [],
            'customerEmail' => $quotation !== null
                ? $this->quotationCustomerEmail($quotation)
                : '',
            'error' => $error,
        ]);
    }

    public function ___executeSalesQuotationAction(): void
    {
        $this->requirePost();
        $this->requireSales();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['issue', 'send', 'accept', 'convert', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['issue', 'send', 'accept', 'convert', 'cancel', 'archive', 'restore']);
        $permissions = [
            'issue' => 'kontor-sales-quotation-issue',
            'send' => 'kontor-sales-quotation-send',
            'accept' => 'kontor-sales-quotation-accept',
            'convert' => 'kontor-sales-order-create',
            'cancel' => 'kontor-sales-quotation-cancel',
            'archive' => 'kontor-sales-quotation-edit',
            'restore' => 'kontor-sales-quotation-edit',
        ];
        $this->requirePermission($permissions[$action]);
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $quotation = $sales->quotationRepository()->require($id);
        $this->requireSameOrganization($quotation->organizationId);

        if ($action === 'issue') {
            $this->requireDocuments();
            $this->requireFiles();
            $template = $this->documentsModule()->templateRepository()->findCurrentVersion(
                $quotation->organizationId,
                'quotation.standard',
                $quotation->documentLanguage,
            );
            if ($template === null) {
                throw new WireException($this->_(
                    'Publish an active quotation.standard document template before issuing this quotation.'
                ));
            }

            $pdo = $this->wire()->database->pdo();
            $pdo->beginTransaction();
            try {
                $issued = $sales->quotationWorkflow()->issue($id);
                $lines = $sales->documentLineRepository()->forDocument('quotation', $id);
                $data = $this->quotationDocumentData($issued, $lines);
                $snapshot = $this->documentsModule()->snapshotBuilder()->build($template, $data);
                $pdf = $this->documentsModule()->renderService()->renderPdf($template, $data);
                $issued->attachIssuedDocument($template->uid->toString(), $snapshot);
                $sales->quotationRepository()->save($issued);
                $stored = $this->filesModule()->fileManager()->upload(
                    organizationUid: $issued->organizationId,
                    originalName: ($issued->number ?? 'quotation-' . $id) . '.pdf',
                    contents: $pdf,
                    visibility: 'private',
                    classification: 'confidential',
                    entityType: 'quotation',
                    entityUid: $id,
                    metadata: [
                        'source' => 'sales',
                        'documentType' => 'quotation',
                        'number' => $issued->number,
                        'documentSnapshot' => $snapshot,
                    ],
                    actorId: (int) $this->wire()->user->id,
                );
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $this->audit('files', 'file', $stored['uid'], 'generated', metadata: [
                'sourceComponent' => 'sales',
                'quotationUid' => $id,
                'version' => $stored['versionNumber'],
            ]);
            $this->message($this->_('Quotation issued with an immutable snapshot and private PDF.'));
        } elseif ($action === 'send') {
            $this->requireMail();
            $mailboxUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('mailbox_uid')
            );
            $recipient = strtolower(trim((string) $this->wire()->input->post('recipient')));
            $mailbox = $this->mailModule()->mailboxRepository()->require($mailboxUid);
            $this->requireSameOrganization($mailbox->organizationId);
            if (!$mailbox->isActive()) {
                throw new WireException($this->_('Select an active mailbox.'));
            }
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new WireException($this->_('A valid quotation recipient is required.'));
            }
            $number = $quotation->number ?? $this->_('Quotation');
            $subject = sprintf($this->_('Quotation %s'), $number);
            $body = sprintf(
                $this->_("Hello,\n\nplease find quotation %s for %s %s. The offer is valid until %s.\n\nRegards"),
                $number,
                number_format($quotation->total->amountMinor() / 100, 2, '.', ''),
                $quotation->total->currencyCode(),
                $quotation->validUntil?->format('Y-m-d') ?? $this->_('further notice'),
            );
            $dryRun = (bool) $this->wire()->input->post('dry_run');
            $outbound = $dryRun
                ? $this->mailModule()->outboundWithSender(
                    new class implements \Kontor\Mail\Contracts\MailSenderInterface {
                        public function send(
                            string $fromAddress,
                            array $toAddresses,
                            array $ccAddresses,
                            string $subject,
                            string $bodyText,
                        ): void {
                        }
                    }
                )
                : $this->mailModule()->outbound();
            $message = $outbound->send(
                $quotation->organizationId,
                $mailbox->uid->toString(),
                $mailbox->emailAddress,
                [$recipient],
                [],
                $subject,
                $body,
                (int) $this->wire()->user->id,
            );
            $this->mailModule()->entityLinking()->link(
                $quotation->organizationId,
                $message->uid->toString(),
                'quotation',
                $quotation->uid->toString(),
                (int) $this->wire()->user->id,
            );
            $this->audit('mail', 'message', $message->uid->toString(), 'quotation_delivery', metadata: [
                'quotationUid' => $quotation->uid->toString(),
                'dryRun' => $dryRun,
                'status' => $message->status,
            ]);
            if ($message->status !== 'sent') {
                throw new WireException(sprintf(
                    $this->_('Quotation delivery failed: %s'),
                    $message->error ?? $this->_('unknown transport error'),
                ));
            }
            $sales->quotationWorkflow()->send($id);
            $this->audit('sales', 'quotation', $id, 'send', metadata: [
                'mailMessageUid' => $message->uid->toString(),
                'recipient' => $recipient,
                'dryRun' => $dryRun,
            ]);
            $this->message($dryRun
                ? $this->_('Quotation delivery simulated, recorded in Mail, and marked sent.')
                : $this->_('Quotation sent, recorded in Mail, and marked sent.'));
            $this->wire()->session->redirect('../sales-quotation/?id=' . rawurlencode($id));
            return;
        } elseif ($action === 'accept') {
            $sales->quotationWorkflow()->accept($id);
        } elseif ($action === 'convert') {
            $order = $sales->conversionService()->convert($id);
            $this->audit(
                'sales',
                'order',
                $order->uid->toString(),
                'created',
                current: ['quotationUid' => $id, 'number' => $order->number],
            );
            $this->message($this->_('Sales order created.'));
            $this->wire()->session->redirect(
                '../sales-order/?id=' . rawurlencode($order->uid->toString())
            );
        } elseif ($action === 'cancel') {
            $sales->quotationWorkflow()->cancel($id);
        } elseif ($action === 'restore') {
            $sales->quotationRepository()->restore($id);
        } else {
            $sales->quotationRepository()->archive($id);
        }

        $this->audit('sales', 'quotation', $id, $action);
        $this->message($this->_('Quotation updated.'));
        $this->wire()->session->redirect('../sales-quotation/?id=' . rawurlencode($id));
    }

    public function ___executeSalesOrder(): string
    {
        $this->requireSales();
        $this->requirePermission('kontor-sales-order-view');
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $order = $sales->orderRepository()->require($id);
        $this->requireSameOrganization($order->organizationId);
        $lines = $sales->documentLineRepository()->forDocument('order', $id);
        $trackedLines = $order->isPending() ? $this->salesOrderTrackedLines($lines) : [];
        $reservationMovements = $order->isConfirmed()
            ? $this->salesOrderReservationMovements($id)
            : [];
        $this->setPageTitle(sprintf($this->_('Kontor · %s'), $order->number ?? $this->_('Sales order')));

        return $this->renderTemplate('sales-order', [
            'order' => $order,
            'lines' => $lines,
            'customerLabel' => $this->salesCustomerLabels()[
                $order->customerType . ':' . $order->customerUid
            ] ?? $order->customerUid,
            'existingInvoice' => $this->invoicesReady()
                ? $this->invoiceModule()->invoiceRepository()->findByOrder($id)
                : null,
            'invoicesReady' => $this->invoicesReady(),
            'inventoryWarehouses' => $trackedLines !== [] && $this->inventoryReady()
                ? array_values(array_filter(
                    $this->inventoryModule()->warehouseRepository()->forOrganization(
                        $this->organizationUid()
                    ),
                    static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
                ))
                : [],
            'trackedLineCount' => count($trackedLines),
            'reservationWarehouseUid' => $this->reservationWarehouseUid($reservationMovements),
            'canReserveInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-reserve'),
            'canShipInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-adjust'),
            'canReleaseInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-release'),
        ]);
    }

    public function ___executeSalesOrderAction(): void
    {
        $this->requirePost();
        $this->requireSales();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['confirm', 'complete', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['confirm', 'complete', 'cancel', 'archive', 'restore']);
        $permissions = [
            'confirm' => 'kontor-sales-order-confirm',
            'complete' => 'kontor-sales-order-complete',
            'cancel' => 'kontor-sales-order-cancel',
            'archive' => 'kontor-sales-order-edit',
            'restore' => 'kontor-sales-order-edit',
        ];
        $this->requirePermission($permissions[$action]);
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $order = $sales->orderRepository()->require($id);
        $this->requireSameOrganization($order->organizationId);
        $lines = $sales->documentLineRepository()->forDocument('order', $id);
        $trackedLines = $this->salesOrderTrackedLines($lines);
        $reservations = $this->salesOrderReservationMovements($id);

        if ($action === 'confirm') {
            if ($trackedLines === []) {
                $sales->orderWorkflow()->confirm($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-reserve');
                $warehouseUid = $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('warehouse_uid')
                );
                $warehouse = $this->inventoryModule()->warehouseRepository()->require($warehouseUid);
                $this->requireSameOrganization($warehouse->organizationId);
                if (!$warehouse->isActive()) {
                    throw new WireException($this->_('Select an active fulfillment warehouse.'));
                }

                $pdo = $this->wire()->database->pdo();
                $pdo->beginTransaction();
                try {
                    foreach ($trackedLines as $line) {
                        $this->inventoryModule()->movements()->reserve(
                            $order->organizationId,
                            $warehouseUid,
                            (string) $line->itemUid,
                            $line->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $line->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':reserve:' . $line->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->confirm($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Inventory reserved for the sales order.'));
            }
        } elseif ($action === 'complete') {
            if ($reservations === []) {
                $sales->orderWorkflow()->complete($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-adjust');
                $warehouseUid = $this->reservationWarehouseUid($reservations);
                if ($warehouseUid === null) {
                    throw new WireException($this->_('The order has no consistent reservation warehouse.'));
                }

                $pdo = $this->wire()->database->pdo();
                $pdo->beginTransaction();
                try {
                    foreach ($reservations as $reservation) {
                        $this->inventoryModule()->movements()->shipReserved(
                            $order->organizationId,
                            $warehouseUid,
                            $reservation->itemUid,
                            $reservation->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $reservation->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':ship:' . $reservation->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->complete($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Reserved inventory shipped and order completed.'));
            }
        } elseif ($action === 'cancel') {
            if ($reservations === []) {
                $sales->orderWorkflow()->cancel($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-release');
                $warehouseUid = $this->reservationWarehouseUid($reservations);
                if ($warehouseUid === null) {
                    throw new WireException($this->_('The order has no consistent reservation warehouse.'));
                }

                $pdo = $this->wire()->database->pdo();
                $pdo->beginTransaction();
                try {
                    foreach ($reservations as $reservation) {
                        $this->inventoryModule()->movements()->release(
                            $order->organizationId,
                            $warehouseUid,
                            $reservation->itemUid,
                            $reservation->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $reservation->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':release:' . $reservation->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->cancel($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Inventory reservation released.'));
            }
        } elseif ($action === 'restore') {
            $sales->orderRepository()->restore($id);
        } else {
            $sales->orderRepository()->archive($id);
        }

        $this->audit('sales', 'order', $id, $action);
        $this->message($this->_('Sales order updated.'));
        $this->wire()->session->redirect('../sales-order/?id=' . rawurlencode($id));
    }

    public function ___executeInvoices(): string
    {
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-view');
        $this->setPageTitle($this->_('Kontor · Invoices'));
        $invoices = $this->invoiceModule()->invoiceRepository()->findMatching(
            $this->organizationUid(),
            limit: 50,
        );

        return $this->renderTemplate('invoices', [
            'invoices' => $invoices,
            'customerLabels' => $this->salesCustomerLabels(),
        ]);
    }

    public function ___executeInvoice(): string
    {
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-view');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $module = $this->invoiceModule();
        $invoice = $module->invoiceRepository()->require($id);
        $this->requireSameOrganization($invoice->organizationId);
        $this->setPageTitle(sprintf(
            $this->_('Kontor · %s'),
            $invoice->number ?? $this->_('Draft invoice')
        ));
        $documentType = $invoice->kind === 'credit_note' ? 'credit_note' : 'invoice';
        $invoiceTemplate = null;
        $creditNoteTemplate = null;
        if ($this->documentsReady()) {
            $invoiceTemplate = $invoice->templateUid !== null
                ? $this->documentsModule()->templateRepository()->find($invoice->templateUid)
                : $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $invoice->organizationId,
                    $documentType . '.standard',
                    $invoice->documentLanguage,
                );
            if ($invoice->kind === 'invoice' && $invoice->isCreditable()) {
                $creditNoteTemplate = $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $invoice->organizationId,
                    'credit_note.standard',
                    $invoice->documentLanguage,
                );
            }
        }
        $issuedFiles = $this->filesReady()
            ? $this->filesModule()->fileManager()->forEntity(
                $documentType,
                $invoice->uid->toString(),
                $invoice->organizationId,
            )
            : [];

        return $this->renderTemplate('invoice', [
            'invoice' => $invoice,
            'lines' => $module->documentLineRepository()->forDocument(
                $documentType,
                $id,
            ),
            'invoiceTemplate' => $invoiceTemplate,
            'creditNoteTemplate' => $creditNoteTemplate,
            'issuedFile' => $issuedFiles[0] ?? null,
            'customerLabel' => $this->salesCustomerLabels()[
                $invoice->customerType . ':' . $invoice->customerUid
            ] ?? $invoice->customerUid,
            'paymentsReady' => $this->paymentsReady(),
            'allocations' => $this->paymentsReady()
                ? $this->paymentModule()->allocationRepository()->forDocument('invoice', $id)
                : [],
            'ledgerReady' => $this->ledgerReady(),
            'ledgerPosting' => $this->ledgerReady()
                ? $this->ledgerModule()->entryRepository()->findByReference(
                    LedgerInvoicePostingService::ISSUE_REFERENCE,
                    $id,
                )
                : null,
            'ledgerCancellation' => $this->ledgerReady()
                ? $this->ledgerModule()->entryRepository()->findByReference(
                    LedgerInvoicePostingService::CANCELLATION_REFERENCE,
                    $id,
                )
                : null,
            'mailReady' => $this->mailReady(),
            'mailboxes' => $this->mailReady()
                ? array_values(array_filter(
                    $this->mailModule()->mailboxRepository()->forOrganization($invoice->organizationId),
                    static fn (\Kontor\Mail\Domain\Mailbox $mailbox): bool => $mailbox->isActive(),
                ))
                : [],
            'customerEmail' => $this->invoiceCustomerEmail($invoice),
        ]);
    }

    public function ___executeInvoiceFromOrder(): void
    {
        $this->requirePost();
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-create');
        $orderId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('order_uid'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $order = $sales->orderRepository()->require($orderId);
        $this->requireSameOrganization($order->organizationId);
        $invoice = $this->invoiceModule()->orderConversionService()->convert($orderId);
        $this->audit(
            'invoices',
            'invoice',
            $invoice->uid->toString(),
            'created',
            current: [
                'orderUid' => $orderId,
                'totalMinor' => $invoice->total->amountMinor(),
                'dueDate' => $invoice->dueDate?->format('Y-m-d'),
            ],
        );
        $this->message($this->_('Invoice draft created from sales order.'));
        $this->wire()->session->redirect(
            '../invoice/?id=' . rawurlencode($invoice->uid->toString())
        );
    }

    public function ___executeInvoiceAction(): void
    {
        $this->requirePost();
        $this->requireInvoices();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['issue', 'send', 'cancel', 'credit', 'archive', 'restore']
        );
        $this->requireAction($action, ['issue', 'send', 'cancel', 'credit', 'archive', 'restore']);
        $permissions = [
            'issue' => 'kontor-invoices-invoice-issue',
            'send' => 'kontor-invoices-invoice-send',
            'cancel' => 'kontor-invoices-invoice-cancel',
            'credit' => 'kontor-invoices-credit-note-create',
            'archive' => 'kontor-invoices-invoice-edit-draft',
            'restore' => 'kontor-invoices-invoice-edit-draft',
        ];
        $this->requirePermission($permissions[$action]);
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $module = $this->invoiceModule();
        $invoice = $module->invoiceRepository()->require($id);
        $this->requireSameOrganization($invoice->organizationId);

        if ($action === 'issue' || $action === 'credit') {
            $this->requireDocuments();
            $this->requireFiles();
            $templateKey = $action === 'credit' ? 'credit_note.standard' : 'invoice.standard';
            $template = $this->documentsModule()->templateRepository()->findCurrentVersion(
                $invoice->organizationId,
                $templateKey,
                $invoice->documentLanguage,
            );
            if ($template === null) {
                throw new WireException(sprintf(
                    $this->_('Publish an active %s document template before issuing this document.'),
                    $templateKey,
                ));
            }

            $pdo = $this->wire()->database->pdo();
            $pdo->beginTransaction();
            try {
                $issued = $action === 'credit'
                    ? $module->workflow()->issueCreditNote($id, (int) $this->wire()->user->id)
                    : $module->workflow()->issue($id, (int) $this->wire()->user->id);
                $stored = $this->storeIssuedInvoiceDocument($module, $issued, $template);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $this->audit('files', 'file', $stored['uid'], 'generated', metadata: [
                'sourceComponent' => 'invoices',
                'invoiceUid' => $issued->uid->toString(),
                'version' => $stored['versionNumber'],
            ]);

            if ($action === 'credit') {
                $this->audit(
                    'invoices',
                    'invoice',
                    $issued->uid->toString(),
                    'credit_note_issued',
                    current: ['creditedInvoiceUid' => $id, 'number' => $issued->number],
                );
                $this->message($this->_('Credit note issued with an immutable snapshot and private PDF.'));
                $this->wire()->session->redirect(
                    '../invoice/?id=' . rawurlencode($issued->uid->toString())
                );
            }
            $this->message($this->_('Invoice issued with an immutable snapshot and private PDF.'));
        } elseif ($action === 'send') {
            $this->requireMail();
            $mailboxUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('mailbox_uid')
            );
            $recipient = strtolower(trim((string) $this->wire()->input->post('recipient')));
            $mailbox = $this->mailModule()->mailboxRepository()->require($mailboxUid);
            $this->requireSameOrganization($mailbox->organizationId);
            if (!$mailbox->isActive()) {
                throw new WireException($this->_('Select an active mailbox.'));
            }
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new WireException($this->_('A valid invoice recipient is required.'));
            }
            $number = $invoice->number ?? $this->_('Invoice');
            $subject = sprintf($this->_('Invoice %s'), $number);
            $body = sprintf(
                $this->_("Hello,\n\nplease find invoice %s for %s %s. Payment is due %s.\n\nRegards"),
                $number,
                number_format($invoice->total->amountMinor() / 100, 2, '.', ''),
                $invoice->total->currencyCode(),
                $invoice->dueDate?->format('Y-m-d') ?? $this->_('on receipt'),
            );
            $dryRun = (bool) $this->wire()->input->post('dry_run');
            $outbound = $dryRun
                ? $this->mailModule()->outboundWithSender(
                    new class implements \Kontor\Mail\Contracts\MailSenderInterface {
                        public function send(
                            string $fromAddress,
                            array $toAddresses,
                            array $ccAddresses,
                            string $subject,
                            string $bodyText,
                        ): void {
                        }
                    }
                )
                : $this->mailModule()->outbound();
            $message = $outbound->send(
                $invoice->organizationId,
                $mailbox->uid->toString(),
                $mailbox->emailAddress,
                [$recipient],
                [],
                $subject,
                $body,
                (int) $this->wire()->user->id,
            );
            $this->mailModule()->entityLinking()->link(
                $invoice->organizationId,
                $message->uid->toString(),
                'invoice',
                $invoice->uid->toString(),
                (int) $this->wire()->user->id,
            );
            $this->audit('mail', 'message', $message->uid->toString(), 'invoice_delivery', metadata: [
                'invoiceUid' => $invoice->uid->toString(),
                'dryRun' => $dryRun,
                'status' => $message->status,
            ]);
            if ($message->status !== 'sent') {
                throw new WireException(sprintf(
                    $this->_('Invoice delivery failed: %s'),
                    $message->error ?? $this->_('unknown transport error'),
                ));
            }
            $module->workflow()->send($id);
            $this->audit('invoices', 'invoice', $id, 'send', metadata: [
                'mailMessageUid' => $message->uid->toString(),
                'recipient' => $recipient,
                'dryRun' => $dryRun,
            ]);
            $this->message($dryRun
                ? $this->_('Invoice delivery simulated, recorded in Mail, and marked sent.')
                : $this->_('Invoice sent, recorded in Mail, and marked sent.'));
            $this->wire()->session->redirect('../invoice/?id=' . rawurlencode($id));
            return;
        } elseif ($action === 'cancel') {
            $pdo = $this->wire()->database->pdo();
            $pdo->beginTransaction();
            try {
                $module->workflow()->cancel($id, (int) $this->wire()->user->id);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        } elseif ($action === 'restore') {
            $module->invoiceRepository()->restore($id);
        } else {
            $module->invoiceRepository()->archive($id);
        }

        $this->audit('invoices', 'invoice', $id, $action);
        $this->message($this->_('Invoice updated.'));
        $this->wire()->session->redirect('../invoice/?id=' . rawurlencode($id));
    }

    public function ___executePayments(): string
    {
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-view');
        $this->setPageTitle($this->_('Kontor · Payments'));

        return $this->renderTemplate('payments', [
            'payments' => $this->paymentModule()->paymentRepository()->findMatching(
                $this->organizationUid(),
                limit: 50,
            ),
            'payerLabels' => $this->salesCustomerLabels(),
        ]);
    }

    public function ___executePayment(): string
    {
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-view');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $module = $this->paymentModule();
        $payment = $module->paymentRepository()->require($id);
        $this->requireSameOrganization($payment->organizationId);
        $allocations = $module->allocationRepository()->forPayment($id);
        $invoiceLabels = [];
        $ledgerEntries = [];

        foreach ($allocations as $allocation) {
            if ($allocation->documentType !== 'invoice') {
                continue;
            }
            $invoice = $this->invoiceModule()->invoiceRepository()->find($allocation->documentUid);
            if ($invoice !== null && hash_equals($invoice->organizationId, $this->organizationUid())) {
                $invoiceLabels[$allocation->documentUid] = $invoice->number ?? $this->_('Draft invoice');
            }
            if ($this->ledgerReady()) {
                $referenceUid = $allocation->uid->toString();
                $ledgerEntries[$referenceUid] = [
                    'posting' => $this->ledgerModule()->entryRepository()->findByReference(
                        LedgerAllocationPostingService::POSTING_REFERENCE,
                        $referenceUid,
                    ),
                    'reversal' => $this->ledgerModule()->entryRepository()->findByReference(
                        LedgerAllocationPostingService::REVERSAL_REFERENCE,
                        $referenceUid,
                    ),
                ];
            }
        }

        $this->setPageTitle(sprintf(
            $this->_('Kontor · %s'),
            $payment->number ?? $this->_('Draft payment')
        ));

        return $this->renderTemplate('payment', [
            'payment' => $payment,
            'allocations' => $allocations,
            'invoiceLabels' => $invoiceLabels,
            'ledgerReady' => $this->ledgerReady(),
            'ledgerEntries' => $ledgerEntries,
            'payerLabel' => $this->salesCustomerLabels()[
                $payment->payerType . ':' . $payment->payerUid
            ] ?? $payment->payerUid,
        ]);
    }

    public function ___executePaymentFromInvoice(): void
    {
        $this->requirePost();
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-create');
        $this->requirePermission('kontor-payments-payment-allocate');
        $invoiceId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('invoice_uid'));
        $invoice = $this->invoiceModule()->invoiceRepository()->require($invoiceId);
        $this->requireSameOrganization($invoice->organizationId);

        if (!in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true)) {
            throw new WireException($this->_('This invoice cannot receive a payment in its current status.'));
        }

        $amountText = str_replace(',', '.', trim((string) $this->wire()->input->post('amount')));
        if (!is_numeric($amountText) || (float) $amountText <= 0) {
            throw new WireException($this->_('Payment amount must be greater than zero.'));
        }
        $amount = Money::ofMinor((int) round((float) $amountText * 100), $invoice->currencyCode);
        if ($amount->amountMinor() > $invoice->due->amountMinor()) {
            throw new WireException($this->_('Payment amount cannot exceed the invoice balance.'));
        }

        $method = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('method'),
            ['bank_transfer', 'card', 'cash', 'other']
        ) ?? 'other';
        $reference = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('transaction_reference')
        ));
        $payment = Payment::create(
            $this->organizationUid(),
            $invoice->customerType,
            $invoice->customerUid,
            $amount,
            method: $method,
            paymentDate: new \DateTimeImmutable('today'),
            transactionReference: $reference !== '' ? $reference : null,
        );
        $module = $this->paymentModule();
        $pdo = $this->wire()->database->pdo();
        $pdo->beginTransaction();
        try {
            $module->paymentRepository()->save($payment);
            $payment = $module->workflow()->confirm($payment->uid->toString());
            $allocation = $module->allocationService()->allocate(
                $payment->uid->toString(),
                'invoice',
                $invoiceId,
                $amount,
                (int) $this->wire()->user->id,
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $this->audit(
            'payments',
            'payment',
            $payment->uid->toString(),
            'captured',
            current: [
                'number' => $payment->number,
                'amountMinor' => $amount->amountMinor(),
                'invoiceUid' => $invoiceId,
                'allocationUid' => $allocation->uid->toString(),
            ],
        );
        $this->message($this->ledgerReady()
            ? $this->_('Payment recorded, allocated, and posted to the ledger.')
            : $this->_('Payment recorded and allocated to the invoice.'));
        $this->wire()->session->redirect(
            '../payment/?id=' . rawurlencode($payment->uid->toString())
        );
    }

    public function ___executePaymentAction(): void
    {
        $this->requirePost();
        $this->requirePayments();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['reverse']
        );
        $this->requireAction($action, ['reverse']);
        $this->requirePermission('kontor-payments-payment-reverse');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $payment = $this->paymentModule()->paymentRepository()->require($id);
        $this->requireSameOrganization($payment->organizationId);
        $pdo = $this->wire()->database->pdo();
        $pdo->beginTransaction();
        try {
            $this->paymentModule()->workflow()->reversePayment(
                $id,
                (int) $this->wire()->user->id,
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        $this->audit('payments', 'payment', $id, 'reversed');
        $this->message($this->ledgerReady()
            ? $this->_('Payment, allocations, and ledger posting were reversed.')
            : $this->_('Payment and its allocations were reversed.'));
        $this->wire()->session->redirect('../payment/?id=' . rawurlencode($id));
    }

    public function ___executeTasks(): string
    {
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-task-view');
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['open', 'in_progress', 'done', 'cancelled']
        );
        $priority = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('priority'),
            ['low', 'normal', 'high', 'urgent']
        );
        $archived = (string) $this->wire()->input->get('archived') === '1';
        $this->setPageTitle($this->_('Kontor · Tasks'));

        return $this->renderTemplate('tasks', [
            'tasks' => $this->taskModule()->taskRepository()->findMatching(
                $this->organizationUid(),
                $query,
                $status,
                $priority,
                $archived,
                limit: 100,
            ),
            'query' => $query,
            'selectedStatus' => $status,
            'selectedPriority' => $priority,
            'archived' => $archived,
        ]);
    }

    public function ___executeTask(): string
    {
        $this->requireTasks();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $archived = (string) $this->wire()->input->get('archived') === '1';
        $repository = $this->taskModule()->taskRepository();
        $task = $id !== '' ? $repository->require($id) : null;
        $this->requirePermission($task === null ? 'kontor-tasks-task-create' : 'kontor-tasks-task-edit');

        if ($task !== null) {
            $this->requireSameOrganization($task->organizationId);
        }

        $values = [
            'title' => $task?->title ?? '',
            'description' => $task?->description ?? '',
            'priority' => $task?->priority ?? 'normal',
            'dueAt' => $task?->dueAt?->format('Y-m-d\TH:i') ?? '',
            'recurrenceRule' => $task?->recurrenceRule ?? '',
            'recurrenceUntil' => $task?->recurrenceUntil?->format('Y-m-d') ?? '',
            'assignedToMe' => $task?->assignedTo === (int) $this->wire()->user->id,
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'title' => trim($this->wire()->sanitizer->text((string) $this->wire()->input->post('title'))),
                'description' => trim((string) $this->wire()->input->post('description')),
                'priority' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('priority'),
                    ['low', 'normal', 'high', 'urgent']
                ) ?? 'normal',
                'dueAt' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('due_at')),
                'recurrenceRule' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('recurrence_rule'),
                    ['daily', 'weekly', 'monthly', 'yearly']
                ) ?? '',
                'recurrenceUntil' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('recurrence_until')
                ),
                'assignedToMe' => (string) $this->wire()->input->post('assigned_to_me') === '1',
            ];
            $dueAt = null;
            $recurrenceUntil = null;

            if ($values['title'] === '') {
                $error = $this->_('Task title is required.');
            }
            if ($error === '' && $values['dueAt'] !== '') {
                try {
                    $dueAt = new \DateTimeImmutable($values['dueAt']);
                } catch (\Throwable) {
                    $error = $this->_('Due date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceUntil'] !== '') {
                try {
                    $recurrenceUntil = new \DateTimeImmutable($values['recurrenceUntil']);
                } catch (\Throwable) {
                    $error = $this->_('Recurrence end date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceRule'] !== '' && $dueAt === null) {
                $error = $this->_('A recurring task needs a due date.');
            }

            if ($error === '') {
                $task ??= Task::create($this->organizationUid(), $values['title']);
                $task->title = $values['title'];
                $task->description = $values['description'] !== '' ? $values['description'] : null;
                $task->priority = $values['priority'];
                $task->dueAt = $dueAt;
                $task->recurrenceRule = $values['recurrenceRule'] !== '' ? $values['recurrenceRule'] : null;
                $task->recurrenceUntil = $recurrenceUntil;
                $task->assignedTo = $values['assignedToMe'] ? (int) $this->wire()->user->id : null;
                $isNew = $id === '';
                $repository->save($task);
                $this->audit(
                    'tasks',
                    'task',
                    $task->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    current: [
                        'title' => $task->title,
                        'priority' => $task->priority,
                        'dueAt' => $task->dueAt?->format(DATE_ATOM),
                        'recurrenceRule' => $task->recurrenceRule,
                    ],
                );
                $this->message($isNew ? $this->_('Task created.') : $this->_('Task updated.'));
                $this->wire()->session->redirect(
                    '../task/?id=' . rawurlencode($task->uid->toString())
                );
            }
        }

        $this->setPageTitle($task === null
            ? $this->_('Kontor · New task')
            : sprintf($this->_('Kontor · %s'), $task->title));
        $collaborationReady = $task !== null && $this->collaborationReady();
        $reminders = $task !== null
            ? $this->taskModule()->reminderRepository()->forTask($task->uid->toString())
            : [];
        $notes = [];
        $comments = [];
        $following = false;
        $user = $this->wire()->user;
        $canViewNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-view');
        $canCreateNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-create');
        $canArchiveNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-archive');
        $canViewComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-view');
        $canCreateComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-create');
        $canArchiveComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-archive');
        $canManageFollow = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-follow-manage');

        if ($collaborationReady) {
            $collaboration = $this->collaborationModule();
            $notes = $canViewNotes
                ? $collaboration->noteRepository()->forEntity('task', $task->uid->toString())
                : [];
            $comments = $canViewComments
                ? $collaboration->commentRepository()->forEntity('task', $task->uid->toString())
                : [];
            if ($canManageFollow) {
                $following = $collaboration->followerRepository()->isFollowing(
                    $this->organizationUid(),
                    'task',
                    $task->uid->toString(),
                    (int) $user->id,
                );
            }
            if ($canViewComments) {
                $collaboration->unreadStateService()->markRead(
                    $this->organizationUid(),
                    (int) $user->id,
                    'task',
                    $task->uid->toString(),
                );
            }
        }

        return $this->renderTemplate('task', [
            'task' => $task,
            'values' => $values,
            'error' => $error,
            'archived' => $archived,
            'reminders' => $reminders,
            'canManageReminders' => $user->isSuperuser()
                || $user->hasPermission('kontor-tasks-reminder-manage'),
            'collaborationReady' => $collaborationReady,
            'notes' => $notes,
            'comments' => $comments,
            'following' => $following,
            'canViewNotes' => $canViewNotes,
            'canCreateNotes' => $canCreateNotes,
            'canArchiveNotes' => $canArchiveNotes,
            'canViewComments' => $canViewComments,
            'canCreateComments' => $canCreateComments,
            'canArchiveComments' => $canArchiveComments,
            'canManageFollow' => $canManageFollow,
        ]);
    }

    public function ___executeTaskAction(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['start', 'complete', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['start', 'complete', 'cancel', 'archive', 'restore']);
        $this->requirePermission(match ($action) {
            'complete' => 'kontor-tasks-task-complete',
            'cancel' => 'kontor-tasks-task-cancel',
            default => 'kontor-tasks-task-edit',
        });
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $module = $this->taskModule();
        $task = $module->taskRepository()->require($id);
        $this->requireSameOrganization($task->organizationId);

        if ($action === 'start') {
            $module->workflow()->start($id);
        } elseif ($action === 'complete') {
            $result = $module->workflow()->complete($id);
            if ($result['next'] !== null) {
                $this->audit('tasks', 'task', $id, 'complete');
                $this->audit(
                    'tasks',
                    'task',
                    $result['next']->uid->toString(),
                    'recurrence_created',
                    current: ['sourceTaskUid' => $id, 'dueAt' => $result['next']->dueAt?->format(DATE_ATOM)],
                );
                $this->message($this->_('Task completed and the next occurrence was created.'));
                $this->wire()->session->redirect(
                    '../task/?id=' . rawurlencode($result['next']->uid->toString())
                );
            }
        } elseif ($action === 'cancel') {
            $module->workflow()->cancel($id);
        } elseif ($action === 'restore') {
            $module->taskRepository()->restore($id);
            $this->audit('tasks', 'task', $id, 'restored');
            $this->message($this->_('Task restored.'));
            $this->wire()->session->redirect('../tasks/');
        } else {
            $module->taskRepository()->archive($id);
            $this->audit('tasks', 'task', $id, 'archived');
            $this->message($this->_('Task archived.'));
            $this->wire()->session->redirect('../tasks/?archived=1');
        }

        $this->audit('tasks', 'task', $id, $action);
        $this->message($this->_('Task updated.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($id));
    }

    public function ___executeTaskReminder(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-reminder-manage');
        $taskUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('task_uid')
        );
        $task = $this->taskModule()->taskRepository()->require($taskUid);
        $this->requireSameOrganization($task->organizationId);
        if ($task->assignedTo === null) {
            throw new WireException($this->_('Assign the task before scheduling an email reminder.'));
        }

        $recipient = $this->wire()->users->get($task->assignedTo);
        $recipientEmail = strtolower(trim((string) $recipient->email));
        if (!$recipient->id || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new WireException($this->_('The assigned user needs a valid email address.'));
        }

        $rawRemindAt = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('remind_at')
        );
        if ($rawRemindAt === '') {
            throw new WireException($this->_('Reminder date is required.'));
        }
        try {
            $remindAt = new \DateTimeImmutable($rawRemindAt);
        } catch (\Throwable) {
            throw new WireException($this->_('Reminder date is invalid.'));
        }

        $fromAddress = strtolower(trim((string) $this->wire()->config->adminEmail));
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $fromAddress = 'notifications@kontor.local';
        }
        $result = $this->taskModule()->reminderDispatcher()->scheduleEmail(
            organizationUid: $task->organizationId,
            taskUid: $task->uid->toString(),
            taskTitle: $task->title,
            remindAt: $remindAt,
            recipientUserId: (int) $recipient->id,
            recipientEmail: $recipientEmail,
            fromAddress: $fromAddress,
            createdBy: (int) $this->wire()->user->id,
        );

        $this->audit(
            'tasks',
            'task_reminder',
            $result['reminder']->uid->toString(),
            'scheduled',
            current: [
                'taskUid' => $task->uid->toString(),
                'remindAt' => $remindAt->format(DATE_ATOM),
                'channel' => 'email',
                'jobUid' => $result['jobUid'],
            ],
        );
        $this->message($this->_('Email reminder scheduled.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($task->uid->toString()));
    }

    public function ___executeCollaboration(): string
    {
        $this->requireCollaboration();
        $this->requirePermission('kontor-collaboration-comment-view');
        $module = $this->collaborationModule();
        $this->setPageTitle($this->_('Kontor · Collaboration'));

        return $this->renderTemplate('collaboration', [
            'notes' => ($this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-collaboration-note-view'))
                ? $module->noteRepository()->findRecent($this->organizationUid(), 50)
                : [],
            'comments' => $module->commentRepository()->findRecent($this->organizationUid(), 50),
            'taskLabels' => $this->collaborationTaskLabels(),
        ]);
    }

    public function ___executeCollaborationPost(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $kind = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('kind'),
            ['note', 'comment']
        );
        $this->requireAction($kind, ['note', 'comment']);
        $this->requirePermission($kind === 'note'
            ? 'kontor-collaboration-note-create'
            : 'kontor-collaboration-comment-create');
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('entity_type'),
            ['task']
        );
        $this->requireAction($entityType, ['task']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->taskModule()->taskRepository()->require($entityUid);
        $this->requireSameOrganization($task->organizationId);
        $body = trim((string) $this->wire()->input->post('body'));

        if ($body === '') {
            throw new WireException($this->_('Collaboration text cannot be empty.'));
        }
        $body = mb_substr($body, 0, 10000);
        $module = $this->collaborationModule();
        $notificationCount = 0;

        if ($kind === 'note') {
            $record = Note::create(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
            );
            $module->noteRepository()->save($record);
        } else {
            $parentUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('parent_uid')
            );
            if ($parentUid !== '') {
                $parent = $module->commentRepository()->require($parentUid);
                if (
                    !hash_equals($parent->organizationId, $this->organizationUid())
                    || $parent->entityType !== $entityType
                    || !hash_equals($parent->entityUid, $entityUid)
                ) {
                    throw new WirePermissionException($this->_('Reply target does not belong to this thread.'));
                }
            }
            $record = $module->commentService()->post(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
                $parentUid !== '' ? $parentUid : null,
            );
            $notificationCount = count($this->queueCollaborationNotifications($record, $task->title));
        }

        $this->audit(
            'collaboration',
            $kind,
            $record->uid->toString(),
            'created',
            current: ['entityType' => $entityType, 'entityUid' => $entityUid],
        );
        $this->message(match (true) {
            $kind === 'note' => $this->_('Note added.'),
            $notificationCount > 0 => sprintf(
                $this->_('Comment posted; %d notification(s) queued.'),
                $notificationCount,
            ),
            default => $this->_('Comment posted.'),
        });
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeCollaborationAction(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive_note', 'archive_comment', 'toggle_follow']
        );
        $this->requireAction($action, ['archive_note', 'archive_comment', 'toggle_follow']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->taskModule()->taskRepository()->require($entityUid);
        $this->requireSameOrganization($task->organizationId);
        $module = $this->collaborationModule();

        if ($action === 'toggle_follow') {
            $this->requirePermission('kontor-collaboration-follow-manage');
            $userId = (int) $this->wire()->user->id;
            $following = $module->followerRepository()->isFollowing(
                $this->organizationUid(),
                'task',
                $entityUid,
                $userId,
            );
            if ($following) {
                $module->followerRepository()->unfollow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            } else {
                $module->followerRepository()->follow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            }
            $this->audit(
                'collaboration',
                'follower',
                $entityUid,
                $following ? 'unfollowed' : 'followed',
            );
            $this->message($following ? $this->_('Thread unfollowed.') : $this->_('Thread followed.'));
        } else {
            $kind = $action === 'archive_note' ? 'note' : 'comment';
            $this->requirePermission('kontor-collaboration-' . $kind . '-archive');
            $recordUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('record_uid'));
            $repository = $kind === 'note'
                ? $module->noteRepository()
                : $module->commentRepository();
            $record = $repository->require($recordUid);
            if (
                !hash_equals($record->organizationId, $this->organizationUid())
                || $record->entityType !== 'task'
                || !hash_equals($record->entityUid, $entityUid)
            ) {
                throw new WirePermissionException($this->_('Collaboration record does not belong to this task.'));
            }
            $repository->archive($recordUid);
            $this->audit('collaboration', $kind, $recordUid, 'archived');
            $this->message($kind === 'note' ? $this->_('Note archived.') : $this->_('Comment archived.'));
        }

        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeReports(): string
    {
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-view');
        $this->setPageTitle($this->_('Kontor · Reports'));
        $module = $this->reportsModule();
        $providers = $module->providerRegistry()->all();
        $requestedKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('provider')
        );
        $providerKey = $requestedKey !== '' ? $requestedKey : (string) array_key_first($providers);
        $provider = $providerKey !== '' && $module->providerRegistry()->has($providerKey)
            ? $module->providerRegistry()->get($providerKey)
            : null;
        $filters = [];
        $groupBy = [];
        $result = null;

        if ($provider !== null) {
            $schema = $provider->schema();
            foreach ($schema->filterableFields as $field) {
                $value = trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->get('filter_' . $field)
                ));
                if ($value !== '') {
                    $filters[$field] = $value;
                }
            }
            $requestedGroup = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->get('group_by')
            );
            if ($requestedGroup !== '' && in_array($requestedGroup, $schema->groupableFields, true)) {
                $groupBy[] = $requestedGroup;
            }

            if ((string) $this->wire()->input->get('run') === '1') {
                $result = $module->reportBuilder()->run(
                    $providerKey,
                    new ReportQuery($this->organizationUid(), $filters, $groupBy),
                );
                $this->audit(
                    'reports',
                    'report',
                    $providerKey,
                    'run',
                    metadata: ['filters' => $filters, 'groupBy' => $groupBy, 'rowCount' => count($result->rows)],
                );
            }
        }

        return $this->renderTemplate('reports', [
            'providers' => $providers,
            'providerKey' => $providerKey,
            'provider' => $provider,
            'filters' => $filters,
            'groupBy' => $groupBy,
            'result' => $result,
            'schedules' => $module->scheduledReportRepository()->forOrganization(
                $this->organizationUid()
            ),
            'canManageSchedules' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-schedule-manage'),
            'canExport' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-report-export'),
        ]);
    }

    public function ___executeReportsSchedule(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        if ($name === '') {
            throw new WireException($this->_('A schedule name is required.'));
        }
        $recurrence = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('recurrence'),
            ['daily', 'weekly', 'monthly', 'yearly'],
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('format'),
            ['csv', 'json', 'xlsx', 'pdf'],
        );
        if ($recurrence === null || $format === null) {
            throw new WireException($this->_('Invalid schedule settings.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }

        $firstRunInput = trim((string) $this->wire()->input->post('first_run_at'));
        try {
            $firstRunAt = $firstRunInput !== ''
                ? new \DateTimeImmutable($firstRunInput)
                : new \DateTimeImmutable();
        } catch (\Exception) {
            throw new WireException($this->_('Invalid first run time.'));
        }

        $schedule = $module->scheduledReports()->schedule(
            organizationUid: $this->organizationUid(),
            providerKey: $providerKey,
            name: $name,
            recurrenceRule: $recurrence,
            filters: $filters,
            groupBy: array_values(array_map('strval', $groupBy)),
            format: $format,
            firstRunAt: $firstRunAt,
            createdBy: (int) $this->wire()->user->id,
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $schedule->uid->toString(),
            'created',
            current: [
                'provider' => $providerKey,
                'recurrence' => $recurrence,
                'format' => $format,
                'nextRunAt' => $firstRunAt->format(DATE_ATOM),
            ],
        );
        $this->message($this->_('Scheduled report created.'));
        $this->wire()->session->redirect('../reports/?provider='.rawurlencode($providerKey));
    }

    public function ___executeReportsDispatchDue(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $jobs = $this->reportsModule()->scheduledReportDispatcher()->dispatchDue(
            $this->organizationUid()
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $this->organizationUid(),
            'dispatched',
            metadata: ['jobCount' => count($jobs), 'jobUids' => array_values($jobs)],
        );
        $this->message(sprintf($this->_('%d due report job(s) queued.'), count($jobs)));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsScheduleAction(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('uid'));
        $schedule = $this->reportsModule()->scheduledReportRepository()->require($uid);
        if (!hash_equals($schedule->organizationId, $this->organizationUid())) {
            throw new WirePermissionException($this->_('Scheduled report does not belong to this organization.'));
        }

        $this->reportsModule()->scheduledReportRepository()->archive($uid);
        $this->audit('reports', 'scheduled_report', $uid, 'archived');
        $this->message($this->_('Scheduled report archived.'));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsExport(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-export');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        foreach ($filters as $field => $value) {
            if (!is_string($field) || !is_scalar($value)) {
                throw new WireException($this->_('Invalid report filter.'));
            }
            $filters[$field] = mb_substr(trim((string) $value), 0, 191);
        }
        $groupBy = array_values(array_filter(
            $groupBy,
            static fn (mixed $field): bool => is_string($field),
        ));
        $result = $module->reportBuilder()->run(
            $providerKey,
            new ReportQuery($this->organizationUid(), $filters, $groupBy),
        );
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_report_');
        if ($temporary === false) {
            throw new WireException($this->_('Could not create a report export file.'));
        }
        $path = $temporary . '.csv';
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        $fields = array_keys($module->providerRegistry()->get($providerKey)->schema()->fields);
        $module->reportExporter()->export($result, $fields, 'csv', $path);
        $this->audit(
            'reports',
            'report',
            $providerKey,
            'exported',
            metadata: ['format' => 'csv', 'rowCount' => count($result->rows)],
        );
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => preg_replace('/[^a-z0-9_-]+/i', '-', $providerKey) . '-report.csv',
            'exit' => true,
        ]);
    }

    public function ___executeInventory(): string
    {
        $this->requireInventory();
        $this->requirePermission('kontor-inventory-stock-view');
        $this->setPageTitle($this->_('Kontor · Inventory'));
        $module = $this->inventoryModule();
        $organizationUid = $this->organizationUid();
        $warehouses = $module->warehouseRepository()->forOrganization($organizationUid);

        return $this->renderTemplate('inventory', [
            'warehouses' => $warehouses,
            'warehouseLabels' => array_column(array_map(
                static fn (Warehouse $warehouse): array => [
                    $warehouse->uid->toString(),
                    $warehouse->code . ' · ' . $warehouse->name,
                ],
                $warehouses,
            ), 1, 0),
            'itemLabels' => $this->inventoryItemLabels(),
            'balances' => $module->balanceRepository()->forOrganization($organizationUid),
            'movements' => ($this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-inventory-movement-view'))
                ? $module->movementRepository()->recentForOrganization($organizationUid)
                : [],
            'canManageWarehouses' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-inventory-warehouse-admin'),
            'canMoveStock' => $this->canPerformAnyInventoryMovement(),
        ]);
    }

    public function ___executeInventoryWarehouse(): string
    {
        $this->requireInventory();
        $this->requirePermission('kontor-inventory-warehouse-admin');
        $this->setPageTitle($this->_('Kontor · New warehouse'));
        $values = ['code' => '', 'name' => ''];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'code' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('code')
                )),
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
            ];
            if ($values['code'] === '' || preg_match('/^[A-Z0-9_-]{1,50}$/', $values['code']) !== 1) {
                $error = $this->_('Warehouse code must use letters, numbers, hyphens, or underscores.');
            } elseif ($values['name'] === '') {
                $error = $this->_('Warehouse name is required.');
            }

            if ($error === '') {
                $warehouse = Warehouse::create(
                    $this->organizationUid(),
                    $values['code'],
                    mb_substr($values['name'], 0, 191),
                );
                try {
                    $this->inventoryModule()->warehouseRepository()->save($warehouse);
                } catch (\PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? $this->_('That warehouse code is already in use.')
                        : $this->_('Warehouse could not be saved.');
                }
                if ($error === '') {
                    $this->audit(
                        'inventory',
                        'warehouse',
                        $warehouse->uid->toString(),
                        'created',
                        current: ['code' => $warehouse->code, 'name' => $warehouse->name],
                    );
                    $this->message($this->_('Warehouse created.'));
                    $this->wire()->session->redirect('../inventory/');
                }
            }
        }

        return $this->renderTemplate('inventory-warehouse', [
            'values' => $values,
            'error' => $error,
        ]);
    }

    public function ___executeInventoryWarehouseAction(): void
    {
        $this->requirePost();
        $this->requireInventory();
        $this->requirePermission('kontor-inventory-warehouse-admin');
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['activate', 'deactivate']
        );
        $this->requireAction($action, ['activate', 'deactivate']);
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('warehouse_uid'));
        $warehouse = $this->inventoryModule()->warehouseRepository()->require($uid);
        $this->requireSameOrganization($warehouse->organizationId);
        $action === 'activate'
            ? $this->inventoryModule()->warehouseRepository()->restore($uid)
            : $this->inventoryModule()->warehouseRepository()->archive($uid);
        $this->audit('inventory', 'warehouse', $uid, $action === 'activate' ? 'activated' : 'deactivated');
        $this->message($action === 'activate'
            ? $this->_('Warehouse activated.')
            : $this->_('Warehouse deactivated.'));
        $this->wire()->session->redirect('../inventory/');
    }

    public function ___executeInventoryMovement(): string
    {
        $this->requireInventory();
        $module = $this->inventoryModule();
        $warehouses = $module->warehouseRepository()->forOrganization($this->organizationUid());
        $items = $this->inventoryItemOptions();
        $values = [
            'action' => 'receive',
            'warehouseUid' => '',
            'destinationWarehouseUid' => '',
            'itemUid' => '',
            'quantity' => '',
            'unitCode' => 'pcs',
            'reason' => '',
            'idempotencyKey' => 'admin-' . Uid::generate()->toString(),
        ];
        $error = '';

        if ($this->wire()->input->post('submit_move')) {
            $this->requirePost();
            $action = $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('action'),
                ['receive', 'transfer', 'adjust_increase', 'adjust_decrease', 'reserve', 'release']
            );
            $this->requireAction($action, ['receive', 'transfer', 'adjust_increase', 'adjust_decrease', 'reserve', 'release']);
            $this->requirePermission(match ($action) {
                'receive' => 'kontor-inventory-receive',
                'transfer' => 'kontor-inventory-transfer',
                'adjust_increase', 'adjust_decrease' => 'kontor-inventory-adjust',
                'reserve' => 'kontor-inventory-reserve',
                'release' => 'kontor-inventory-release',
            });
            $values = [
                'action' => $action,
                'warehouseUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('warehouse_uid')
                ),
                'destinationWarehouseUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('destination_warehouse_uid')
                ),
                'itemUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('item_uid')
                ),
                'quantity' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('quantity')
                ),
                'unitCode' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_code')
                ),
                'reason' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('reason')
                )),
                'idempotencyKey' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('idempotency_key')
                ),
            ];
            $quantity = (float) str_replace(',', '.', $values['quantity']);
            $warehouseMap = [];
            foreach ($warehouses as $warehouse) {
                $warehouseMap[$warehouse->uid->toString()] = $warehouse;
            }
            if (!isset($warehouseMap[$values['warehouseUid']])
                || !$warehouseMap[$values['warehouseUid']]->isActive()) {
                $error = $this->_('Select an active warehouse.');
            } elseif ($action === 'transfer' && (
                !isset($warehouseMap[$values['destinationWarehouseUid']])
                || !$warehouseMap[$values['destinationWarehouseUid']]->isActive()
                || $values['destinationWarehouseUid'] === $values['warehouseUid']
            )) {
                $error = $this->_('Select a different active destination warehouse.');
            } elseif ($values['itemUid'] === '') {
                $error = $this->_('Select or enter an inventory item.');
            } elseif ($quantity <= 0) {
                $error = $this->_('Quantity must be greater than zero.');
            } elseif (in_array($action, ['adjust_increase', 'adjust_decrease'], true)
                && $values['reason'] === '') {
                $error = $this->_('A reason is required for stock adjustments.');
            }

            if ($error === '') {
                $arguments = [
                    $this->organizationUid(),
                    $values['warehouseUid'],
                    $values['itemUid'],
                    $quantity,
                ];
                try {
                    $movement = match ($action) {
                        'receive' => $module->movements()->receive(
                            ...$arguments,
                            unitCode: $values['unitCode'] ?: 'pcs',
                            reason: $values['reason'] ?: null,
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                        'transfer' => $module->movements()->transfer(
                            $this->organizationUid(),
                            $values['warehouseUid'],
                            $values['destinationWarehouseUid'],
                            $values['itemUid'],
                            $quantity,
                            unitCode: $values['unitCode'] ?: 'pcs',
                            reason: $values['reason'] ?: null,
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                        'adjust_increase' => $module->movements()->adjustIncrease(
                            ...$arguments,
                            reason: $values['reason'],
                            unitCode: $values['unitCode'] ?: 'pcs',
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                        'adjust_decrease' => $module->movements()->adjustDecrease(
                            ...$arguments,
                            reason: $values['reason'],
                            unitCode: $values['unitCode'] ?: 'pcs',
                            allowNegative: $this->wire()->user->isSuperuser()
                                || $this->wire()->user->hasPermission('kontor-inventory-negative-stock-override'),
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                        'reserve' => $module->movements()->reserve(
                            ...$arguments,
                            unitCode: $values['unitCode'] ?: 'pcs',
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                        'release' => $module->movements()->release(
                            ...$arguments,
                            unitCode: $values['unitCode'] ?: 'pcs',
                            idempotencyKey: $values['idempotencyKey'],
                            createdBy: (int) $this->wire()->user->id,
                        ),
                    };
                } catch (\InvalidArgumentException|\RuntimeException $exception) {
                    $error = $exception->getMessage();
                }
                if ($error === '') {
                    $this->audit(
                        'inventory',
                        'movement',
                        $movement->uid->toString(),
                        $action,
                        current: [
                            'itemUid' => $movement->itemUid,
                            'quantity' => $movement->quantity,
                            'unitCode' => $movement->unitCode,
                        ],
                    );
                    $this->message($this->_('Inventory movement completed.'));
                    $this->wire()->session->redirect('../inventory/');
                }
            }
        } else {
            $this->requirePermission('kontor-inventory-stock-view');
        }
        $this->setPageTitle($this->_('Kontor · Stock movement'));

        return $this->renderTemplate('inventory-movement', [
            'values' => $values,
            'error' => $error,
            'warehouses' => array_values(array_filter(
                $warehouses,
                static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
            )),
            'items' => $items,
        ]);
    }

    public function ___executePurchasing(): string
    {
        $this->requirePurchasing();
        $this->requirePermission('kontor-purchasing-po-view');
        $this->setPageTitle($this->_('Kontor · Purchasing'));
        $module = $this->purchasingModule();
        $suppliers = $module->supplierRepository()->forOrganization($this->organizationUid());

        return $this->renderTemplate('purchasing', [
            'suppliers' => $suppliers,
            'supplierLabels' => array_column(array_map(
                static fn (Supplier $supplier): array => [
                    $supplier->uid->toString(),
                    $supplier->code . ' · ' . $supplier->legalName,
                ],
                $suppliers,
            ), 1, 0),
            'orders' => $module->purchaseOrderRepository()->forOrganization($this->organizationUid()),
            'canCreateSupplier' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-purchasing-supplier-create'),
            'canCreateOrder' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-purchasing-po-create'),
        ]);
    }

    public function ___executePurchasingSupplier(): string
    {
        $this->requirePurchasing();
        $this->requirePermission('kontor-purchasing-supplier-create');
        $this->setPageTitle($this->_('Kontor · New supplier'));
        $values = [
            'code' => '',
            'legalName' => '',
            'email' => '',
            'phone' => '',
            'currencyCode' => $this->organization()->defaultCurrency,
            'paymentTermsDays' => '30',
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'code' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('code')
                )),
                'legalName' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('legal_name')
                )),
                'email' => trim($this->wire()->sanitizer->email(
                    (string) $this->wire()->input->post('email')
                )),
                'phone' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('phone')
                )),
                'currencyCode' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency_code')
                )),
                'paymentTermsDays' => $this->wire()->sanitizer->digits(
                    (string) $this->wire()->input->post('payment_terms_days')
                ),
            ];
            if ($values['code'] === '' || preg_match('/^[A-Z0-9_-]{1,50}$/', $values['code']) !== 1) {
                $error = $this->_('Supplier code must use letters, numbers, hyphens, or underscores.');
            } elseif ($values['legalName'] === '') {
                $error = $this->_('Supplier legal name is required.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currencyCode']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            }

            if ($error === '') {
                $supplier = Supplier::create(
                    $this->organizationUid(),
                    $values['code'],
                    mb_substr($values['legalName'], 0, 191),
                    $values['currencyCode'],
                    $values['email'] ?: null,
                    $values['phone'] ?: null,
                    min(365, max(0, (int) $values['paymentTermsDays'])),
                    (int) $this->wire()->user->id,
                );
                try {
                    $this->purchasingModule()->supplierRepository()->save($supplier);
                } catch (\PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? $this->_('That supplier code is already in use.')
                        : $this->_('Supplier could not be saved.');
                }
                if ($error === '') {
                    $this->audit(
                        'purchasing',
                        'supplier',
                        $supplier->uid->toString(),
                        'created',
                        current: ['code' => $supplier->code, 'legalName' => $supplier->legalName],
                    );
                    $this->message($this->_('Supplier created.'));
                    $this->wire()->session->redirect('../purchasing/');
                }
            }
        }

        return $this->renderTemplate('purchasing-supplier', [
            'values' => $values,
            'error' => $error,
        ]);
    }

    public function ___executePurchaseOrder(): string
    {
        $this->requirePurchasing();
        $module = $this->purchasingModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $order = $id !== '' ? $module->purchaseOrderRepository()->require($id) : null;
        if ($order !== null) {
            $this->requireSameOrganization($order->organizationId);
            $this->requirePermission('kontor-purchasing-po-view');
        } else {
            $this->requirePermission('kontor-purchasing-po-create');
        }
        $suppliers = array_values(array_filter(
            $module->supplierRepository()->forOrganization($this->organizationUid()),
            static fn (Supplier $supplier): bool => $supplier->isActive(),
        ));
        $warehouses = array_values(array_filter(
            $this->inventoryModule()->warehouseRepository()->forOrganization($this->organizationUid()),
            static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
        ));
        $items = $this->inventoryItemOptions();
        $values = [
            'supplierUid' => '',
            'warehouseUid' => '',
            'itemUid' => '',
            'quantity' => '1',
            'unitPrice' => '',
            'currencyCode' => $this->organization()->defaultCurrency,
            'expectedDate' => '',
        ];
        $error = '';

        if ($order === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'supplierUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('supplier_uid')
                ),
                'warehouseUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('warehouse_uid')
                ),
                'itemUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('item_uid')
                ),
                'quantity' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('quantity')
                ),
                'unitPrice' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_price')
                ),
                'currencyCode' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency_code')
                )),
                'expectedDate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('expected_date')
                ),
            ];
            $supplierMap = [];
            foreach ($suppliers as $supplier) {
                $supplierMap[$supplier->uid->toString()] = $supplier;
            }
            $warehouseMap = [];
            foreach ($warehouses as $warehouse) {
                $warehouseMap[$warehouse->uid->toString()] = $warehouse;
            }
            $quantity = (float) str_replace(',', '.', $values['quantity']);
            $unitPrice = str_replace(',', '.', $values['unitPrice']);
            if (!isset($supplierMap[$values['supplierUid']])) {
                $error = $this->_('Select an active supplier.');
            } elseif (!isset($warehouseMap[$values['warehouseUid']])) {
                $error = $this->_('Select an active receiving warehouse.');
            } elseif (!isset($items[$values['itemUid']])) {
                $error = $this->_('Select an active inventory-tracked item.');
            } elseif ($quantity <= 0) {
                $error = $this->_('Quantity must be greater than zero.');
            } elseif ($unitPrice === '' || !is_numeric($unitPrice) || (float) $unitPrice < 0) {
                $error = $this->_('Unit price must be zero or greater.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currencyCode']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            }
            $expectedDate = null;
            if ($error === '' && $values['expectedDate'] !== '') {
                try {
                    $expectedDate = new \DateTimeImmutable($values['expectedDate']);
                } catch (\Throwable) {
                    $error = $this->_('Expected date is invalid.');
                }
            }

            if ($error === '') {
                $order = PurchaseOrder::create(
                    $this->organizationUid(),
                    $values['supplierUid'],
                    $values['currencyCode'],
                    $values['warehouseUid'],
                    $expectedDate,
                );
                $module->purchaseOrderRepository()->save($order);
                $item = $items[$values['itemUid']];
                $module->documentLineRepository()->save(DocumentLine::create(
                    $this->organizationUid(),
                    'purchase_order',
                    $order->uid->toString(),
                    $item['label'],
                    $quantity,
                    Money::ofMinor((int) round((float) $unitPrice * 100), $values['currencyCode']),
                    itemUid: $values['itemUid'],
                    itemType: 'product',
                    unitCode: $item['unitCode'],
                ));
                $this->audit(
                    'purchasing',
                    'purchase_order',
                    $order->uid->toString(),
                    'created',
                    current: ['supplierUid' => $order->supplierUid, 'warehouseUid' => $order->warehouseUid],
                );
                $this->message($this->_('Purchase order created.'));
                $this->wire()->session->redirect(
                    '../purchase-order/?id=' . rawurlencode($order->uid->toString())
                );
            }
        }
        $lines = $order !== null
            ? $module->documentLineRepository()->forDocument('purchase_order', $order->uid->toString())
            : [];
        $this->setPageTitle($order === null
            ? $this->_('Kontor · New purchase order')
            : sprintf($this->_('Kontor · %s'), $order->number ?? 'Draft purchase order'));

        return $this->renderTemplate('purchase-order', [
            'order' => $order,
            'values' => $values,
            'error' => $error,
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'items' => $items,
            'lines' => $lines,
            'receipts' => $order !== null
                ? $module->receiptRepository()->forPurchaseOrder($order->uid->toString())
                : [],
            'canIssue' => $order !== null && $order->isDraft()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-purchasing-po-issue')),
            'canReceive' => $order !== null && $order->isReceivable()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-purchasing-receipt-create')),
            'canCancel' => $order !== null && $order->isCancellable()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-purchasing-po-cancel')),
        ]);
    }

    public function ___executePurchaseOrderAction(): void
    {
        $this->requirePost();
        $this->requirePurchasing();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['issue', 'cancel']
        );
        $this->requireAction($action, ['issue', 'cancel']);
        $this->requirePermission($action === 'issue'
            ? 'kontor-purchasing-po-issue'
            : 'kontor-purchasing-po-cancel');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $order = $this->purchasingModule()->purchaseOrderRepository()->require($id);
        $this->requireSameOrganization($order->organizationId);
        $order = $action === 'issue'
            ? $this->purchasingModule()->purchaseOrderWorkflow()->issue($id)
            : $this->purchasingModule()->purchaseOrderWorkflow()->cancel($id);
        $this->audit(
            'purchasing',
            'purchase_order',
            $id,
            $action === 'issue' ? 'issued' : 'cancelled',
            current: ['number' => $order->number, 'status' => $order->status],
        );
        $this->message($action === 'issue'
            ? $this->_('Purchase order issued.')
            : $this->_('Purchase order cancelled.'));
        $this->wire()->session->redirect('../purchase-order/?id=' . rawurlencode($id));
    }

    public function ___executePurchasingReceipt(): string
    {
        $this->requirePurchasing();
        $this->requirePermission('kontor-purchasing-receipt-create');
        $module = $this->purchasingModule();
        $orderUid = $this->wire()->sanitizer->text(
            (string) ($this->wire()->input->post('order_uid') ?: $this->wire()->input->get('order'))
        );
        $order = $module->purchaseOrderRepository()->require($orderUid);
        $this->requireSameOrganization($order->organizationId);
        if (!$order->isReceivable()) {
            throw new WireException($this->_('This purchase order is not receivable.'));
        }
        $lines = $module->documentLineRepository()->forDocument('purchase_order', $orderUid);
        $warehouses = array_values(array_filter(
            $this->inventoryModule()->warehouseRepository()->forOrganization($this->organizationUid()),
            static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
        ));
        $warehouseUid = $order->warehouseUid ?? '';
        $quantities = [];
        $error = '';
        foreach ($lines as $line) {
            $quantities[$line->uid->toString()] = max(
                0,
                $line->quantity - $module->receiptLineRepository()->totalReceivedFor($line->uid->toString()),
            );
        }

        if ($this->wire()->input->post('submit_receive')) {
            $this->requirePost();
            $warehouseUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('warehouse_uid')
            );
            $warehouse = $this->inventoryModule()->warehouseRepository()->find($warehouseUid);
            if ($warehouse === null
                || !hash_equals($warehouse->organizationId, $this->organizationUid())
                || !$warehouse->isActive()) {
                $error = $this->_('Select an active receiving warehouse.');
            }
            $requested = [];
            foreach ($lines as $line) {
                $value = $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('quantity_' . $line->uid->toString())
                );
                $quantity = (float) str_replace(',', '.', $value);
                if ($quantity > 0) {
                    $requested[] = ['poLineUid' => $line->uid->toString(), 'quantity' => $quantity];
                }
            }
            if ($error === '' && $requested === []) {
                $error = $this->_('Enter a received quantity for at least one line.');
            }
            if ($error === '') {
                try {
                    $receipt = $module->goodsReceipt()->receive(
                        $this->organizationUid(),
                        $orderUid,
                        $warehouseUid,
                        $requested,
                        (int) $this->wire()->user->id,
                    );
                } catch (\InvalidArgumentException|\RuntimeException $exception) {
                    $error = $exception->getMessage();
                }
                if ($error === '') {
                    $this->audit(
                        'purchasing',
                        'goods_receipt',
                        $receipt->uid->toString(),
                        'received',
                        current: ['purchaseOrderUid' => $orderUid, 'warehouseUid' => $warehouseUid],
                    );
                    $this->message($this->_('Goods receipt recorded and inventory updated.'));
                    $this->wire()->session->redirect(
                        '../purchase-order/?id=' . rawurlencode($orderUid)
                    );
                }
            }
        }
        $this->setPageTitle($this->_('Kontor · Goods receipt'));

        return $this->renderTemplate('purchasing-receipt', [
            'order' => $order,
            'lines' => $lines,
            'warehouses' => $warehouses,
            'warehouseUid' => $warehouseUid,
            'outstanding' => $quantities,
            'error' => $error,
        ]);
    }

    public function ___executeExpenses(): string
    {
        $this->requireExpenses();
        $this->requirePermission('kontor-expenses-expense-view');
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['draft', 'submitted', 'approved', 'rejected', 'reimbursed', 'cancelled']
        );
        $module = $this->expensesModule();
        $categories = $module->categoryRepository()->forOrganization($this->organizationUid());
        $this->setPageTitle($this->_('Kontor · Expenses'));

        return $this->renderTemplate('expenses', [
            'expenses' => $module->expenseRepository()->forOrganization(
                $this->organizationUid(),
                $status,
            ),
            'categories' => $categories,
            'categoryLabels' => array_column(array_map(
                static fn (ExpenseCategory $category): array => [
                    $category->uid->toString(),
                    $category->code . ' · ' . $category->name,
                ],
                $categories,
            ), 1, 0),
            'selectedStatus' => $status,
            'canCreateExpense' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-expenses-expense-create'),
            'canManageCategories' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-expenses-category-manage'),
        ]);
    }

    public function ___executeExpenseCategory(): string
    {
        $this->requireExpenses();
        $this->requirePermission('kontor-expenses-category-manage');
        $this->setPageTitle($this->_('Kontor · New expense category'));
        $values = ['code' => '', 'name' => ''];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'code' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('code')
                )),
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
            ];
            if ($values['code'] === '' || preg_match('/^[A-Z0-9_-]{1,50}$/', $values['code']) !== 1) {
                $error = $this->_('Category code must use letters, numbers, hyphens, or underscores.');
            } elseif ($values['name'] === '') {
                $error = $this->_('Category name is required.');
            }
            if ($error === '') {
                $category = ExpenseCategory::create(
                    $this->organizationUid(),
                    $values['code'],
                    mb_substr($values['name'], 0, 191),
                    (int) $this->wire()->user->id,
                );
                try {
                    $this->expensesModule()->categoryRepository()->save($category);
                } catch (\PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? $this->_('That expense category code is already in use.')
                        : $this->_('Expense category could not be saved.');
                }
                if ($error === '') {
                    $this->audit(
                        'expenses',
                        'expense_category',
                        $category->uid->toString(),
                        'created',
                        current: ['code' => $category->code, 'name' => $category->name],
                    );
                    $this->message($this->_('Expense category created.'));
                    $this->wire()->session->redirect('../expenses/');
                }
            }
        }

        return $this->renderTemplate('expense-category', [
            'values' => $values,
            'error' => $error,
        ]);
    }

    public function ___executeExpense(): string
    {
        $this->requireExpenses();
        $module = $this->expensesModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $expense = $id !== '' ? $module->expenseRepository()->require($id) : null;
        if ($expense !== null) {
            $this->requireSameOrganization($expense->organizationId);
            $this->requirePermission('kontor-expenses-expense-view');
        } else {
            $this->requirePermission('kontor-expenses-expense-create');
        }
        $categories = array_values(array_filter(
            $module->categoryRepository()->forOrganization($this->organizationUid()),
            static fn (ExpenseCategory $category): bool => $category->isActive(),
        ));
        $suppliers = $this->purchasingReady()
            ? array_values(array_filter(
                $this->purchasingModule()->supplierRepository()->forOrganization($this->organizationUid()),
                static fn (Supplier $supplier): bool => $supplier->isActive(),
            ))
            : [];
        $values = [
            'categoryUid' => '',
            'supplierUid' => '',
            'description' => '',
            'amount' => '',
            'currencyCode' => $this->organization()->defaultCurrency,
            'expenseDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'receiptFileUid' => '',
        ];
        $error = '';

        if ($expense === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'categoryUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('category_uid')
                ),
                'supplierUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('supplier_uid')
                ),
                'description' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('description')
                )),
                'amount' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('amount')
                ),
                'currencyCode' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency_code')
                )),
                'expenseDate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('expense_date')
                ),
                'receiptFileUid' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('receipt_file_uid')
                ),
            ];
            $categoryMap = [];
            foreach ($categories as $category) {
                $categoryMap[$category->uid->toString()] = $category;
            }
            $supplierMap = [];
            foreach ($suppliers as $supplier) {
                $supplierMap[$supplier->uid->toString()] = $supplier;
            }
            $amount = str_replace(',', '.', $values['amount']);
            if (!isset($categoryMap[$values['categoryUid']])) {
                $error = $this->_('Select an active expense category.');
            } elseif ($values['supplierUid'] !== '' && !isset($supplierMap[$values['supplierUid']])) {
                $error = $this->_('Selected supplier is invalid.');
            } elseif ($values['description'] === '') {
                $error = $this->_('Expense description is required.');
            } elseif ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
                $error = $this->_('Amount must be greater than zero.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currencyCode']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            }
            try {
                $expenseDate = new \DateTimeImmutable($values['expenseDate']);
            } catch (\Throwable) {
                $expenseDate = new \DateTimeImmutable();
                $error = $this->_('Expense date is invalid.');
            }
            if ($error === '') {
                $expense = Expense::create(
                    $this->organizationUid(),
                    $values['categoryUid'],
                    mb_substr($values['description'], 0, 500),
                    Money::ofMinor((int) round((float) $amount * 100), $values['currencyCode']),
                    $expenseDate,
                    $values['supplierUid'] ?: null,
                    $values['receiptFileUid'] ?: null,
                    (int) $this->wire()->user->id,
                );
                $module->expenseRepository()->save($expense);
                $this->audit(
                    'expenses',
                    'expense',
                    $expense->uid->toString(),
                    'created',
                    current: ['description' => $expense->description, 'amountMinor' => $expense->amount->amountMinor()],
                );
                $this->message($this->_('Expense created.'));
                $this->wire()->session->redirect(
                    '../expense/?id=' . rawurlencode($expense->uid->toString())
                );
            }
        }
        $this->setPageTitle($expense === null
            ? $this->_('Kontor · New expense')
            : sprintf($this->_('Kontor · %s'), $expense->description));

        $workflowCoordinator = $expense !== null ? $module->workflowCoordinator() : null;

        return $this->renderTemplate('expense', [
            'expense' => $expense,
            'values' => $values,
            'error' => $error,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'configuredWorkflowState' => $expense !== null
                ? $workflowCoordinator?->currentState($expense)
                : null,
            'configuredWorkflowHistory' => $expense !== null && $workflowCoordinator !== null
                ? $workflowCoordinator->history($expense)
                : [],
            'ledgerEntry' => $expense !== null && $this->ledgerReady()
                ? $this->ledgerModule()->entryRepository()->findByReference(
                    LedgerExpensePostingService::REFERENCE_TYPE,
                    $expense->uid->toString(),
                )
                : null,
            'canSubmit' => $expense !== null && $expense->isDraft()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-expenses-expense-submit')),
            'canApprove' => $expense !== null && $expense->isSubmitted()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-expenses-expense-approve')),
            'canReimburse' => $expense !== null && $expense->isApproved()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-expenses-expense-reimburse')),
            'canCancel' => $expense !== null && $expense->isCancellable()
                && ($this->wire()->user->isSuperuser()
                    || $this->wire()->user->hasPermission('kontor-expenses-expense-edit-draft')),
        ]);
    }

    public function ___executeExpenseAction(): void
    {
        $this->requirePost();
        $this->requireExpenses();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['submit', 'approve', 'reject', 'reimburse', 'cancel']
        );
        $this->requireAction($action, ['submit', 'approve', 'reject', 'reimburse', 'cancel']);
        $this->requirePermission(match ($action) {
            'submit' => 'kontor-expenses-expense-submit',
            'approve', 'reject' => 'kontor-expenses-expense-approve',
            'reimburse' => 'kontor-expenses-expense-reimburse',
            'cancel' => 'kontor-expenses-expense-edit-draft',
        });
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $expense = $this->expensesModule()->expenseRepository()->require($id);
        $this->requireSameOrganization($expense->organizationId);
        $reason = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('reason')
        ));
        $pdo = $this->wire()->database->pdo();
        $pdo->beginTransaction();
        try {
            $coordinator = $this->expensesModule()->workflowCoordinator();
            if ($coordinator !== null) {
                $expense = $coordinator->perform(
                    $id,
                    $action,
                    (int) $this->wire()->user->id,
                    $reason,
                );
            } else {
                $workflow = $this->expensesModule()->workflow();
                $expense = match ($action) {
                    'submit' => $workflow->submit($id, (int) $this->wire()->user->id),
                    'approve' => $workflow->approve($id, (int) $this->wire()->user->id),
                    'reject' => $workflow->reject($id, (int) $this->wire()->user->id, $reason),
                    'reimburse' => $workflow->reimburse($id, (int) $this->wire()->user->id),
                    'cancel' => $workflow->cancel($id),
                };
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        $this->audit(
            'expenses',
            'expense',
            $id,
            $action,
            current: ['status' => $expense->status, 'rejectionReason' => $expense->rejectionReason],
        );
        $this->message($action === 'reimburse' && $this->ledgerReady()
            ? $this->_('Expense reimbursed and posted to the ledger.')
            : $this->_('Expense workflow updated.'));
        $this->wire()->session->redirect('../expense/?id=' . rawurlencode($id));
    }

    public function ___executeProjects(): string
    {
        $this->requireProjects();
        $this->requirePermission('kontor-projects-project-view');
        $this->setPageTitle($this->_('Kontor · Projects'));

        return $this->renderTemplate('projects', [
            'projects' => $this->projectsModule()->projectRepository()
                ->forOrganization($this->organizationUid()),
            'customerLabels' => $this->salesCustomerLabels(),
            'canCreate' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-projects-project-create'),
        ]);
    }

    public function ___executeProject(): string
    {
        $this->requireProjects();
        $module = $this->projectsModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $project = $id !== '' ? $module->projectRepository()->require($id) : null;
        if ($project !== null) {
            $this->requireSameOrganization($project->organizationId);
            $this->requirePermission('kontor-projects-project-view');
        } else {
            $this->requirePermission('kontor-projects-project-create');
        }
        $customers = $this->salesCustomerLabels();
        $values = [
            'code' => '',
            'name' => '',
            'customer' => '',
            'hourlyRate' => '',
            'currencyCode' => $this->organization()->defaultCurrency,
        ];
        $error = '';

        if ($project === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'code' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('code')
                )),
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
                'customer' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('customer')
                ),
                'hourlyRate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('hourly_rate')
                ),
                'currencyCode' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency_code')
                )),
            ];
            $rate = str_replace(',', '.', $values['hourlyRate']);
            if ($values['code'] === '' || preg_match('/^[A-Z0-9_-]{1,50}$/', $values['code']) !== 1) {
                $error = $this->_('Project code must use letters, numbers, hyphens, or underscores.');
            } elseif ($values['name'] === '') {
                $error = $this->_('Project name is required.');
            } elseif (!isset($customers[$values['customer']])) {
                $error = $this->_('Select a customer.');
            } elseif ($rate === '' || !is_numeric($rate) || (float) $rate <= 0) {
                $error = $this->_('Hourly rate must be greater than zero.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currencyCode']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            }
            if ($error === '') {
                [$customerType, $customerUid] = explode(':', $values['customer'], 2);
                $project = Project::create(
                    $this->organizationUid(),
                    $values['code'],
                    mb_substr($values['name'], 0, 191),
                    $customerType,
                    $customerUid,
                    (int) round((float) $rate * 100),
                    $values['currencyCode'],
                    (int) $this->wire()->user->id,
                );
                try {
                    $module->projectRepository()->save($project);
                } catch (\PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? $this->_('That project code is already in use.')
                        : $this->_('Project could not be saved.');
                }
                if ($error === '') {
                    $this->audit(
                        'projects',
                        'project',
                        $project->uid->toString(),
                        'created',
                        current: ['code' => $project->code, 'name' => $project->name],
                    );
                    $this->message($this->_('Project created.'));
                    $this->wire()->session->redirect(
                        '../project/?id=' . rawurlencode($project->uid->toString())
                    );
                }
            }
        }

        $this->setPageTitle($project === null
            ? $this->_('Kontor · New project')
            : sprintf($this->_('Kontor · %s'), $project->name));

        return $this->renderTemplate('project', [
            'project' => $project,
            'values' => $values,
            'error' => $error,
            'customers' => $customers,
            'customerLabel' => $project !== null
                ? ($customers[($project->customerType ?? '') . ':' . ($project->customerUid ?? '')] ?? '—')
                : '—',
            'milestones' => $project !== null
                ? $module->milestoneRepository()->forProject($project->uid->toString())
                : [],
            'timeEntries' => $project !== null
                ? array_reverse($module->timeEntryRepository()->forProject($project->uid->toString()))
                : [],
            'billableItems' => $project !== null
                ? array_reverse($module->billableItemRepository()->forProject($project->uid->toString()))
                : [],
            'canManageMilestones' => $this->can('kontor-projects-milestone-manage'),
            'canTrackTime' => $this->can('kontor-projects-time-track'),
            'canManageBillable' => $this->can('kontor-projects-billable-item-manage'),
            'canGenerateInvoice' => $this->can('kontor-projects-invoice-generate'),
        ]);
    }

    public function ___executeProjectMilestone(): void
    {
        $this->requirePost();
        $this->requireProjects();
        $this->requirePermission('kontor-projects-milestone-manage');
        $project = $this->requireProjectFromPost();
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        if ($name === '') {
            throw new WireException($this->_('Milestone name is required.'));
        }
        $due = $this->wire()->sanitizer->text((string) $this->wire()->input->post('due_date'));
        $milestone = ProjectMilestone::create(
            $this->organizationUid(),
            $project->uid->toString(),
            mb_substr($name, 0, 191),
            $due !== '' ? new \DateTimeImmutable($due) : null,
        );
        $this->projectsModule()->milestoneRepository()->save($milestone);
        $this->audit('projects', 'milestone', $milestone->uid->toString(), 'created');
        $this->message($this->_('Milestone created.'));
        $this->redirectToProject($project->uid->toString());
    }

    public function ___executeProjectMilestoneAction(): void
    {
        $this->requirePost();
        $this->requireProjects();
        $this->requirePermission('kontor-projects-milestone-manage');
        $project = $this->requireProjectFromPost();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['complete', 'reopen']
        );
        $this->requireAction($action, ['complete', 'reopen']);
        $milestoneUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('milestone_uid')
        );
        $milestone = $this->projectsModule()->milestoneRepository()->require($milestoneUid);
        if ($milestone->projectUid !== $project->uid->toString()) {
            throw new WirePermissionException($this->_('Milestone does not belong to this project.'));
        }
        $action === 'complete'
            ? $this->projectsModule()->milestones()->complete($milestoneUid)
            : $this->projectsModule()->milestones()->reopen($milestoneUid);
        $this->audit('projects', 'milestone', $milestoneUid, $action);
        $this->message($this->_('Milestone updated.'));
        $this->redirectToProject($project->uid->toString());
    }

    public function ___executeProjectTime(): void
    {
        $this->requirePost();
        $this->requireProjects();
        $this->requirePermission('kontor-projects-time-track');
        $project = $this->requireProjectFromPost();
        $description = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('description')
        ));
        $minutes = (int) $this->wire()->input->post('minutes');
        if ($description === '' || $minutes < 1 || $minutes > 14400) {
            throw new WireException($this->_('Description and a duration from 1 to 14,400 minutes are required.'));
        }
        $milestoneUid = $this->validatedProjectMilestoneUid(
            $project->uid->toString(),
            (string) $this->wire()->input->post('milestone_uid')
        );
        $endedAt = new \DateTimeImmutable();
        $entry = $this->projectsModule()->timeTracking()->logManual(
            $this->organizationUid(),
            $project->uid->toString(),
            (int) $this->wire()->user->id,
            $endedAt->modify("-{$minutes} minutes"),
            $endedAt,
            mb_substr($description, 0, 500),
            $milestoneUid,
            true,
            null,
            $project->currencyCode,
        );
        $this->audit('projects', 'time_entry', $entry->uid->toString(), 'logged');
        $this->message($this->_('Time entry logged.'));
        $this->redirectToProject($project->uid->toString());
    }

    public function ___executeProjectBillableItem(): void
    {
        $this->requirePost();
        $this->requireProjects();
        $this->requirePermission('kontor-projects-billable-item-manage');
        $project = $this->requireProjectFromPost();
        $description = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('description')
        ));
        $quantity = (float) str_replace(',', '.', (string) $this->wire()->input->post('quantity'));
        $price = (float) str_replace(',', '.', (string) $this->wire()->input->post('unit_price'));
        if ($description === '' || $quantity <= 0 || $price < 0 || $project->currencyCode === null) {
            throw new WireException($this->_('Description, positive quantity, and a valid unit price are required.'));
        }
        $milestoneUid = $this->validatedProjectMilestoneUid(
            $project->uid->toString(),
            (string) $this->wire()->input->post('milestone_uid')
        );
        $item = BillableItem::create(
            $this->organizationUid(),
            $project->uid->toString(),
            mb_substr($description, 0, 500),
            $quantity,
            Money::ofMinor((int) round($price * 100), $project->currencyCode),
            $milestoneUid,
        );
        $this->projectsModule()->billableItemRepository()->save($item);
        $this->audit('projects', 'billable_item', $item->uid->toString(), 'created');
        $this->message($this->_('Billable item added.'));
        $this->redirectToProject($project->uid->toString());
    }

    public function ___executeProjectInvoice(): void
    {
        $this->requirePost();
        $this->requireProjects();
        $this->requirePermission('kontor-projects-invoice-generate');
        $project = $this->requireProjectFromPost();
        try {
            $invoice = $this->projectsModule()->invoicing()->generateInvoice(
                $project->uid->toString()
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            $this->redirectToProject($project->uid->toString());
        }
        $this->audit(
            'projects',
            'project',
            $project->uid->toString(),
            'invoice_generated',
            current: ['invoiceUid' => $invoice->uid->toString()],
        );
        $this->message($this->_('Draft invoice generated from project work.'));
        $this->wire()->session->redirect(
            '../invoice/?id=' . rawurlencode($invoice->uid->toString())
        );
    }

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
        $values = [
            'workflowKey' => '',
            'entityType' => '',
            'name' => '',
            'initialState' => 'draft',
            'states' => 'draft, review, approved, rejected',
        ];
        $error = '';

        if ($definition === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'workflowKey' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('workflow_key')
                )),
                'entityType' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('entity_type')
                )),
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
                'initialState' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('initial_state')
                )),
                'states' => (string) $this->wire()->input->post('states'),
            ];
            $states = array_values(array_unique(array_filter(array_map(
                static fn (string $state): string => strtolower(trim($state)),
                explode(',', $values['states']),
            ))));
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $values['workflowKey']) !== 1) {
                $error = $this->_('Workflow key must be a lowercase identifier.');
            } elseif (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $values['entityType']) !== 1) {
                $error = $this->_('Entity type must be a lowercase identifier.');
            } elseif ($values['name'] === '') {
                $error = $this->_('Workflow name is required.');
            } elseif ($states === [] || array_filter(
                $states,
                static fn (string $state): bool => preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $state) !== 1,
            ) !== []) {
                $error = $this->_('States must be comma-separated lowercase identifiers.');
            } elseif (!in_array($values['initialState'], $states, true)) {
                $error = $this->_('Initial state must appear in the state list.');
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
                        ? $this->_('That workflow key is already in use.')
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

        return $this->renderTemplate('workflow', [
            'definition' => $definition,
            'values' => $values,
            'error' => $error,
            'transitions' => $definition !== null
                ? $module->transitionRepository()->forDefinition($definition->uid->toString())
                : [],
            'instances' => $definition !== null
                ? $module->instanceRepository()->forDefinition($definition->uid->toString())
                : [],
            'canManage' => $this->can('kontor-workflow-definition-manage'),
            'canTransition' => $this->can('kontor-workflow-transition'),
        ]);
    }

    public function ___executeWorkflowTransition(): void
    {
        $this->requirePost();
        $this->requireWorkflow();
        $this->requirePermission('kontor-workflow-definition-manage');
        $definition = $this->requireWorkflowDefinitionFromPost();
        $actionKey = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action_key')
        ));
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
            throw new WireException($this->_('Action key must be a lowercase identifier.'));
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
        $this->setPageTitle(sprintf($this->_('Kontor · %s'), $instance->entityUid));

        return $this->renderTemplate('workflow-instance', [
            'instance' => $instance,
            'definition' => $definition,
            'transitions' => $transitions,
            'history' => $module->engine()->history($instance->entityType, $instance->entityUid),
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

        return $this->renderTemplate('automations', [
            'rules' => $module->ruleRepository()->forOrganization($this->organizationUid()),
            'logs' => array_slice(
                $module->executionLogRepository()->forOrganization($this->organizationUid()),
                0,
                25,
            ),
            'canManage' => $this->can('kontor-automation-rule-manage'),
        ]);
    }

    public function ___executeAutomation(): string
    {
        $this->requireAutomation();
        $module = $this->automationModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $rule = $id !== '' ? $module->ruleRepository()->require($id) : null;
        if ($rule !== null) {
            $this->requireSameOrganization($rule->organizationId);
            $this->requirePermission('kontor-automation-rule-view');
        } else {
            $this->requirePermission('kontor-automation-rule-manage');
        }
        $values = ['name' => '', 'triggerEvent' => 'demo.request.received'];
        $error = '';
        if ($rule === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
                'triggerEvent' => strtolower($this->wire()->sanitizer->text(
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

    public function ___executeMail(): string
    {
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-view');
        $module = $this->mailModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->messageRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }
        $this->setPageTitle($this->_('Kontor · Mail'));

        return $this->renderTemplate('mail', [
            'mailboxes' => $module->mailboxRepository()->forOrganization($this->organizationUid()),
            'messages' => $module->messageRepository()->forOrganization($this->organizationUid()),
            'selected' => $selected,
            'relations' => $selected !== null
                ? $module->entityLinking()->linkedEntities(
                    $this->organizationUid(),
                    $selected->uid->toString(),
                )
                : [],
            'adapters' => $module->inboundAdapterRegistry()->all(),
            'canManageMailboxes' => $this->can('kontor-mail-mailbox-manage'),
            'canSend' => $this->can('kontor-mail-message-send'),
        ]);
    }

    public function ___executeMailMailbox(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-mailbox-manage');
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $email = strtolower(trim((string) $this->wire()->input->post('email_address')));
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new WireException($this->_('Mailbox name and valid email address are required.'));
        }
        $mailbox = $this->mailModule()->mailboxes()->open(
            $this->organizationUid(),
            mb_substr($name, 0, 255),
            mb_substr($email, 0, 320),
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'mailbox', $mailbox->uid->toString(), 'created');
        $this->message($this->_('Shared mailbox created.'));
        $this->wire()->session->redirect('../mail/');
    }

    public function ___executeMailOutbound(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-send');
        $mailbox = $this->mailboxFromPost();
        $from = strtolower(trim((string) $this->wire()->input->post('from_address')));
        $to = $this->mailAddressesFromPost('to_addresses');
        $cc = $this->mailAddressesFromPost('cc_addresses', required: false);
        $subject = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('subject')
        ));
        $body = trim((string) $this->wire()->input->post('body_text'));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false || $subject === '' || $body === '') {
            throw new WireException($this->_('Valid sender, subject, and body are required.'));
        }
        $dryRun = (bool) $this->wire()->input->post('dry_run');
        $service = $dryRun
            ? $this->mailModule()->outboundWithSender(
                new class implements \Kontor\Mail\Contracts\MailSenderInterface {
                    public function send(
                        string $fromAddress,
                        array $toAddresses,
                        array $ccAddresses,
                        string $subject,
                        string $bodyText,
                    ): void {
                    }
                }
            )
            : $this->mailModule()->outbound();
        $message = $service->send(
            $this->organizationUid(),
            $mailbox?->uid->toString(),
            $from,
            $to,
            $cc,
            mb_substr($subject, 0, 255),
            $body,
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'message', $message->uid->toString(), 'sent', metadata: [
            'dryRun' => $dryRun,
            'status' => $message->status,
        ]);
        $this->message($dryRun
            ? $this->_('Outbound message simulated and recorded.')
            : $this->_('Outbound message processed.'));
        $this->wire()->session->redirect(
            '../mail/?id=' . rawurlencode($message->uid->toString())
        );
    }

    public function ___executeMailInbound(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-mailbox-manage');
        $mailbox = $this->mailboxFromPost();
        $adapterKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('adapter')
        );
        $raw = trim((string) $this->wire()->input->post('raw_email'));
        $adapter = $this->mailModule()->inboundAdapterRegistry()->get($adapterKey);
        if (!$adapter instanceof \Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter) {
            throw new WireException($this->_('The selected adapter does not accept raw email input.'));
        }
        if ($raw === '' || strlen($raw) > 1048576) {
            throw new WireException($this->_('Raw email is required and must be at most 1 MB.'));
        }
        $adapter->pushRaw($raw);
        $messages = $this->mailModule()->inbound()->poll(
            $adapter,
            $this->organizationUid(),
            $mailbox?->uid->toString(),
        );
        if ($messages === []) {
            throw new WireException($this->_('No valid inbound message was parsed.'));
        }
        $message = $messages[0];
        $this->audit('mail', 'message', $message->uid->toString(), 'received');
        $this->message($this->_('Inbound message received.'));
        $this->wire()->session->redirect(
            '../mail/?id=' . rawurlencode($message->uid->toString())
        );
    }

    public function ___executeMailLink(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-view');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('message_uid')
        );
        $message = $this->mailModule()->messageRepository()->require($uid);
        $this->requireSameOrganization($message->organizationId);
        $entityType = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_type')
        );
        $entityUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_uid')
        );
        if ($entityType === '' || $entityUid === '') {
            throw new WireException($this->_('Entity type and UID are required.'));
        }
        $relationUid = $this->mailModule()->entityLinking()->link(
            $this->organizationUid(),
            $uid,
            $entityType,
            $entityUid,
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'relation', $relationUid, 'created');
        $this->message($this->_('Message linked to entity.'));
        $this->wire()->session->redirect('../mail/?id=' . rawurlencode($uid));
    }

    public function ___executePortal(): string
    {
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $module = $this->portalModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->accountRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }

        $contact = null;
        $quotationRows = [];
        $invoiceRows = [];
        if ($selected !== null) {
            $contact = $module->profile()->view($selected->contactUid);
            $this->requireSameOrganization($contact->organizationId);
            foreach ($module->quotationRepository()->forContact(
                $this->organizationUid(),
                $selected->contactUid,
            ) as $quotation) {
                $files = $module->files()->filesForDocument(
                    'quotation',
                    $quotation->uid->toString(),
                );
                $quotationRows[] = [
                    'quotation' => $quotation,
                    'files' => array_map(
                        fn (array $file): array => $file + [
                            'downloadUrl' => $module->files()->downloadUrl(
                                (string) $file['uid'],
                                new \DateTimeImmutable('+15 minutes'),
                            ),
                        ],
                        $files,
                    ),
                ];
            }
            foreach ($module->invoiceRepository()->forContact(
                $this->organizationUid(),
                $selected->contactUid,
            ) as $invoice) {
                $files = $module->files()->filesForDocument(
                    'invoice',
                    $invoice->uid->toString(),
                );
                $invoiceRows[] = [
                    'invoice' => $invoice,
                    'payments' => $module->payments()->paymentsForInvoice(
                        $invoice->uid->toString()
                    ),
                    'files' => array_map(
                        fn (array $file): array => $file + [
                            'downloadUrl' => $module->files()->downloadUrl(
                                (string) $file['uid'],
                                new \DateTimeImmutable('+15 minutes'),
                            ),
                        ],
                        $files,
                    ),
                ];
            }
        }

        $verification = $this->wire()->session->get('kontorPortalVerification');
        $this->wire()->session->set('kontorPortalVerification', null);
        $this->setPageTitle($this->_('Kontor · Portal'));

        return $this->renderTemplate('portal', [
            'accounts' => $module->accountRepository()->forOrganization($this->organizationUid()),
            'contacts' => $this->contactRepository()->findAll($this->organizationUid(), limit: 250),
            'selected' => $selected,
            'contact' => $contact,
            'quotationRows' => $quotationRows,
            'invoiceRows' => $invoiceRows,
            'verification' => is_array($verification) ? $verification : null,
        ]);
    }

    public function ___executePortalAccount(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $contactUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('contact_uid')
        );
        $contact = $this->contactRepository()->require($contactUid);
        $this->requireSameOrganization($contact->organizationId);
        $email = strtolower(trim((string) $this->wire()->input->post('email')));
        $password = (string) $this->wire()->input->post('password');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12) {
            throw new WireException($this->_('A valid email and password of at least 12 characters are required.'));
        }
        if ($this->portalModule()->accountRepository()->findByEmail(
            $this->organizationUid(),
            $email,
        ) !== null) {
            throw new WireException($this->_('A portal account already uses this email address.'));
        }
        $account = $this->portalModule()->authentication()->register(
            $this->organizationUid(),
            $contactUid,
            $email,
            $password,
            (int) $this->wire()->user->id,
        );
        $this->audit('portal', 'account', $account->uid->toString(), 'created');
        $this->message($this->_('Portal account created.'));
        $this->wire()->session->redirect(
            '../portal/?id=' . rawurlencode($account->uid->toString())
        );
    }

    public function ___executePortalVerify(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $email = strtolower(trim((string) $this->wire()->input->post('email')));
        $password = (string) $this->wire()->input->post('password');
        $accountUid = '';
        try {
            $account = $this->portalModule()->authentication()->authenticate(
                $this->organizationUid(),
                $email,
                $password,
            );
            $accountUid = $account->uid->toString();
            $this->wire()->session->set('kontorPortalVerification', [
                'ok' => true,
                'message' => $this->_('Credentials accepted; login timestamp recorded.'),
            ]);
            $this->audit('portal', 'account', $accountUid, 'login_verified');
        } catch (\Kontor\Portal\Application\PortalAuthenticationFailedException) {
            $this->wire()->session->set('kontorPortalVerification', [
                'ok' => false,
                'message' => $this->_('Credentials rejected.'),
            ]);
        }
        $redirect = '../portal/';
        if ($accountUid !== '') {
            $redirect .= '?id=' . rawurlencode($accountUid);
        }
        $this->wire()->session->redirect($redirect);
    }

    public function ___executePortalStatus(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('account_uid')
        );
        $action = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        );
        $this->requireAction($action, ['enable', 'disable']);
        $account = $this->portalModule()->accountRepository()->require($uid);
        $this->requireSameOrganization($account->organizationId);
        $action === 'enable' ? $account->enable() : $account->disable();
        $this->portalModule()->accountRepository()->save($account);
        $this->audit('portal', 'account', $uid, $action . 'd');
        $this->message($action === 'enable'
            ? $this->_('Portal account enabled.')
            : $this->_('Portal account disabled.'));
        $this->wire()->session->redirect('../portal/?id=' . rawurlencode($uid));
    }

    public function ___executePortalProfile(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('account_uid')
        );
        $account = $this->portalModule()->accountRepository()->require($uid);
        $this->requireSameOrganization($account->organizationId);
        $changes = [];
        foreach (['firstName', 'middleName', 'lastName', 'phone', 'mobile', 'preferredLanguage'] as $field) {
            $changes[$field] = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post($field)
            ));
        }
        $contact = $this->portalModule()->profile()->update($account->contactUid, $changes);
        $this->requireSameOrganization($contact->organizationId);
        $this->audit('portal', 'profile', $contact->uid->toString(), 'updated');
        $this->message($this->_('Customer-safe profile fields updated.'));
        $this->wire()->session->redirect('../portal/?id=' . rawurlencode($uid));
    }

    public function ___executeFiles(): string
    {
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-view');
        $module = $this->filesModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->fileRepository()->find($id) : null;
        if ($id !== '' && ($selected === null
            || (int) $selected['organization_id'] !== $this->organizationInternalId())) {
            throw new Wire404Exception($this->_('File metadata was not found.'));
        }
        $versions = [];
        if ($selected !== null && $selected['entity_type'] !== null && $selected['entity_uid'] !== null) {
            $versions = $module->fileManager()->versionHistory(
                (string) $selected['entity_type'],
                (string) $selected['entity_uid'],
                (string) $selected['original_name'],
                $this->organizationUid(),
            );
        }
        $shareResult = $this->wire()->session->get('kontorFilesShareResult');
        $this->wire()->session->set('kontorFilesShareResult', null);
        $this->setPageTitle($this->_('Kontor · Files'));

        return $this->renderTemplate('files', [
            'files' => $module->fileRepository()->forOrganization($this->organizationInternalId()),
            'selected' => $selected,
            'versions' => $versions,
            'shareResult' => is_array($shareResult) ? $shareResult : null,
            'storageHealth' => $module->healthCheck()->run(),
            'canUpload' => $this->can('kontor-files-file-upload'),
            'canDownload' => $this->can('kontor-files-file-download'),
            'canShare' => $this->can('kontor-files-file-share'),
            'canDelete' => $this->can('kontor-files-file-delete'),
        ]);
    }

    public function ___executeFilesUpload(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-upload');
        $upload = $_FILES['file_upload'] ?? null;
        if (!is_array($upload)
            || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            throw new WireException($this->_('Choose a file that completed uploading.'));
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 25 * 1024 * 1024) {
            throw new WireException($this->_('Files must be between 1 byte and 25 MB.'));
        }
        $originalName = basename(str_replace('\\', '/', (string) ($upload['name'] ?? '')));
        if ($originalName === '' || $originalName === '.' || strlen($originalName) > 255) {
            throw new WireException($this->_('The original filename is invalid or too long.'));
        }
        $visibility = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('visibility')
        ));
        $classification = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('classification')
        ));
        $this->requireAction($visibility, ['private', 'internal']);
        $this->requireAction($classification, ['general', 'financial', 'confidential', 'restricted']);
        $entityType = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_type')
        )));
        $entityUid = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_uid')
        )));
        if (($entityType === '') !== ($entityUid === '')) {
            throw new WireException($this->_('Entity type and entity UID must be provided together.'));
        }
        if ($entityType !== ''
            && (preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $entityType) !== 1
                || !Uid::isValid($entityUid))) {
            throw new WireException($this->_('Use a valid entity type and ULID.'));
        }
        $stream = fopen((string) $upload['tmp_name'], 'rb');
        if ($stream === false) {
            throw new WireException($this->_('The uploaded file could not be opened.'));
        }
        try {
            $result = $this->filesModule()->fileManager()->upload(
                organizationUid: $this->organizationUid(),
                originalName: $originalName,
                contents: $stream,
                visibility: $visibility,
                classification: $classification,
                entityType: $entityType !== '' ? $entityType : null,
                entityUid: $entityUid !== '' ? $entityUid : null,
                metadata: $this->fileMetadataFromPost(),
                actorId: (int) $this->wire()->user->id,
            );
        } finally {
            fclose($stream);
        }
        $this->audit('files', 'file', $result['uid'], 'uploaded', metadata: [
            'version' => $result['versionNumber'],
            'visibility' => $visibility,
            'classification' => $classification,
            'entityType' => $entityType !== '' ? $entityType : null,
        ]);
        $this->message(sprintf($this->_('File version %d uploaded.'), $result['versionNumber']));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($result['uid']));
    }

    public function ___executeFilesAction(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-delete');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('file_uid'));
        $action = $this->wire()->sanitizer->text((string) $this->wire()->input->post('action'));
        $this->requireAction($action, ['archive', 'restore']);
        $manager = $this->filesModule()->fileManager();
        $action === 'archive'
            ? $manager->archive($uid, $this->organizationUid())
            : $manager->restore($uid, $this->organizationUid());
        $this->audit('files', 'file', $uid, $action . 'd');
        $this->message($action === 'archive'
            ? $this->_('File archived; its bytes remain in private storage.')
            : $this->_('File restored as the active version.'));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($uid));
    }

    public function ___executeFilesShare(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-share');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('file_uid'));
        $file = $this->filesModule()->fileRepository()->find($uid);
        if ($file === null
            || (int) $file['organization_id'] !== $this->organizationInternalId()
            || $file['archived_at'] !== null) {
            throw new WireException($this->_('Only an active file in this organization can be shared.'));
        }
        $expiresAt = new \DateTimeImmutable('+15 minutes');
        $url = $this->filesModule()->fileManager()->temporaryUrl(
            $uid,
            $expiresAt,
            $this->organizationUid(),
        );
        $this->wire()->session->set('kontorFilesShareResult', [
            'uid' => $uid,
            'url' => $url,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ]);
        $this->audit('files', 'file', $uid, 'shared', metadata: [
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ]);
        $this->message($this->_('Signed download link generated for 15 minutes.'));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($uid));
    }

    public function ___executeFilesDownload(): never
    {
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-download');
        $path = (string) $this->wire()->input->get('path');
        $expires = (int) $this->wire()->input->get('expires');
        $signature = (string) $this->wire()->input->get('signature');
        if ($path === '' || $expires < 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
            || !$this->filesModule()->verifyTemporaryUrl($path, $expires, $signature)) {
            throw new WirePermissionException($this->_('The signed file link is invalid or expired.'));
        }
        $file = $this->filesModule()->fileRepository()->findByPath(
            $this->organizationInternalId(),
            $path,
        );
        if ($file === null || $file['archived_at'] !== null) {
            throw new Wire404Exception($this->_('The active file was not found.'));
        }
        $stream = $this->filesModule()->fileManager()->read(
            (string) $file['uid'],
            $this->organizationUid(),
        );
        $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['original_name']) ?: 'download';
        header('Content-Type: ' . ((string) ($file['mime_type'] ?? '') ?: 'application/octet-stream'));
        header('Content-Length: ' . (int) $file['size_bytes']);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Cache-Control: private, no-store');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function ___executeCache(): string
    {
        $this->requireCache();
        $this->requirePermission('kontor-cache-view');
        $state = $this->wire()->session->get('kontorCacheWorkbench');
        $state = is_array($state) ? $state : [];
        $this->wire()->session->set('kontorCacheWorkbench', null);
        $this->setPageTitle($this->_('Kontor · Cache'));

        return $this->renderTemplate('cache', [
            'health' => $this->cacheModule()->healthCheck()->run(),
            'state' => array_replace([
                'namespace' => 'operations',
                'key' => 'example',
                'tags' => 'demo',
                'ttl' => '300',
                'valueJson' => '{"status":"ready"}',
                'result' => null,
            ], $state),
            'effectiveNamespacePrefix' => 'kontor-admin-org-' . $this->organizationInternalId() . '-',
            'consumers' => [
                [
                    'name' => 'Search',
                    'status' => $this->searchReady() ? 'connected' : 'unavailable',
                    'namespace' => 'search',
                    'policy' => '30-second query cache · results tag invalidated after indexing',
                ],
            ],
            'canManage' => $this->can('kontor-cache-manage'),
            'canFlush' => $this->can('kontor-cache-flush'),
        ]);
    }

    public function ___executeCacheOperate(): void
    {
        $this->requirePost();
        $this->requireCache();
        $this->requirePermission('kontor-cache-manage');
        $action = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        ));
        $this->requireAction($action, ['set', 'get', 'delete']);
        $input = $this->cacheWorkbenchInput();
        $cache = $this->cacheModule()->manager()->forNamespace($input['effectiveNamespace']);
        $result = null;

        if ($action === 'set') {
            $value = $this->cacheValueFromPost($input['valueJson']);
            $cache->set($input['key'], $value, $input['ttl'], $input['tags']);
            $result = [
                'action' => 'set',
                'hit' => true,
                'value' => $value,
                'message' => $this->_('Value stored in the selected namespace and tag generation.'),
            ];
        } elseif ($action === 'get') {
            $hit = $cache->has($input['key'], $input['tags']);
            $result = [
                'action' => 'get',
                'hit' => $hit,
                'value' => $hit ? $cache->get($input['key'], $input['tags']) : null,
                'message' => $hit ? $this->_('Cache hit.') : $this->_('Cache miss.'),
            ];
        } else {
            $wasPresent = $cache->has($input['key'], $input['tags']);
            $cache->delete($input['key'], $input['tags']);
            $result = [
                'action' => 'delete',
                'hit' => $wasPresent,
                'value' => null,
                'message' => $wasPresent
                    ? $this->_('Entry deleted from the current generation.')
                    : $this->_('Entry was already missing.'),
            ];
        }

        $this->wire()->session->set('kontorCacheWorkbench', [
            'namespace' => $input['namespace'],
            'key' => $input['key'],
            'tags' => implode(', ', $input['tags']),
            'ttl' => (string) ($input['ttl'] ?? 0),
            'valueJson' => $input['valueJson'],
            'result' => $result,
        ]);
        $this->audit('cache', 'namespace', $this->organizationUid(), $action, metadata: [
            'namespace' => $input['namespace'],
            'key' => $input['key'],
            'tags' => $input['tags'],
            'ttl' => $input['ttl'],
            'hit' => $result['hit'],
        ]);
        $this->message($result['message']);
        $this->wire()->session->redirect('../cache/');
    }

    public function ___executeCacheFlush(): void
    {
        $this->requirePost();
        $this->requireCache();
        $this->requirePermission('kontor-cache-flush');
        $action = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        ));
        $this->requireAction($action, ['flush-tag', 'flush-namespace']);
        $input = $this->cacheWorkbenchInput();
        $cache = $this->cacheModule()->manager()->forNamespace($input['effectiveNamespace']);
        if ($action === 'flush-tag') {
            if (count($input['tags']) !== 1) {
                throw new WireException($this->_('Tag invalidation requires exactly one tag.'));
            }
            $cache->flushTag($input['tags'][0]);
            $message = $this->_('Tag generation invalidated; matching entries now miss.');
        } else {
            $cache->flushNamespace();
            $message = $this->_('Namespace generation invalidated; all its entries now miss.');
        }
        $this->wire()->session->set('kontorCacheWorkbench', [
            'namespace' => $input['namespace'],
            'key' => $input['key'],
            'tags' => implode(', ', $input['tags']),
            'ttl' => (string) ($input['ttl'] ?? 0),
            'valueJson' => $input['valueJson'],
            'result' => [
                'action' => $action,
                'hit' => false,
                'value' => null,
                'message' => $message,
            ],
        ]);
        $this->audit('cache', 'namespace', $this->organizationUid(), $action, metadata: [
            'namespace' => $input['namespace'],
            'tag' => $action === 'flush-tag' ? $input['tags'][0] : null,
        ]);
        $this->message($message);
        $this->wire()->session->redirect('../cache/');
    }

    public function ___executeDocuments(): string
    {
        $this->requireDocuments();
        $this->requirePermission('kontor-documents-template-view');
        $module = $this->documentsModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->templateRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }
        $preview = $this->wire()->session->get('kontorDocumentsPreview');
        $this->wire()->session->set('kontorDocumentsPreview', null);
        $this->setPageTitle($this->_('Kontor · Documents'));

        return $this->renderTemplate('documents', [
            'templates' => $module->templateRepository()->forOrganization($this->organizationUid()),
            'selected' => $selected,
            'preview' => is_array($preview) ? $preview : null,
            'canCreate' => $this->can('kontor-documents-template-create'),
            'canEdit' => $this->can('kontor-documents-template-edit'),
            'canArchive' => $this->can('kontor-documents-template-archive'),
            'canRender' => $this->can('kontor-documents-render'),
        ]);
    }

    public function ___executeDocumentsPublish(): void
    {
        $this->requirePost();
        $this->requireDocuments();
        $templateKey = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('template_key')
        )));
        $documentType = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('document_type')
        )));
        $language = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('language')
        )));
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $body = trim((string) $this->wire()->input->post('body_html'));
        $css = trim((string) $this->wire()->input->post('custom_css'));
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $templateKey)
            || !preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $documentType)
            || !preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language)
            || $name === ''
            || $body === ''
            || strlen($body) > 262144
            || strlen($css) > 65536) {
            throw new WireException($this->_('Valid template metadata and HTML body are required.'));
        }
        $existing = $this->documentsModule()->templateRepository()->findCurrentVersionExact(
            $this->organizationUid(),
            $templateKey,
            $language,
        );
        $this->requirePermission($existing === null
            ? 'kontor-documents-template-create'
            : 'kontor-documents-template-edit');
        $template = $this->documentsModule()->templateManager()->publish(
            $this->organizationUid(),
            $templateKey,
            $documentType,
            $language,
            mb_substr($name, 0, 255),
            $body,
            $css !== '' ? $css : null,
            (int) $this->wire()->user->id,
        );
        $this->audit('documents', 'template', $template->uid->toString(), 'published', metadata: [
            'key' => $templateKey,
            'language' => $language,
            'version' => $template->versionNumber,
        ]);
        $this->message($this->_('Document template version published.'));
        $this->wire()->session->redirect(
            '../documents/?id=' . rawurlencode($template->uid->toString())
        );
    }

    public function ___executeDocumentsPreview(): void
    {
        $this->requirePost();
        $this->requireDocuments();
        $this->requireFiles();
        $this->requirePermission('kontor-documents-render');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('template_uid')
        );
        $template = $this->documentsModule()->templateRepository()->require($uid);
        $this->requireSameOrganization($template->organizationId);
        $rawData = trim((string) $this->wire()->input->post('data_json'));
        if ($rawData === '' || strlen($rawData) > 262144) {
            throw new WireException($this->_('Preview JSON is required and must be at most 256 KB.'));
        }
        try {
            $data = json_decode($rawData, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WireException($this->_('Preview data must be valid JSON.'));
        }
        if (!is_array($data)) {
            throw new WireException($this->_('Preview data must be a JSON object or array.'));
        }
        $renderer = $this->documentsModule()->renderService();
        $snapshot = $this->documentsModule()->snapshotBuilder()->build($template, $data);
        $pdf = $renderer->renderPdf($template, $data);
        $filenameStem = preg_replace('/[^A-Za-z0-9._-]/', '-', $template->templateKey) ?: 'document';
        $stored = $this->filesModule()->fileManager()->upload(
            organizationUid: $this->organizationUid(),
            originalName: $filenameStem . '-v' . $template->versionNumber . '.pdf',
            contents: $pdf,
            visibility: 'private',
            classification: 'confidential',
            entityType: 'document_template',
            entityUid: $template->uid->toString(),
            metadata: [
                'source' => 'documents',
                'documentType' => $template->documentType,
                'templateKey' => $template->templateKey,
                'documentSnapshot' => $snapshot,
            ],
            actorId: (int) $this->wire()->user->id,
        );
        $this->wire()->session->set('kontorDocumentsPreview', [
            'templateUid' => $uid,
            'html' => $renderer->renderPreviewHtml($template, $data),
            'snapshot' => $snapshot,
            'pdfBytes' => strlen($pdf),
            'fileUid' => $stored['uid'],
            'fileVersion' => $stored['versionNumber'],
            'dataJson' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $this->audit('documents', 'template', $uid, 'rendered', metadata: [
            'pdfBytes' => strlen($pdf),
            'snapshotVersion' => $snapshot['templateVersion'],
            'fileUid' => $stored['uid'],
            'fileVersion' => $stored['versionNumber'],
        ]);
        $this->audit('files', 'file', $stored['uid'], 'generated', metadata: [
            'sourceComponent' => 'documents',
            'templateUid' => $uid,
            'version' => $stored['versionNumber'],
        ]);
        $this->message($this->_('HTML, PDF, and immutable snapshot rendered; the PDF was stored privately.'));
        $this->wire()->session->redirect('../documents/?id=' . rawurlencode($uid));
    }

    public function ___executeDocumentsArchive(): void
    {
        $this->requirePost();
        $this->requireDocuments();
        $this->requirePermission('kontor-documents-template-archive');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('template_uid')
        );
        $action = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        );
        $this->requireAction($action, ['archive', 'restore']);
        $template = $this->documentsModule()->templateRepository()->require($uid);
        $this->requireSameOrganization($template->organizationId);
        $repository = $this->documentsModule()->templateRepository();
        $action === 'archive' ? $repository->archive($uid) : $repository->restore($uid);
        $this->audit('documents', 'template', $uid, $action . 'd');
        $this->message($action === 'archive'
            ? $this->_('Template version archived.')
            : $this->_('Template version restored.'));
        $this->wire()->session->redirect('../documents/?id=' . rawurlencode($uid));
    }

    public function ___executeAI(): string
    {
        $this->requireAI();
        $this->requirePermission('kontor-ai-action-approve');
        $module = $this->aiModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->pendingActionRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }
        $result = $this->wire()->session->get('kontorAIWorkbenchResult');
        $this->wire()->session->set('kontorAIWorkbenchResult', null);
        $this->setPageTitle($this->_('Kontor · AI'));

        return $this->renderTemplate('ai', [
            'providers' => $module->providerRegistry()->all(),
            'actions' => $module->pendingActionRepository()->forOrganization($this->organizationUid()),
            'selected' => $selected,
            'result' => is_array($result) ? $result : null,
        ]);
    }

    public function ___executeAIExecute(): void
    {
        $this->requirePost();
        $this->requireAI();
        $this->requirePermission('kontor-ai-action-approve');
        $capability = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('capability')
        );
        $this->requireAction($capability, ['summarize', 'draft', 'extract']);
        $text = trim((string) $this->wire()->input->post('text'));
        $instructions = trim((string) $this->wire()->input->post('instructions'));
        if ($text === '' || strlen($text) > 262144) {
            throw new WireException($this->_('Input text is required and must be at most 256 KB.'));
        }
        $simulate = (bool) $this->wire()->input->post('simulate');
        $module = $this->aiModule();
        $gateway = $simulate ? $module->previewGateway() : $module->gateway();
        $result = null;
        $pendingUid = '';

        if ($capability === 'summarize') {
            $response = (new \Kontor\AI\Application\SummaryService($gateway))->summarize(
                $this->organizationUid(),
                $text,
                (string) $this->wire()->user->id,
            );
            $result = [
                'capability' => $capability,
                'success' => $response->success,
                'output' => $response->output,
                'error' => $response->errorMessage,
                'pending' => false,
                'simulated' => $simulate,
            ];
        } elseif ($capability === 'extract') {
            $schema = $this->aiJsonObjectFromPost('schema_json');
            $normalizedSchema = [];
            foreach ($schema as $field => $type) {
                if (!is_scalar($type)) {
                    throw new WireException($this->_('Extraction schema values must be scalar type names.'));
                }
                $normalizedSchema[(string) $field] = (string) $type;
            }
            $response = (new \Kontor\AI\Application\ExtractionService($gateway))->extract(
                $this->organizationUid(),
                $text,
                $normalizedSchema,
                (string) $this->wire()->user->id,
            );
            $result = [
                'capability' => $capability,
                'success' => $response->success,
                'output' => $response->output,
                'error' => $response->errorMessage,
                'pending' => false,
                'simulated' => $simulate,
            ];
        } else {
            if ($instructions === '') {
                throw new WireException($this->_('Draft instructions are required.'));
            }
            $context = $this->aiJsonObjectFromPost('context_json');
            $approvalService = $simulate ? $module->previewApprovals() : $module->approvals();
            $approvalResult = $approvalService->requestAndMaybeApprove(new AIRequest(
                capability: 'draft',
                organizationId: $this->organizationUid(),
                input: ['context' => array_merge($context, ['sourceText' => $text]), 'instructions' => $instructions],
                requiresConfirmation: true,
                actorId: (string) $this->wire()->user->id,
            ), requestedBy: (int) $this->wire()->user->id);
            if ($approvalResult->isPending && $approvalResult->pendingAction !== null) {
                $pendingUid = $approvalResult->pendingAction->uid->toString();
                $result = [
                    'capability' => $capability,
                    'success' => true,
                    'output' => [],
                    'error' => null,
                    'pending' => true,
                    'pendingUid' => $pendingUid,
                    'simulated' => $simulate,
                ];
                $this->audit('ai', 'pending_action', $pendingUid, 'requested', metadata: [
                    'simulated' => $simulate,
                ]);
            } else {
                $response = $approvalResult->response;
                $result = [
                    'capability' => $capability,
                    'success' => $response?->success ?? false,
                    'output' => $response?->output ?? [],
                    'error' => $response?->errorMessage,
                    'pending' => false,
                    'simulated' => $simulate,
                ];
            }
        }

        $this->wire()->session->set('kontorAIWorkbenchResult', $result);
        $this->message($result['pending']
            ? $this->_('AI draft withheld for human approval.')
            : $this->_('AI workbench request completed.'));
        $redirect = '../ai/';
        if ($pendingUid !== '') {
            $redirect .= '?id=' . rawurlencode($pendingUid);
        }
        $this->wire()->session->redirect($redirect);
    }

    public function ___executeAIDecide(): void
    {
        $this->requirePost();
        $this->requireAI();
        $this->requirePermission('kontor-ai-action-approve');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action_uid')
        );
        $decision = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('decision')
        );
        $this->requireAction($decision, ['approve', 'reject']);
        $pending = $this->aiModule()->pendingActionRepository()->require($uid);
        $this->requireSameOrganization($pending->organizationId);
        if (!$pending->isPending()) {
            throw new WireException($this->_('This AI action has already been decided.'));
        }
        $decided = $decision === 'approve'
            ? $this->aiModule()->approvals()->approve($uid, (int) $this->wire()->user->id)
            : $this->aiModule()->approvals()->reject($uid, (int) $this->wire()->user->id);
        $this->audit('ai', 'pending_action', $uid, $decided->status);
        $this->message($decision === 'approve'
            ? $this->_('AI action approved.')
            : $this->_('AI action rejected.'));
        $this->wire()->session->redirect('../ai/?id=' . rawurlencode($uid));
    }

    public function ___executeLedger(): string
    {
        $this->requireLedger();
        $this->requirePermission('kontor-ledger-entry-view');
        $module = $this->ledgerModule();
        $accounts = $module->accountRepository()->forOrganization($this->organizationUid());
        $accountRows = [];
        foreach ($accounts as $account) {
            $accountRows[] = [
                'account' => $account,
                'balance' => $module->balances()->balance($account->uid->toString()),
            ];
        }
        $entries = $module->entryRepository()->forOrganization($this->organizationUid());
        $entryRows = [];
        foreach ($entries as $entry) {
            $lines = $module->lineRepository()->forEntry($entry->uid->toString());
            $debitMinor = array_sum(array_map(
                static fn ($line): int => $line->debit->amountMinor(),
                $lines,
            ));
            $currencyCode = $lines !== []
                ? $lines[0]->debit->currencyCode()
                : $this->organization()->defaultCurrency;
            $entryRows[] = [
                'entry' => $entry,
                'amount' => Money::ofMinor($debitMinor, $currencyCode),
            ];
        }
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->entryRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }
        $accountLabels = [];
        foreach ($accounts as $account) {
            $accountLabels[$account->uid->toString()] = $account->code . ' · ' . $account->name;
        }
        $this->setPageTitle($this->_('Kontor · Ledger'));

        return $this->renderTemplate('ledger', [
            'accountRows' => $accountRows,
            'activeAccounts' => array_values(array_filter(
                $accounts,
                static fn (Account $account): bool => $account->isActive(),
            )),
            'entryRows' => $entryRows,
            'selected' => $selected,
            'selectedLines' => $selected !== null
                ? $module->lineRepository()->forEntry($selected->uid->toString())
                : [],
            'accountLabels' => $accountLabels,
            'defaultCurrency' => $this->organization()->defaultCurrency,
            'canManageAccounts' => $this->can('kontor-ledger-account-manage'),
            'canRecordEntries' => $this->can('kontor-ledger-entry-record'),
        ]);
    }

    public function ___executeLedgerAccountCreate(): void
    {
        $this->requirePost();
        $this->requireLedger();
        $this->requirePermission('kontor-ledger-account-manage');
        $code = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('code')
        )));
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $type = $this->wire()->sanitizer->text((string) $this->wire()->input->post('type'));
        $currency = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('currency_code')
        )));
        $parentUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('parent_uid')
        );
        if (preg_match('/^[A-Z0-9._-]{1,20}$/', $code) !== 1) {
            throw new WireException($this->_('Account code may contain letters, numbers, dots, underscores, and hyphens.'));
        }
        if ($name === '') {
            throw new WireException($this->_('Account name is required.'));
        }
        $this->requireAction($type, Account::TYPES);
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new WireException($this->_('Currency must be a three-letter code.'));
        }
        if ($parentUid !== '') {
            $parent = $this->ledgerModule()->accountRepository()->require($parentUid);
            $this->requireSameOrganization($parent->organizationId);
            if (!$parent->isActive()) {
                throw new WireException($this->_('Parent account must be active.'));
            }
        }
        $account = $this->ledgerModule()->chartOfAccounts()->createAccount(
            $this->organizationUid(),
            $code,
            mb_substr($name, 0, 255),
            $type,
            $currency,
            $parentUid ?: null,
            (int) $this->wire()->user->id,
        );
        $this->audit('ledger', 'account', $account->uid->toString(), 'created', current: [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
        ]);
        $this->message($this->_('Ledger account created.'));
        $this->wire()->session->redirect('../ledger/');
    }

    public function ___executeLedgerAccountAction(): void
    {
        $this->requirePost();
        $this->requireLedger();
        $this->requirePermission('kontor-ledger-account-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('account_uid')
        );
        $action = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        );
        $this->requireAction($action, ['archive', 'restore']);
        $account = $this->ledgerModule()->accountRepository()->require($uid);
        $this->requireSameOrganization($account->organizationId);
        if ($action === 'archive') {
            $this->ledgerModule()->chartOfAccounts()->archive($uid);
        } else {
            $this->ledgerModule()->chartOfAccounts()->restore($uid);
        }
        $this->audit('ledger', 'account', $uid, $action . 'd');
        $this->message($action === 'archive'
            ? $this->_('Ledger account archived.')
            : $this->_('Ledger account restored.'));
        $this->wire()->session->redirect('../ledger/');
    }

    public function ___executeLedgerEntryRecord(): void
    {
        $this->requirePost();
        $this->requireLedger();
        $this->requirePermission('kontor-ledger-entry-record');
        $description = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('description')
        ));
        if ($description === '') {
            throw new WireException($this->_('Entry description is required.'));
        }
        $dateText = trim((string) $this->wire()->input->post('entry_date'));
        $entryDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateText);
        if ($entryDate === false || $entryDate->format('Y-m-d') !== $dateText) {
            throw new WireException($this->_('Entry date must use YYYY-MM-DD.'));
        }
        $debitUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('debit_account_uid')
        );
        $creditUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('credit_account_uid')
        );
        if ($debitUid === $creditUid) {
            throw new WireException($this->_('Debit and credit accounts must differ.'));
        }
        $accounts = $this->ledgerModule()->accountRepository();
        $debitAccount = $accounts->require($debitUid);
        $creditAccount = $accounts->require($creditUid);
        $this->requireSameOrganization($debitAccount->organizationId);
        $this->requireSameOrganization($creditAccount->organizationId);
        if (!$debitAccount->isActive() || !$creditAccount->isActive()) {
            throw new WireException($this->_('Both ledger accounts must be active.'));
        }
        if ($debitAccount->currencyCode !== $creditAccount->currencyCode) {
            throw new WireException($this->_('Debit and credit accounts must use the same currency.'));
        }
        $amount = Money::ofMinor(
            $this->ledgerAmountMinorFromPost(),
            $debitAccount->currencyCode,
        );
        $referenceType = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('reference_type')
        ));
        $referenceUid = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('reference_uid')
        )));
        if (($referenceType === '') !== ($referenceUid === '')) {
            throw new WireException($this->_('Reference type and UID must be provided together.'));
        }
        if ($referenceUid !== '' && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $referenceUid) !== 1) {
            throw new WireException($this->_('Reference UID must be a valid ULID.'));
        }
        $entry = $this->ledgerModule()->entries()->record(
            $this->organizationUid(),
            mb_substr($description, 0, 500),
            $entryDate,
            [
                LedgerLineInput::debit($debitUid, $amount),
                LedgerLineInput::credit($creditUid, $amount),
            ],
            $referenceType ?: null,
            $referenceUid ?: null,
            (int) $this->wire()->user->id,
        );
        $this->audit('ledger', 'entry', $entry->uid->toString(), 'recorded', current: [
            'description' => $entry->description,
            'amountMinor' => $amount->amountMinor(),
            'currencyCode' => $amount->currencyCode(),
        ]);
        $this->message($this->_('Balanced ledger entry recorded.'));
        $this->wire()->session->redirect(
            '../ledger/?id=' . rawurlencode($entry->uid->toString())
        );
    }

    public function ___executeGermany(): string
    {
        $this->requireGermany();
        $this->requirePermission('kontor-germany-view');
        $module = $this->germanyModule();
        $definitions = $module->chartOfAccountsSeeder()->definitions();
        $chartStatus = [];
        foreach ($definitions as $definition) {
            $account = $this->ledgerModule()->accountRepository()->findByCode(
                $this->organizationUid(),
                $definition['code'],
            );
            $chartStatus[] = [
                'definition' => $definition,
                'account' => $account,
                'compatible' => $account === null || (
                    $account->name === $definition['name']
                    && $account->type === $definition['type']
                    && $account->currencyCode === 'EUR'
                ),
            ];
        }
        $taxResult = $this->wire()->session->get('kontorGermanyTaxResult');
        $xmlResult = $this->wire()->session->get('kontorGermanyXmlResult');
        $this->wire()->session->set('kontorGermanyTaxResult', null);
        $this->wire()->session->set('kontorGermanyXmlResult', null);
        $provider = $module->localizationProvider();
        $this->setPageTitle($this->_('Kontor · Germany'));

        return $this->renderTemplate('germany', [
            'countryCode' => $provider->countryCode(),
            'documentFormats' => $provider->supportedDocumentFormats(),
            'chartStatus' => $chartStatus,
            'taxResult' => is_array($taxResult) ? $taxResult : null,
            'xmlResult' => is_array($xmlResult) ? $xmlResult : null,
            'canConfigure' => $this->can('kontor-germany-configure'),
        ]);
    }

    public function ___executeGermanyTaxId(): void
    {
        $this->requirePost();
        $this->requireGermany();
        $this->requirePermission('kontor-germany-view');
        $taxId = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('tax_id')
        )));
        if ($taxId === '' || strlen($taxId) > 32) {
            throw new WireException($this->_('Enter a German VAT ID.'));
        }
        $valid = $this->germanyModule()->localizationProvider()->validateTaxId($taxId);
        $this->wire()->session->set('kontorGermanyTaxResult', [
            'taxId' => $taxId,
            'valid' => $valid,
        ]);
        $this->message($valid
            ? $this->_('VAT ID checksum is valid.')
            : $this->_('VAT ID checksum is invalid.'));
        $this->wire()->session->redirect('../germany/');
    }

    public function ___executeGermanySeedChart(): void
    {
        $this->requirePost();
        $this->requireGermany();
        $this->requirePermission('kontor-germany-configure');
        $seeder = $this->germanyModule()->chartOfAccountsSeeder();
        $before = [];
        foreach ($seeder->definitions() as $definition) {
            $existing = $this->ledgerModule()->accountRepository()->findByCode(
                $this->organizationUid(),
                $definition['code'],
            );
            if ($existing !== null) {
                $before[$existing->uid->toString()] = true;
            }
        }
        $accounts = $seeder->seed(
            $this->organizationUid(),
            (int) $this->wire()->user->id,
        );
        $created = 0;
        foreach ($accounts as $account) {
            if (isset($before[$account->uid->toString()])) {
                continue;
            }
            $created++;
            $this->audit('germany', 'ledger_account', $account->uid->toString(), 'seeded', current: [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ]);
        }
        $this->message(sprintf(
            $this->_('German chart ready: %d created, %d already present.'),
            $created,
            count($accounts) - $created,
        ));
        $this->wire()->session->redirect('../germany/');
    }

    public function ___executeGermanyFormat(): void
    {
        $this->requirePost();
        $this->requireGermany();
        $this->requirePermission('kontor-germany-view');
        $invoiceNumber = $this->germanyRequiredTextFromPost('invoice_number', 'Invoice number', 100);
        $issueDate = $this->germanyDateFromPost('issue_date', 'Issue date');
        $dueDateRaw = trim((string) $this->wire()->input->post('due_date'));
        $dueDate = $dueDateRaw !== ''
            ? $this->germanyDateFromPost('due_date', 'Due date')
            : null;
        if ($dueDate !== null && $dueDate < $issueDate) {
            throw new WireException($this->_('Due date cannot be before the issue date.'));
        }
        $sellerTaxId = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('seller_tax_id')
        )));
        if ($sellerTaxId !== ''
            && !$this->germanyModule()->localizationProvider()->validateTaxId($sellerTaxId)) {
            throw new WireException($this->_('Seller VAT ID checksum is invalid.'));
        }
        $sellerTaxId = str_replace([' ', '-'], '', $sellerTaxId);
        $quantityRaw = str_replace(',', '.', trim((string) $this->wire()->input->post('quantity')));
        if (preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $quantityRaw) !== 1
            || (float) $quantityRaw <= 0) {
            throw new WireException($this->_('Quantity must be positive with at most three decimal places.'));
        }
        $taxRateRaw = str_replace(',', '.', trim((string) $this->wire()->input->post('tax_rate')));
        if (preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $taxRateRaw) !== 1
            || (float) $taxRateRaw > 100) {
            throw new WireException($this->_('Tax rate must be between 0 and 100.'));
        }
        $unitPrice = Money::ofMinor(
            $this->positiveMoneyMinorFromPost('unit_price', 'Unit price'),
            'EUR',
        );
        $lineTotal = $unitPrice->multiply((float) $quantityRaw);
        $totalTax = Money::ofMinor(
            (int) round($lineTotal->amountMinor() * (float) $taxRateRaw / 100),
            'EUR',
        );
        $totalGross = $lineTotal->add($totalTax);
        $buyerCountryCode = strtoupper(
            $this->germanyRequiredTextFromPost('buyer_country_code', 'Buyer country', 2)
        );
        if (preg_match('/^[A-Z]{2}$/', $buyerCountryCode) !== 1) {
            throw new WireException($this->_('Buyer country must be a two-letter code.'));
        }
        $invoice = new LocalizedInvoiceInput(
            invoiceNumber: $invoiceNumber,
            issueDate: $issueDate,
            dueDate: $dueDate,
            currencyCode: 'EUR',
            seller: new LocalizedPartyInput(
                $this->germanyRequiredTextFromPost('seller_name', 'Seller name', 255),
                $this->germanyRequiredTextFromPost('seller_street', 'Seller street', 255),
                $this->germanyRequiredTextFromPost('seller_city', 'Seller city', 100),
                $this->germanyRequiredTextFromPost('seller_postal_code', 'Seller postal code', 20),
                'DE',
                $sellerTaxId ?: null,
            ),
            buyer: new LocalizedPartyInput(
                $this->germanyRequiredTextFromPost('buyer_name', 'Buyer name', 255),
                $this->germanyRequiredTextFromPost('buyer_street', 'Buyer street', 255),
                $this->germanyRequiredTextFromPost('buyer_city', 'Buyer city', 100),
                $this->germanyRequiredTextFromPost('buyer_postal_code', 'Buyer postal code', 20),
                $buyerCountryCode,
            ),
            lineItems: [
                new LocalizedLineItemInput(
                    $this->germanyRequiredTextFromPost('line_description', 'Line description', 255),
                    $quantityRaw,
                    $unitPrice,
                    $lineTotal,
                    $taxRateRaw,
                ),
            ],
            totalNet: $lineTotal,
            totalTax: $totalTax,
            totalGross: $totalGross,
        );
        $xml = $this->germanyModule()->documentFormatter()->format($invoice);
        $this->wire()->session->set('kontorGermanyXmlResult', [
            'invoiceNumber' => $invoiceNumber,
            'xml' => $xml,
            'bytes' => strlen($xml),
            'netMinor' => $lineTotal->amountMinor(),
            'taxMinor' => $totalTax->amountMinor(),
            'grossMinor' => $totalGross->amountMinor(),
        ]);
        $this->message($this->_('XRechnung preview generated.'));
        $this->wire()->session->redirect('../germany/');
    }

    public function ___executeMarketplace(): string
    {
        $this->requireMarketplace();
        $this->requirePermission('kontor-marketplace-advisory-view');
        $module = $this->marketplaceModule();
        $syncResult = $this->wire()->session->get('kontorMarketplaceSyncResult');
        $this->wire()->session->set('kontorMarketplaceSyncResult', null);
        $installability = [];
        foreach ($module->listingRepository()->all() as $listing) {
            $installability[$listing->registryName . ':' . $listing->package] =
                $module->installabilityChecker()->check(
                    $listing,
                    [],
                    PHP_VERSION,
                    '3.0.269',
                );
        }
        $this->setPageTitle($this->_('Kontor · Marketplace'));

        return $this->renderTemplate('marketplace', [
            'registries' => $module->registryRepository()->all(),
            'listings' => $module->listingRepository()->all(),
            'publishers' => $module->publisherRepository()->all(),
            'advisories' => $module->advisoryRepository()->all(),
            'installability' => $installability,
            'syncResult' => is_array($syncResult) ? $syncResult : null,
            'canManage' => $this->can('kontor-marketplace-registry-manage'),
        ]);
    }

    public function ___executeMarketplaceRegistry(): void
    {
        $this->requirePost();
        $this->requireMarketplace();
        $this->requirePermission('kontor-marketplace-registry-manage');
        $name = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $url = trim((string) $this->wire()->input->post('url'));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $name) !== 1
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array((string) parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new WireException($this->_('A valid registry name and HTTP URL are required.'));
        }
        $registry = $this->marketplaceModule()->registryManagement()->registerCustomRegistry(
            $name,
            mb_substr($url, 0, 2048),
            (bool) $this->wire()->input->post('trusted'),
        );
        $this->audit('marketplace', 'registry', $registry->name, 'created');
        $this->message($this->_('Custom registry added.'));
        $this->wire()->session->redirect('../marketplace/');
    }

    public function ___executeMarketplaceRegistryToggle(): void
    {
        $this->requirePost();
        $this->requireMarketplace();
        $this->requirePermission('kontor-marketplace-registry-manage');
        $name = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('registry_name')
        );
        $registry = $this->marketplaceModule()->registryRepository()->require($name);
        if ($registry->status === 'active') {
            $this->marketplaceModule()->registryManagement()->disable($name);
            $action = 'disabled';
        } else {
            $this->marketplaceModule()->registryManagement()->enable($name);
            $action = 'enabled';
        }
        $this->audit('marketplace', 'registry', $name, $action);
        $this->message(sprintf($this->_('Registry %s.'), $action));
        $this->wire()->session->redirect('../marketplace/');
    }

    public function ___executeMarketplaceImport(): void
    {
        $this->requirePost();
        $this->requireMarketplace();
        $this->requirePermission('kontor-marketplace-registry-manage');
        $registryName = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('registry_name')
        );
        $payload = trim((string) $this->wire()->input->post('payload_json'));
        if ($payload === '' || strlen($payload) > 1048576) {
            throw new WireException($this->_('Registry JSON is required and must be at most 1 MB.'));
        }
        try {
            $result = $this->marketplaceModule()->syncService()->syncPayload(
                $registryName,
                $payload,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Registry payload is not valid JSON.'));
        }
        $this->audit('marketplace', 'registry', $registryName, 'synchronized', metadata: [
            'listings' => $result->listingsSynced,
            'advisories' => $result->advisoriesSynced,
            'skipped' => count($result->skipped),
        ]);
        $this->wire()->session->set('kontorMarketplaceSyncResult', [
            'listings' => $result->listingsSynced,
            'advisories' => $result->advisoriesSynced,
            'skipped' => $result->skipped,
        ]);
        $this->message($this->_('Registry payload synchronized.'));
        $this->wire()->session->redirect('../marketplace/');
    }

    public function ___executeGraphql(): string
    {
        $this->requireGraphql();
        $this->requirePermission('kontor-api-token-manage');
        $result = $this->wire()->session->get('kontorGraphqlResult');
        $this->wire()->session->set('kontorGraphqlResult', null);
        $this->setPageTitle($this->_('Kontor · GraphQL'));

        return $this->renderTemplate('graphql', [
            'schema' => $this->graphqlModule()->schemaRegistry()->toSdl(),
            'types' => $this->graphqlModule()->schemaRegistry()->objectTypes(),
            'result' => is_array($result) ? $result : null,
            'sampleQuery' => "{\n  organizations(pageSize: 10) {\n    uid\n    name\n    status\n  }\n}",
        ]);
    }

    public function ___executeGraphqlRun(): void
    {
        $this->requirePost();
        $this->requireGraphql();
        $this->requirePermission('kontor-api-token-manage');
        $token = trim((string) $this->wire()->input->post('token'));
        $query = trim((string) $this->wire()->input->post('query'));
        if ($token === '' || $query === '') {
            throw new WireException($this->_('Bearer token and GraphQL query are required.'));
        }
        $request = new \Kontor\API\DTO\ApiHttpRequest(
            method: 'POST',
            path: 'graphql',
            queryParams: [],
            headers: ['authorization' => 'Bearer ' . $token],
            body: json_encode(['query' => $query], JSON_THROW_ON_ERROR),
        );
        $response = $this->graphqlModule()->requestHandler()->handle($request);
        $decoded = json_decode($response->body, true);
        $this->wire()->session->set('kontorGraphqlResult', [
            'status' => $response->status,
            'body' => is_array($decoded) ? $decoded : ['raw' => $response->body],
            'query' => $query,
        ]);
        $this->audit('graphql', 'query', Uid::generate()->toString(), 'executed', metadata: [
            'status' => $response->status,
            'hasErrors' => isset($decoded['errors']) && $decoded['errors'] !== [],
        ]);
        $this->wire()->session->redirect('../graphql/');
    }

    public function ___executeApi(): string
    {
        $this->requireApi();
        $this->requirePermission('kontor-api-token-manage');
        $module = $this->apiModule();
        $subscriptions = $this->can('kontor-api-webhook-manage')
            ? $module->webhookSubscriptionRepository()->forOrganization($this->organizationUid())
            : [];
        $deliveries = [];
        foreach ($subscriptions as $subscription) {
            foreach ($module->webhookDeliveryRepository()->forSubscription(
                $subscription->uid->toString()
            ) as $delivery) {
                $deliveries[] = $delivery;
            }
        }
        usort(
            $deliveries,
            static fn ($left, $right): int => $right->createdAt <=> $left->createdAt,
        );
        $secret = $this->wire()->session->get('kontorApiOneTimeSecret');
        $this->wire()->session->set('kontorApiOneTimeSecret', null);
        $this->setPageTitle($this->_('Kontor · API'));

        return $this->renderTemplate('api', [
            'tokens' => $module->tokenRepository()->forOrganization($this->organizationUid()),
            'subscriptions' => $subscriptions,
            'deliveries' => array_slice($deliveries, 0, 25),
            'resources' => $module->resourceRegistry()->all(),
            'openApi' => $module->openApiGenerator()->generate(),
            'oneTimeSecret' => is_array($secret) ? $secret : null,
            'canManageWebhooks' => $this->can('kontor-api-webhook-manage'),
        ]);
    }

    public function ___executeApiToken(): void
    {
        $this->requirePost();
        $this->requireApi();
        $this->requirePermission('kontor-api-token-manage');
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $scopes = array_values(array_unique(array_filter(array_map(
            static fn (string $scope): string => trim(strtolower($scope)),
            explode(',', (string) $this->wire()->input->post('scopes')),
        ))));
        $expires = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('expires_at')
        ));
        if ($name === '') {
            throw new WireException($this->_('Token name is required.'));
        }
        try {
            $expiresAt = $expires !== '' ? new \DateTimeImmutable($expires) : null;
        } catch (\Exception) {
            throw new WireException($this->_('Token expiry is invalid.'));
        }
        $issued = $this->apiModule()->authenticator()->issue(
            $this->organizationUid(),
            mb_substr($name, 0, 255),
            $scopes,
            $expiresAt,
            (int) $this->wire()->user->id,
        );
        $this->audit('api', 'token', $issued->token->uid->toString(), 'issued');
        $this->wire()->session->set('kontorApiOneTimeSecret', [
            'kind' => 'token',
            'label' => $issued->token->name,
            'value' => $issued->plaintext,
        ]);
        $this->message($this->_('API token issued.'));
        $this->wire()->session->redirect('../api/');
    }

    public function ___executeApiTokenRevoke(): void
    {
        $this->requirePost();
        $this->requireApi();
        $this->requirePermission('kontor-api-token-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('token_uid')
        );
        $token = $this->apiModule()->tokenRepository()->require($uid);
        $this->requireSameOrganization($token->organizationId);
        $this->apiModule()->authenticator()->revoke($uid);
        $this->audit('api', 'token', $uid, 'revoked');
        $this->message($this->_('API token revoked.'));
        $this->wire()->session->redirect('../api/');
    }

    public function ___executeApiWebhook(): void
    {
        $this->requirePost();
        $this->requireApi();
        $this->requirePermission('kontor-api-webhook-manage');
        $url = trim((string) $this->wire()->input->post('url'));
        $event = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('event_pattern')
        ));
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array((string) parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || preg_match('/^[a-z][a-z0-9._-]{1,149}$/', $event) !== 1) {
            throw new WireException($this->_('A valid HTTP URL and event name are required.'));
        }
        $secret = 'whsec_' . bin2hex(random_bytes(24));
        $subscription = \Kontor\API\Domain\WebhookSubscription::create(
            $this->organizationUid(),
            mb_substr($url, 0, 2048),
            $event,
            $secret,
            (int) $this->wire()->user->id,
        );
        $this->apiModule()->webhookSubscriptionRepository()->save($subscription);
        $this->audit('api', 'webhook', $subscription->uid->toString(), 'created');
        $this->wire()->session->set('kontorApiOneTimeSecret', [
            'kind' => 'webhook',
            'label' => $event,
            'value' => $secret,
        ]);
        $this->message($this->_('Webhook subscription created.'));
        $this->wire()->session->redirect('../api/');
    }

    public function ___executeApiWebhookArchive(): void
    {
        $this->requirePost();
        $this->requireApi();
        $this->requirePermission('kontor-api-webhook-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('subscription_uid')
        );
        $subscription = $this->apiModule()->webhookSubscriptionRepository()->require($uid);
        $this->requireSameOrganization($subscription->organizationId);
        $this->apiModule()->webhookSubscriptionRepository()->archive($uid);
        $this->audit('api', 'webhook', $uid, 'archived');
        $this->message($this->_('Webhook subscription archived.'));
        $this->wire()->session->redirect('../api/');
    }

    public function ___executeCustomEntities(): string
    {
        $this->requireEntities();
        $this->requirePermission('kontor-entities-record-view');
        $module = $this->entitiesModule();
        $definitions = array_values(array_filter(
            $module->definitionRepository()->forOrganization($this->organizationUid()),
            static fn ($definition): bool => $definition->isActive(),
        ));
        $recordCounts = [];
        foreach ($definitions as $definition) {
            $recordCounts[$definition->uid->toString()] = count(
                $module->recordRepository()->forDefinition($definition->uid->toString())
            );
        }
        $this->setPageTitle($this->_('Kontor · Custom entities'));

        return $this->renderTemplate('custom-entities', [
            'definitions' => $definitions,
            'recordCounts' => $recordCounts,
            'canManage' => $this->can('kontor-entities-definition-manage'),
        ]);
    }

    public function ___executeCustomEntity(): string
    {
        $this->requireEntities();
        $module = $this->entitiesModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $definition = $id !== '' ? $module->definitionRepository()->require($id) : null;
        if ($definition !== null) {
            $this->requireSameOrganization($definition->organizationId);
            $this->requireEntityViewPermission($definition);
        } else {
            $this->requirePermission('kontor-entities-definition-manage');
        }
        $values = [
            'entityKey' => '',
            'name' => '',
            'viewPermission' => 'kontor-entities-record-view',
            'editPermission' => 'kontor-entities-record-manage',
            'apiExposed' => false,
        ];
        $error = '';
        if ($definition === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'entityKey' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('entity_key')
                )),
                'name' => trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('name')
                )),
                'viewPermission' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('view_permission')
                )),
                'editPermission' => strtolower($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('edit_permission')
                )),
                'apiExposed' => (bool) $this->wire()->input->post('api_exposed'),
            ];
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $values['entityKey']) !== 1) {
                $error = $this->_('Entity key must be a lowercase identifier.');
            } elseif ($values['name'] === '') {
                $error = $this->_('Entity name is required.');
            }
            if ($error === '') {
                try {
                    $definition = $module->builder()->defineEntity(
                        $this->organizationUid(),
                        $values['entityKey'],
                        mb_substr($values['name'], 0, 191),
                        $values['viewPermission'] ?: null,
                        $values['editPermission'] ?: null,
                        $values['apiExposed'],
                        (int) $this->wire()->user->id,
                    );
                } catch (\PDOException $exception) {
                    $error = $exception->getCode() === '23000'
                        ? $this->_('That entity key is already in use.')
                        : $this->_('Entity definition could not be saved.');
                }
                if ($error === '') {
                    $this->audit('entities', 'definition', $definition->uid->toString(), 'created');
                    $this->message($this->_('Custom entity created.'));
                    $this->wire()->session->redirect(
                        '../custom-entity/?id=' . rawurlencode($definition->uid->toString())
                    );
                }
            }
        }

        $fields = $definition !== null
            ? $module->fieldRepository()->forDefinition($definition->uid->toString())
            : [];
        $views = $definition !== null
            ? $module->viewRepository()->forDefinition($definition->uid->toString())
            : [];
        $viewId = $this->wire()->sanitizer->text((string) $this->wire()->input->get('view'));
        $selectedView = $viewId !== '' ? $module->viewRepository()->require($viewId) : null;
        if ($selectedView !== null && ($definition === null
            || $selectedView->definitionUid !== $definition->uid->toString()
            || $selectedView->organizationId !== $this->organizationUid())) {
            throw new WirePermissionException($this->_('Saved view does not belong to this entity.'));
        }
        $records = $definition !== null
            ? ($selectedView !== null
                ? $module->views()->apply($selectedView)
                : $module->recordRepository()->forDefinition($definition->uid->toString()))
            : [];
        $this->setPageTitle($definition === null
            ? $this->_('Kontor · New custom entity')
            : sprintf($this->_('Kontor · %s'), $definition->name));

        return $this->renderTemplate('custom-entity', [
            'definition' => $definition,
            'values' => $values,
            'error' => $error,
            'fields' => $fields,
            'views' => $views,
            'selectedView' => $selectedView,
            'records' => $records,
            'schema' => $definition !== null ? $module->schema()->describe($definition->uid->toString()) : null,
            'canDefine' => $this->can('kontor-entities-definition-manage'),
            'canManageRecords' => $definition !== null && $this->canEditEntity($definition),
            'canManageViews' => $this->can('kontor-entities-view-manage'),
        ]);
    }

    public function ___executeCustomEntityField(): void
    {
        $this->requirePost();
        $this->requireEntities();
        $this->requirePermission('kontor-entities-definition-manage');
        $definition = $this->requireEntityDefinitionFromPost();
        $key = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('field_key')
        ));
        $label = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('label')
        ));
        $type = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('field_type'),
            \Kontor\Entities\Domain\EntityField::TYPES,
        );
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1 || $label === '' || $type === null) {
            throw new WireException($this->_('Field key, label, and supported type are required.'));
        }
        $field = $this->entitiesModule()->builder()->addField(
            $definition->uid->toString(),
            $key,
            mb_substr($label, 0, 191),
            $type,
            (bool) $this->wire()->input->post('required'),
        );
        $this->audit('entities', 'field', $field->uid->toString(), 'created');
        $this->message($this->_('Entity field added.'));
        $this->redirectToEntityDefinition($definition->uid->toString());
    }

    public function ___executeCustomEntityRecord(): string
    {
        $this->requireEntities();
        $module = $this->entitiesModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $record = $id !== '' ? $module->recordRepository()->require($id) : null;
        $definitionId = $record?->definitionUid
            ?? $this->wire()->sanitizer->text(
                (string) (
                    $this->wire()->input->get('definition')
                    ?: $this->wire()->input->post('definition_uid')
                )
            );
        $definition = $module->definitionRepository()->require($definitionId);
        $this->requireSameOrganization($definition->organizationId);
        $this->requireEntityViewPermission($definition);
        if ($record !== null) {
            $this->requireSameOrganization($record->organizationId);
        }
        $fields = $module->fieldRepository()->forDefinition($definitionId);
        $error = '';
        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $this->requireEntityEditPermission($definition);
            try {
                $data = $this->entityRecordDataFromPost($fields);
                $record = $record === null
                    ? $module->records()->create($definitionId, $data, (int) $this->wire()->user->id)
                    : $module->records()->update($record->uid->toString(), $data);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            if ($error === '') {
                $this->audit(
                    'entities',
                    'record',
                    $record->uid->toString(),
                    $id === '' ? 'created' : 'updated',
                );
                $this->message($this->_('Custom entity record saved.'));
                $this->wire()->session->redirect(
                    '../custom-entity-record/?id=' . rawurlencode($record->uid->toString())
                );
            }
        }
        $relations = $record !== null
            ? $module->relations()->relatedTo(
                $this->organizationUid(),
                $definition->entityKey,
                $record->uid->toString(),
            )
            : [];
        $this->setPageTitle(sprintf(
            $this->_('Kontor · %s record'),
            $definition->name,
        ));

        return $this->renderTemplate('custom-entity-record', [
            'definition' => $definition,
            'record' => $record,
            'fields' => $fields,
            'error' => $error,
            'relations' => $relations,
            'canEdit' => $this->canEditEntity($definition),
        ]);
    }

    public function ___executeCustomEntityView(): void
    {
        $this->requirePost();
        $this->requireEntities();
        $this->requirePermission('kontor-entities-view-manage');
        $definition = $this->requireEntityDefinitionFromPost();
        $fields = $this->entitiesModule()->fieldRepository()->forDefinition(
            $definition->uid->toString()
        );
        $fieldKeys = array_map(static fn ($field): string => $field->fieldKey, $fields);
        $name = trim($this->wire()->sanitizer->text((string) $this->wire()->input->post('name')));
        $filterField = $this->wire()->sanitizer->text((string) $this->wire()->input->post('filter_field'));
        $filterOperator = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('filter_operator'),
            ['equals', 'not_equals', 'greater_than', 'less_than', 'contains'],
        );
        $filterValue = $this->wire()->sanitizer->text((string) $this->wire()->input->post('filter_value'));
        $sortField = $this->wire()->sanitizer->text((string) $this->wire()->input->post('sort_field'));
        $sortDirection = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('sort_direction'),
            ['asc', 'desc'],
        ) ?? 'asc';
        if ($name === '' || ($filterField !== '' && !in_array($filterField, $fieldKeys, true))
            || ($sortField !== '' && !in_array($sortField, $fieldKeys, true))) {
            throw new WireException($this->_('Saved-view configuration is invalid.'));
        }
        $view = $this->entitiesModule()->views()->createView(
            $this->organizationUid(),
            $definition->uid->toString(),
            mb_substr($name, 0, 191),
            $filterField !== '' ? [[
                'field' => $filterField,
                'operator' => $filterOperator ?? 'equals',
                'value' => $filterValue,
            ]] : [],
            $sortField !== '' ? [['field' => $sortField, 'direction' => $sortDirection]] : [],
            $fieldKeys,
            (int) $this->wire()->user->id,
        );
        $this->audit('entities', 'view', $view->uid->toString(), 'created');
        $this->message($this->_('Saved view created.'));
        $this->wire()->session->redirect(
            '../custom-entity/?id=' . rawurlencode($definition->uid->toString())
                . '&view=' . rawurlencode($view->uid->toString())
        );
    }

    public function ___executeCustomEntityRelation(): void
    {
        $this->requirePost();
        $this->requireEntities();
        $recordUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('record_uid')
        );
        $record = $this->entitiesModule()->recordRepository()->require($recordUid);
        $definition = $this->entitiesModule()->definitionRepository()->require($record->definitionUid);
        $this->requireSameOrganization($record->organizationId);
        $this->requireEntityEditPermission($definition);
        $targetType = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('target_type')
        );
        $targetUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('target_uid')
        );
        $relationType = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('relation_type')
        );
        if ($targetType === '' || $targetUid === '' || $relationType === '') {
            throw new WireException($this->_('Relation target and type are required.'));
        }
        $relationUid = $this->entitiesModule()->relations()->linkRecords(
            $this->organizationUid(),
            $definition->entityKey,
            $recordUid,
            $targetType,
            $targetUid,
            $relationType,
            'directed',
        );
        $this->audit('entities', 'relation', $relationUid, 'created');
        $this->message($this->_('Record relation created.'));
        $this->wire()->session->redirect(
            '../custom-entity-record/?id=' . rawurlencode($recordUid)
        );
    }

    public function ___executeCompany(): string
    {
        $this->requireContacts();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $company = $id !== '' ? $this->companyRepository()->require($id) : null;
        $this->requirePermission($company === null
            ? 'kontor-contacts-company-create'
            : 'kontor-contacts-company-edit');
        $this->setPageTitle($company === null
            ? $this->_('Kontor · New company')
            : sprintf($this->_('Kontor · %s'), $company->legalName));

        $isNew = $company === null;
        $previous = $company === null ? null : $this->companyAuditSnapshot($company);
        $form = $this->buildCompanyForm($company);

        if ($this->wire()->input->post('submit_save')) {
            $form->processInput($this->wire()->input->post);

            if (!$form->getErrors()) {
                $company = $this->saveCompanyFromForm($form, $company);
                $this->audit(
                    component: 'contacts',
                    entityType: 'company',
                    entityUid: $company->uid->toString(),
                    action: $isNew ? 'created' : 'updated',
                    previous: $previous,
                    current: $this->companyAuditSnapshot($company),
                );
                $this->message($this->_('Company saved.'));
                $this->wire()->session->redirect('../company/?id=' . rawurlencode($company->uid->toString()));
            }
        }

        $relationships = $company === null ? [] : $this->companyRelationships($company);

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../companies/',
            'backLabel' => $this->_('Back to companies'),
            'eyebrow' => $this->_('Companies'),
            'title' => $company?->legalName ?: $this->_('Create company'),
            'description' => $company === null
                ? $this->_('Add an organization, customer or partner.')
                : $this->_('Manage commercial identity and contact information.'),
            'entity' => $company,
            'entityType' => 'company',
            'relationships' => $relationships,
            'availableCompanies' => [],
            'addresses' => $company === null
                ? []
                : $this->addressRepository()->forOwner('company', $company->uid->toString()),
            'duplicates' => [],
            'tags' => $company === null
                ? []
                : $this->tagService()->tagsFor(
                    $this->organizationUid(),
                    'company',
                    $company->uid->toString()
                ),
        ]);
    }

    public function ___executeSearch(): string
    {
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Search'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $availableEntityTypes = [
            'contact' => $this->_('Contacts'),
            'company' => $this->_('Companies'),
        ];

        if ($this->catalogReady()) {
            $availableEntityTypes['catalog_item'] = $this->_('Catalog items');
        }

        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('type'),
            array_keys($availableEntityTypes)
        ) ?? '';
        $pageSize = 20;
        $page = max(1, (int) $this->wire()->input->get('page'));
        $totalPages = 1;
        $result = null;

        if (mb_strlen($query) >= 2) {
            $result = $this->searchService()->search(new SearchQuery(
                organizationId: $this->organizationUid(),
                term: $query,
                entityTypes: $entityType !== '' ? [$entityType] : array_keys($availableEntityTypes),
                limit: $pageSize,
                offset: ($page - 1) * $pageSize,
            ));
            $totalPages = max(1, (int) ceil($result->total / $pageSize));

            if ($page > $totalPages) {
                $page = $totalPages;
                $result = $this->searchService()->search(new SearchQuery(
                    organizationId: $this->organizationUid(),
                    term: $query,
                    entityTypes: $entityType !== '' ? [$entityType] : array_keys($availableEntityTypes),
                    limit: $pageSize,
                    offset: ($page - 1) * $pageSize,
                ));
            }
        }

        return $this->renderTemplate('search', [
            'query' => $query,
            'result' => $result,
            'selectedEntityType' => $entityType,
            'availableEntityTypes' => $availableEntityTypes,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function ___executeCatalog(): string
    {
        $this->requirePermission('kontor-catalog-item-view');
        $this->requireCatalog();
        $this->setPageTitle($this->_('Kontor · Catalog'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $itemType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('type'),
            ['product', 'service']
        );
        $categoryUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('category')
        );
        $categoryUid = $categoryUid !== '' ? $categoryUid : null;
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'inactive', 'discontinued']
        );
        $inventory = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('inventory'),
            ['tracked', 'untracked']
        );
        $unitOptions = (new UnitOfMeasure())->all();
        $taxOptions = (new TaxCode())->all();
        $unitCode = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('unit'),
            array_keys($unitOptions)
        );
        $taxCode = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('tax'),
            array_keys($taxOptions)
        );
        $salesCurrency = strtoupper($this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('currency')
        ));
        $salesCurrency = preg_match('/^[A-Z]{3}$/', $salesCurrency) === 1
            ? $salesCurrency
            : null;
        $pricing = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('pricing'),
            ['priced', 'unpriced']
        );
        $hasSalesPrice = $pricing === null ? null : $pricing === 'priced';
        $trackInventory = $inventory === null ? null : $inventory === 'tracked';
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalItems = $this->catalogItemRepository()->countMatching(
            organizationUid: $organizationUid,
            query: $query,
            itemType: $itemType,
            archived: $showArchived,
            categoryUid: $categoryUid,
            status: $status,
            trackInventory: $trackInventory,
            unitCode: $unitCode,
            taxCode: $taxCode,
            salesCurrency: $salesCurrency,
            hasSalesPrice: $hasSalesPrice,
        );
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));
        $items = $this->catalogItemRepository()->findAll(
            organizationUid: $organizationUid,
            query: $query,
            itemType: $itemType,
            archived: $showArchived,
            categoryUid: $categoryUid,
            limit: $pageSize,
            offset: ($page - 1) * $pageSize,
            status: $status,
            trackInventory: $trackInventory,
            unitCode: $unitCode,
            taxCode: $taxCode,
            salesCurrency: $salesCurrency,
            hasSalesPrice: $hasSalesPrice,
        );
        $categoryOptions = [];
        $categoryNames = [];

        foreach ($this->categoryRepository()->findAll($organizationUid, limit: 250) as $category) {
            $uid = $category->uid->toString();
            $categoryOptions[$uid] = $categoryNames[$uid] = $this->categoryName($category);
        }

        foreach ($this->categoryRepository()->findAll($organizationUid, archived: true, limit: 250) as $category) {
            $categoryNames[$category->uid->toString()] = $this->categoryName($category) . ' · archived';
        }

        if (
            $categoryUid !== null
            && $categoryUid !== 'uncategorized'
            && !isset($categoryOptions[$categoryUid])
        ) {
            $selectedCategory = $this->categoryRepository()->find($categoryUid);

            if ($selectedCategory !== null && hash_equals($selectedCategory->organizationId, $organizationUid)) {
                $categoryOptions[$categoryUid] = $categoryNames[$categoryUid] ?? $this->categoryName($selectedCategory);
            }
        }

        return $this->renderTemplate('catalog', [
            'items' => $items,
            'query' => $query,
            'selectedType' => $itemType,
            'selectedCategory' => $categoryUid,
            'selectedStatus' => $status,
            'selectedInventory' => $inventory,
            'selectedUnit' => $unitCode,
            'selectedTax' => $taxCode,
            'selectedCurrency' => $salesCurrency,
            'selectedPricing' => $pricing,
            'currencyOptions' => $this->catalogItemRepository()->salesCurrencies($organizationUid),
            'unitOptions' => $unitOptions,
            'taxOptions' => $taxOptions,
            'categoryOptions' => $categoryOptions,
            'categoryNames' => $categoryNames,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'unitLabels' => $unitOptions,
        ]);
    }

    public function ___executeCatalogReferences(): string
    {
        $this->requirePermission('kontor-catalog-item-view');
        $this->requireCatalog();
        $this->setPageTitle($this->_('Kontor · Catalog references'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $selectedType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('type'),
            ['unit', 'tax']
        );
        $usage = $this->catalogItemRepository()->referenceUsage($this->organizationUid());
        $references = [];

        foreach ([
            'unit' => [(new UnitOfMeasure())->all(), $usage['units']],
            'tax' => [(new TaxCode())->all(), $usage['taxes']],
        ] as $type => [$options, $counts]) {
            if ($selectedType !== null && $selectedType !== $type) {
                continue;
            }

            foreach ($options as $code => $label) {
                if (
                    $query !== ''
                    && !str_contains(mb_strtolower($code . ' ' . $label), mb_strtolower($query))
                ) {
                    continue;
                }

                $references[] = [
                    'type' => $type,
                    'code' => $code,
                    'label' => $label,
                    'usage' => $counts[$code] ?? 0,
                ];
            }
        }

        usort(
            $references,
            static fn (array $left, array $right): int => [$left['type'], $left['label']]
                <=> [$right['type'], $right['label']]
        );

        return $this->renderTemplate('catalog-references', [
            'references' => $references,
            'query' => $query,
            'selectedType' => $selectedType,
            'totalReferences' => count((new UnitOfMeasure())->all()) + count((new TaxCode())->all()),
        ]);
    }

    public function ___executeCatalogItem(): string
    {
        $this->requireCatalog();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $item = $id !== '' ? $this->catalogItemRepository()->require($id) : null;

        if ($item !== null) {
            $this->requireSameOrganization($item->organizationId);
        }

        $this->requirePermission($item === null
            ? 'kontor-catalog-item-create'
            : 'kontor-catalog-item-edit');
        $this->setPageTitle($item === null
            ? $this->_('Kontor · New catalog item')
            : sprintf($this->_('Kontor · %s'), $this->catalogItemTitle($item)));
        $form = $this->buildCatalogItemForm($item);
        $isNew = $item === null;
        $previous = $item === null ? null : $this->catalogItemAuditSnapshot($item);

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $form->processInput($this->wire()->input->post);
            $this->validateCatalogItemForm($form, $item);

            if (!$form->getErrors()) {
                $item = $this->saveCatalogItemFromForm($form, $item);
                $this->audit(
                    'catalog',
                    'catalog_item',
                    $item->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    previous: $previous,
                    current: $this->catalogItemAuditSnapshot($item),
                );
                $this->message($this->_('Catalog item saved.'));
                $this->wire()->session->redirect(
                    '../catalog-item/?id=' . rawurlencode($item->uid->toString())
                );
            }
        }

        $user = $this->wire()->user;
        $canViewPriceLists = $item !== null
            && ($user->isSuperuser() || $user->hasPermission('kontor-catalog-pricelist-view'));
        $canEditPriceLists = $item !== null
            && ($user->isSuperuser() || $user->hasPermission('kontor-catalog-pricelist-edit'));
        $priceEntries = $canViewPriceLists
            ? $this->priceRepository()->forCatalogItem(
                $this->organizationUid(),
                $item->uid->toString(),
            )
            : [];
        $priceListDetails = [];

        if ($canViewPriceLists) {
            foreach ($this->priceListRepository()->findAll($this->organizationUid(), limit: 250) as $priceList) {
                $priceListDetails[$priceList->uid->toString()] = [
                    'name' => $priceList->name,
                    'currency' => $priceList->currencyCode,
                    'status' => $priceList->status,
                ];
            }
        }

        return $this->renderTemplate('catalog-form', [
            'form' => $form,
            'item' => $item,
            'canViewPriceLists' => $canViewPriceLists,
            'canEditPriceLists' => $canEditPriceLists,
            'priceEntries' => $priceEntries,
            'priceListDetails' => $priceListDetails,
            'title' => $item === null ? $this->_('Create catalog item') : $this->catalogItemTitle($item),
        ]);
    }

    public function ___executeCatalogItemAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-item-archive');
        $redirect = $this->catalogListRedirect();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $item = $this->catalogItemRepository()->require($id);
        $this->requireSameOrganization($item->organizationId);

        $action === 'restore'
            ? $this->catalogItemRepository()->restore($id)
            : $this->catalogItemRepository()->archive($id);
        $this->audit(
            'catalog',
            'catalog_item',
            $id,
            $action === 'restore' ? 'restored' : 'archived',
        );
        $this->message($action === 'restore'
            ? $this->_('Catalog item restored.')
            : $this->_('Catalog item archived.'));
        $this->wire()->session->redirect($redirect);
    }

    public function ___executeCatalogItemDuplicate(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-item-create');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $source = $this->catalogItemRepository()->require($id);
        $this->requireSameOrganization($source->organizationId);
        $duplicate = $source->duplicate($this->_(' (copy)'));
        $this->catalogItemRepository()->save($duplicate);
        $this->audit(
            'catalog',
            'catalog_item',
            $duplicate->uid->toString(),
            'created',
            current: $this->catalogItemAuditSnapshot($duplicate),
            metadata: ['duplicatedFrom' => $source->uid->toString()],
        );
        $this->message($this->_('Catalog item duplicated as an inactive draft.'));
        $this->wire()->session->redirect(
            '../catalog-item/?id=' . rawurlencode($duplicate->uid->toString())
        );
    }

    public function ___executeCatalogBulkAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore', 'activate', 'deactivate', 'discontinue']
        );
        $this->requireAction($action, ['archive', 'restore', 'activate', 'deactivate', 'discontinue']);
        $this->requirePermission(in_array($action, ['archive', 'restore'], true)
            ? 'kontor-catalog-item-archive'
            : 'kontor-catalog-item-edit');
        $ids = $this->wire()->sanitizer->arrayVal(
            $this->wire()->input->post('ids'),
            ['maxItems' => 100, 'sanitizer' => 'text']
        );
        $redirect = $this->catalogListRedirect();

        if ($ids === []) {
            $this->warning($this->_('Select at least one catalog item.'));
            $this->wire()->session->redirect($redirect);
        }

        $changedIds = match ($action) {
            'restore' => $this->catalogItemRepository()->restoreMany($this->organizationUid(), $ids),
            'activate' => $this->catalogItemRepository()->activateMany($this->organizationUid(), $ids),
            'deactivate' => $this->catalogItemRepository()->deactivateMany($this->organizationUid(), $ids),
            'discontinue' => $this->catalogItemRepository()->discontinueMany($this->organizationUid(), $ids),
            default => $this->catalogItemRepository()->archiveMany($this->organizationUid(), $ids),
        };

        foreach ($changedIds as $id) {
            $this->audit(
                'catalog',
                'catalog_item',
                $id,
                match ($action) {
                    'restore' => 'restored',
                    'activate' => 'activated',
                    'deactivate' => 'deactivated',
                    'discontinue' => 'discontinued',
                    default => 'archived',
                },
                metadata: ['bulk' => true],
            );
        }

        $this->message(sprintf(
            match ($action) {
                'restore' => $this->_('%d catalog item(s) restored.'),
                'activate' => $this->_('%d catalog item(s) activated.'),
                'deactivate' => $this->_('%d catalog item(s) deactivated.'),
                'discontinue' => $this->_('%d catalog item(s) discontinued.'),
                default => $this->_('%d catalog item(s) archived.'),
            },
            count($changedIds)
        ));
        $this->wire()->session->redirect($redirect);
    }

    public function ___executeCatalogCategories(): string
    {
        $this->requirePermission('kontor-catalog-category-view');
        $this->requireCatalog();
        $this->setPageTitle($this->_('Kontor · Catalog categories'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'inactive']
        );
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalCategories = $this->categoryRepository()->countMatching(
            organizationUid: $organizationUid,
            query: $query,
            archived: $showArchived,
            status: $status,
        );
        $totalPages = max(1, (int) ceil($totalCategories / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));
        $categories = $this->categoryRepository()->findAll(
            organizationUid: $organizationUid,
            query: $query,
            archived: $showArchived,
            limit: $pageSize,
            offset: ($page - 1) * $pageSize,
            status: $status,
        );
        $categoryNames = [];

        foreach ($this->categoryRepository()->findAll($organizationUid, limit: 250) as $category) {
            $categoryNames[$category->uid->toString()] = $this->categoryName($category);
        }

        return $this->renderTemplate('catalog-categories', [
            'categories' => $categories,
            'categoryNames' => $categoryNames,
            'itemCounts' => $this->catalogItemRepository()->categoryUsage($organizationUid),
            'displayLanguage' => $this->organization()->defaultLanguage,
            'query' => $query,
            'selectedStatus' => $status,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCategories' => $totalCategories,
        ]);
    }

    public function ___executeCatalogCategory(): string
    {
        $this->requireCatalog();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $category = $id !== '' ? $this->categoryRepository()->require($id) : null;

        if ($category !== null) {
            $this->requireSameOrganization($category->organizationId);
        }

        $this->requirePermission($category === null
            ? 'kontor-catalog-category-create'
            : 'kontor-catalog-category-edit');
        $this->setPageTitle($category === null
            ? $this->_('Kontor · New category')
            : sprintf($this->_('Kontor · %s'), $this->categoryName($category)));
        $form = $this->buildCatalogCategoryForm($category);
        $isNew = $category === null;
        $previous = $category === null ? null : $this->catalogCategoryAuditSnapshot($category);

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $form->processInput($this->wire()->input->post);
            $this->validateCatalogCategoryForm($form, $category);

            if (!$form->getErrors()) {
                $category = $this->saveCatalogCategoryFromForm($form, $category);
                $this->audit(
                    'catalog',
                    'catalog_category',
                    $category->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    previous: $previous,
                    current: $this->catalogCategoryAuditSnapshot($category),
                );
                $this->message($this->_('Catalog category saved.'));
                $this->wire()->session->redirect(
                    '../catalog-category/?id=' . rawurlencode($category->uid->toString())
                );
            }
        }

        return $this->renderTemplate('catalog-category-form', [
            'form' => $form,
            'title' => $category === null ? $this->_('Create category') : $this->categoryName($category),
        ]);
    }

    public function ___executeCatalogCategoryAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-category-edit');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $category = $this->categoryRepository()->require($id);
        $this->requireSameOrganization($category->organizationId);

        $action === 'restore'
            ? $this->categoryRepository()->restore($id)
            : $this->categoryRepository()->archive($id);
        $this->audit(
            'catalog',
            'catalog_category',
            $id,
            $action === 'restore' ? 'restored' : 'archived',
        );
        $this->message($action === 'restore'
            ? $this->_('Catalog category restored.')
            : $this->_('Catalog category archived.'));
        $this->wire()->session->redirect(
            '../catalog-categories/' . ($action === 'restore' ? '?archived=1' : '')
        );
    }

    public function ___executeCatalogCategoryBulkAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-category-edit');
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore', 'activate', 'deactivate']
        );
        $this->requireAction($action, ['archive', 'restore', 'activate', 'deactivate']);
        $ids = $this->wire()->sanitizer->arrayVal(
            $this->wire()->input->post('ids'),
            ['maxItems' => 100, 'sanitizer' => 'text']
        );
        $redirect = $this->catalogCategoryListRedirect();

        if ($ids === []) {
            $this->warning($this->_('Select at least one catalog category.'));
            $this->wire()->session->redirect($redirect);
        }

        $changedIds = match ($action) {
            'restore' => $this->categoryRepository()->restoreMany($this->organizationUid(), $ids),
            'activate' => $this->categoryRepository()->activateMany($this->organizationUid(), $ids),
            'deactivate' => $this->categoryRepository()->deactivateMany($this->organizationUid(), $ids),
            default => $this->categoryRepository()->archiveMany($this->organizationUid(), $ids),
        };

        foreach ($changedIds as $id) {
            $this->audit(
                'catalog',
                'catalog_category',
                $id,
                match ($action) {
                    'restore' => 'restored',
                    'activate' => 'activated',
                    'deactivate' => 'deactivated',
                    default => 'archived',
                },
                metadata: ['bulk' => true],
            );
        }

        $this->message(sprintf(
            match ($action) {
                'restore' => $this->_('%d catalog category(s) restored.'),
                'activate' => $this->_('%d catalog category(s) activated.'),
                'deactivate' => $this->_('%d catalog category(s) deactivated.'),
                default => $this->_('%d catalog category(s) archived.'),
            },
            count($changedIds),
        ));
        $this->wire()->session->redirect($redirect);
    }

    public function ___executeCatalogPriceLists(): string
    {
        $this->requirePermission('kontor-catalog-pricelist-view');
        $this->requireCatalog();
        $this->setPageTitle($this->_('Kontor · Price lists'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'inactive']
        );
        $validity = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('validity'),
            ['current', 'upcoming', 'expired']
        );
        $currency = strtoupper($this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('currency')
        ));
        $currency = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalPriceLists = $this->priceListRepository()->countMatching(
            organizationUid: $organizationUid,
            query: $query,
            status: $status,
            validity: $validity,
            currencyCode: $currency,
        );
        $totalPages = max(1, (int) ceil($totalPriceLists / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));
        $priceLists = $this->priceListRepository()->findAll(
            organizationUid: $organizationUid,
            query: $query,
            status: $status,
            limit: $pageSize,
            offset: ($page - 1) * $pageSize,
            validity: $validity,
            currencyCode: $currency,
        );
        $currencyOptions = [];

        foreach ($this->priceListRepository()->findAll($organizationUid, limit: 250) as $priceList) {
            $currencyOptions[$priceList->currencyCode] = $priceList->currencyCode;
        }
        ksort($currencyOptions);

        return $this->renderTemplate('catalog-price-lists', [
            'priceLists' => $priceLists,
            'entryCounts' => $this->priceEntryCounts($priceLists),
            'query' => $query,
            'selectedStatus' => $status,
            'selectedValidity' => $validity,
            'selectedCurrency' => $currency,
            'currencyOptions' => $currencyOptions,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalPriceLists' => $totalPriceLists,
        ]);
    }

    public function ___executeCatalogPriceList(): string
    {
        $this->requireCatalog();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $priceList = $id !== '' ? $this->priceListRepository()->require($id) : null;

        if ($priceList !== null) {
            $this->requireSameOrganization($priceList->organizationId);
        }

        $this->requirePermission($priceList === null
            ? 'kontor-catalog-pricelist-create'
            : 'kontor-catalog-pricelist-edit');
        $this->setPageTitle($priceList === null
            ? $this->_('Kontor · New price list')
            : sprintf($this->_('Kontor · %s'), $priceList->name));
        $form = $this->buildCatalogPriceListForm($priceList);
        $isNew = $priceList === null;
        $previous = $priceList === null ? null : $this->catalogPriceListAuditSnapshot($priceList);

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $form->processInput($this->wire()->input->post);
            $this->validateCatalogPriceListForm($form, $priceList);

            if (!$form->getErrors()) {
                $priceList = $this->saveCatalogPriceListFromForm($form, $priceList);
                $this->audit(
                    'catalog',
                    'catalog_price_list',
                    $priceList->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    previous: $previous,
                    current: $this->catalogPriceListAuditSnapshot($priceList),
                );
                $this->message($this->_('Price list saved.'));
                $this->wire()->session->redirect(
                    '../catalog-price-list/?id=' . rawurlencode($priceList->uid->toString())
                );
            }
        }

        $entries = $priceList !== null
            ? $this->priceRepository()->forPriceList($priceList->uid->toString())
            : [];

        return $this->renderTemplate('catalog-price-list-form', [
            'form' => $form,
            'priceList' => $priceList,
            'entries' => $entries,
            'itemNames' => $this->catalogItemNames(),
            'canDuplicatePriceList' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-catalog-pricelist-create'),
            'title' => $priceList === null ? $this->_('Create price list') : $priceList->name,
        ]);
    }

    public function ___executeCatalogPriceListDuplicate(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-pricelist-create');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $source = $this->priceListRepository()->require($id);
        $this->requireSameOrganization($source->organizationId);
        $tierCount = count($this->priceRepository()->forPriceList($id));
        $duplicate = $this->priceListDuplicator()->duplicate($source, $this->_(' (copy)'));

        $this->audit(
            'catalog',
            'catalog_price_list',
            $duplicate->uid->toString(),
            'created',
            current: $this->catalogPriceListAuditSnapshot($duplicate),
            metadata: [
                'duplicatedFrom' => $source->uid->toString(),
                'priceTierCount' => $tierCount,
            ],
        );
        $this->message(sprintf(
            $this->_('Price list duplicated as an inactive draft with %d price tier(s).'),
            $tierCount,
        ));
        $this->wire()->session->redirect(
            '../catalog-price-list/?id=' . rawurlencode($duplicate->uid->toString())
        );
    }

    public function ___executeCatalogPriceListBulkAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-pricelist-edit');
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['activate', 'deactivate']
        );
        $this->requireAction($action, ['activate', 'deactivate']);
        $ids = $this->wire()->sanitizer->arrayVal(
            $this->wire()->input->post('ids'),
            ['maxItems' => 100, 'sanitizer' => 'text']
        );
        $redirect = $this->catalogPriceListRedirect();

        if ($ids === []) {
            $this->warning($this->_('Select at least one price list.'));
            $this->wire()->session->redirect($redirect);
        }

        $changedIds = $action === 'activate'
            ? $this->priceListRepository()->activateMany($this->organizationUid(), $ids)
            : $this->priceListRepository()->deactivateMany($this->organizationUid(), $ids);

        foreach ($changedIds as $id) {
            $this->audit(
                'catalog',
                'catalog_price_list',
                $id,
                $action === 'activate' ? 'activated' : 'deactivated',
                metadata: ['bulk' => true],
            );
        }

        $this->message(sprintf(
            $action === 'activate'
                ? $this->_('%d price list(s) activated.')
                : $this->_('%d price list(s) deactivated.'),
            count($changedIds),
        ));
        $this->wire()->session->redirect($redirect);
    }

    public function ___executeCatalogPriceEntry(): string
    {
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-pricelist-edit');
        $priceListUid = $this->wire()->sanitizer->text((string) $this->wire()->input->get('list'));
        $priceList = $this->priceListRepository()->require($priceListUid);
        $this->requireSameOrganization($priceList->organizationId);
        $itemUid = $this->wire()->sanitizer->text((string) $this->wire()->input->get('item'));
        $quantityText = $this->wire()->sanitizer->text((string) $this->wire()->input->get('quantity'));
        $prefillItemUid = $itemUid !== '' ? $itemUid : null;

        if ($prefillItemUid !== null) {
            $prefillItem = $this->catalogItemRepository()->require($prefillItemUid);
            $this->requireSameOrganization($prefillItem->organizationId);
        }

        $entry = $prefillItemUid !== null && $quantityText !== ''
            ? $this->findPriceEntry($priceListUid, $itemUid, (float) $quantityText)
            : null;
        $this->setPageTitle($entry === null
            ? $this->_('Kontor · New price tier')
            : $this->_('Kontor · Edit price tier'));
        $form = $this->buildCatalogPriceEntryForm($priceList, $entry, $prefillItemUid);
        $previous = $entry === null ? null : $this->catalogPriceEntryAuditSnapshot($entry);

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $form->processInput($this->wire()->input->post);
            $this->validateCatalogPriceEntryForm($form);

            if (!$form->getErrors()) {
                $saved = $this->saveCatalogPriceEntryFromForm($form, $priceList, $entry);
                $this->audit(
                    'catalog',
                    'catalog_price',
                    $saved->itemUid,
                    $entry === null ? 'created' : 'updated',
                    previous: $previous,
                    current: $this->catalogPriceEntryAuditSnapshot($saved),
                    metadata: ['priceListUid' => $priceListUid],
                );
                $this->message($this->_('Price tier saved.'));
                $this->wire()->session->redirect(
                    '../catalog-price-list/?id=' . rawurlencode($priceListUid)
                );
            }
        }

        return $this->renderTemplate('catalog-price-entry-form', [
            'form' => $form,
            'priceList' => $priceList,
            'title' => $entry === null ? $this->_('Add price tier') : $this->_('Edit price tier'),
        ]);
    }

    public function ___executeCatalogPriceEntryAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-pricelist-edit');
        $priceListUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('list'));
        $itemUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('item'));
        $quantity = (float) $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('quantity')
        );
        $priceList = $this->priceListRepository()->require($priceListUid);
        $this->requireSameOrganization($priceList->organizationId);

        if (!$this->priceRepository()->delete($priceListUid, $itemUid, $quantity)) {
            throw new Wire404Exception($this->_('Price tier was not found.'));
        }

        $this->audit(
            'catalog',
            'catalog_price',
            $itemUid,
            'deleted',
            metadata: ['priceListUid' => $priceListUid, 'minQuantity' => $quantity],
        );
        $this->message($this->_('Price tier deleted.'));
        $this->wire()->session->redirect(
            '../catalog-price-list/?id=' . rawurlencode($priceListUid)
        );
    }

    public function ___executeActivity(): string
    {
        $this->requirePermission('kontor-audit-view');
        $this->setPageTitle($this->_('Kontor · Activity'));
        $organizationId = $this->organizationInternalId();
        $filters = $this->activityFilterSelection($organizationId);
        $query = $filters['query'];
        $component = $filters['component'];
        $entityType = $filters['entityType'];
        $action = $filters['action'];
        $pageSize = 50;
        $totalEvents = $this->auditEventRepository()->countMatching(
            $organizationId,
            $query,
            $component,
            $entityType,
            $action,
        );
        $totalPages = max(1, (int) ceil($totalEvents / $pageSize));
        $page = min(
            $totalPages,
            max(1, (int) $this->wire()->input->get('page'))
        );

        return $this->renderTemplate('activity', [
            'events' => $this->auditEventRepository()->findRecent(
                $organizationId,
                $query,
                $pageSize,
                $component,
                $entityType,
                $action,
                ($page - 1) * $pageSize,
            ),
            'query' => $query,
            'filterOptions' => $filters['options'],
            'selectedComponent' => $component,
            'selectedEntityType' => $entityType,
            'selectedAction' => $action,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalEvents' => $totalEvents,
            'changePresenter' => new AuditChangePresenter(),
        ]);
    }

    public function ___executeActivityExport(): void
    {
        $this->requirePermission('kontor-audit-view');
        $organizationId = $this->organizationInternalId();
        $filters = $this->activityFilterSelection($organizationId);
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_activity_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create an Activity export file.'));
        }

        $path = $temporary . '.csv';
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        $count = (new AuditCsvExporter())->export(
            $path,
            $this->auditEventRepository()->iterateMatching(
                $organizationId,
                $filters['query'],
                $filters['component'],
                $filters['entityType'],
                $filters['action'],
            ),
        );
        $activeFilters = array_filter([
            'query' => $filters['query'],
            'component' => $filters['component'],
            'entityType' => $filters['entityType'],
            'action' => $filters['action'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $this->audit(
            'core',
            'audit',
            'bulk',
            'exported',
            metadata: ['format' => 'csv', 'recordCount' => $count, 'filters' => $activeFilters],
        );
        $filename = 'kontor-activity-' . (new \DateTimeImmutable())->format('Y-m-d') . '.csv';
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => $filename,
            'exit' => true,
        ]);
    }

    public function ___executeBackups(): string
    {
        $this->requirePermission('kontor-backups-view');
        $this->setPageTitle($this->_('Kontor · Backups'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $component = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('component'),
            ['core', 'contacts', 'catalog', 'unknown']
        ) ?? '';
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['verified', 'failed']
        ) ?? '';
        $overview = (new BackupOverviewBuilder())->build(
            $this->backupSummaries(),
            $query,
            $component,
            $status,
            max(1, (int) $this->wire()->input->get('page')),
        );

        return $this->renderTemplate('backups', [
            'backups' => $overview['backups'],
            'query' => $query,
            'selectedComponent' => $component,
            'selectedStatus' => $status,
            'page' => $overview['page'],
            'totalPages' => $overview['totalPages'],
            'totalBackups' => $overview['total'],
            'canDownloadBackups' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-backups-download'),
        ]);
    }

    public function ___executeHealth(): string
    {
        $this->requirePermission('kontor-health-view');
        $this->setPageTitle($this->_('Kontor · Health'));
        $checks = [$this->coreHealthCheck()];
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['ok', 'warning', 'critical']
        ) ?? '';

        foreach (['KontorCatalog', 'KontorContacts', 'KontorDashboard', 'KontorQueue', 'KontorSearch'] as $moduleName) {
            if (!$this->wire()->modules->isInstalled($moduleName)) {
                continue;
            }

            $module = $this->wire()->modules->get($moduleName);

            if (is_object($module) && method_exists($module, 'healthCheck')) {
                $checks[] = $module->healthCheck();
            }
        }

        $overview = (new HealthOverviewBuilder())->build(
            $this->healthCheckRunner()->run($checks),
            $query,
            $status,
        );

        return $this->renderTemplate('health', [
            'checks' => $overview['checks'],
            'counts' => $overview['counts'],
            'overall' => $overview['overall'],
            'query' => $query,
            'selectedStatus' => $status,
            'checkedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function ___executeOrganization(): string
    {
        $this->requirePermission('kontor-admin');
        $this->setPageTitle($this->_('Kontor · Organization'));
        $organization = $this->organization();
        $form = $this->buildOrganizationForm($organization);

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $form->processInput($this->wire()->input->post);
            $countryCode = strtoupper($this->requiredFormValue($form, 'country_code'));
            $language = $this->requiredFormValue($form, 'default_language');
            $currency = strtoupper($this->requiredFormValue($form, 'default_currency'));
            $timezone = $this->requiredFormValue($form, 'timezone');

            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                $form->getChildByName('country_code')?->error($this->_('Use a two-letter ISO country code.'));
            }

            if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language) !== 1) {
                $form->getChildByName('default_language')?->error($this->_('Choose a supported language.'));
            }

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $form->getChildByName('default_currency')?->error($this->_('Use a three-letter ISO currency code.'));
            }

            if (!in_array($timezone, $this->allowedTimezones(), true)) {
                $form->getChildByName('timezone')?->error($this->_('Choose a valid IANA timezone.'));
            }

            if (!$form->getErrors()) {
                $previous = $this->organizationAuditSnapshot($organization);
                $organization->name = $this->requiredFormValue($form, 'name');
                $organization->legalName = $this->formValue($form, 'legal_name');
                $organization->countryCode = $countryCode;
                $organization->defaultLanguage = $language;
                $organization->defaultCurrency = $currency;
                $organization->timezone = $timezone;
                $organization->settings['dateFormat'] = $this->requiredFormValue($form, 'date_format');
                $current = $this->organizationAuditSnapshot($organization);

                if ($previous !== $current) {
                    $this->organizationRepository()->save($organization);
                    $this->audit(
                        'core',
                        'organization',
                        $organization->uid->toString(),
                        'updated',
                        previous: $previous,
                        current: $current,
                    );
                    $this->message($this->_('Organization settings saved.'));
                } else {
                    $this->message($this->_('Organization settings are already up to date.'));
                }

                $this->wire()->session->redirect('./');
            }
        }

        return $this->renderTemplate('organization', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    public function ___executeQueue(): string
    {
        $this->requirePermission('kontor-queue-view');
        $this->requireQueue();
        $this->setPageTitle($this->_('Kontor · Queue'));
        $queues = $this->jobRepository()->queues();
        $queue = $this->wire()->sanitizer->text((string) $this->wire()->input->get('queue'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'pending', 'reserved', 'completed', 'dead', 'cancelled']
        );
        $queue = $queue !== '' && in_array($queue, $queues, true) ? $queue : null;
        $pageSize = 25;
        $totalJobs = $this->jobRepository()->countMatching($queue, $status);
        $totalPages = max(1, (int) ceil($totalJobs / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));

        return $this->renderTemplate('queue', [
            'jobs' => $this->jobRepository()->findRecent(
                $queue,
                $status,
                $pageSize,
                ($page - 1) * $pageSize,
            ),
            'counts' => $this->jobRepository()->summaryCounts(),
            'queues' => $queues,
            'selectedQueue' => $queue,
            'selectedStatus' => $status,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalJobs' => $totalJobs,
            'canCancelJobs' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-queue-cancel'),
            'canRetryJobs' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-queue-retry'),
        ]);
    }

    public function ___executeQueueAction(): void
    {
        $this->requirePost();
        $this->requireQueue();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['cancel', 'retry']
        );
        $this->requireAction($action, ['cancel', 'retry']);
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('uid'));
        $job = $this->jobRepository()->find($uid);

        if ($job === null) {
            throw new WireException($this->_('Queue job was not found.'));
        }

        if ($action === 'cancel') {
            $this->requirePermission('kontor-queue-cancel');
            $changed = $this->jobRepository()->cancel($uid);
            $auditAction = 'cancelled';
            $message = $this->_('Pending job cancelled.');
        } else {
            $this->requirePermission('kontor-queue-retry');
            $changed = $this->jobRepository()->retryDead($uid);
            $auditAction = 'retried';
            $message = $this->_('Dead-letter job returned to the queue.');
        }

        if (!$changed) {
            throw new WireException($this->_('The job state changed before this action could be applied.'));
        }

        $this->audit(
            'queue',
            'job',
            $uid,
            $auditAction,
            previous: ['status' => (string) $job['status'], 'attempts' => (int) $job['attempts']],
            current: ['status' => $action === 'cancel' ? 'cancelled' : 'pending', 'attempts' => $action === 'cancel' ? (int) $job['attempts'] : 0],
            metadata: ['jobType' => (string) $job['job_type'], 'queue' => (string) $job['queue']],
        );
        $this->message($message);
        $this->wire()->session->redirect('../queue/?queue=' . rawurlencode((string) $job['queue']));
    }

    public function ___executeBackupCreate(): void
    {
        $this->requirePost();
        $this->requirePermission('kontor-backups-create');
        $component = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('component'),
            ['core', 'contacts', 'catalog']
        );
        $this->requireAction($component, ['core', 'contacts', 'catalog']);

        if ($component === 'catalog') {
            $this->requireCatalog();
        }
        $backup = $this->backupManager()->create(
            component: $component,
            kind: 'snapshot',
            organizationId: $this->organizationUid(),
            reason: 'Manual admin snapshot',
        );
        $this->audit(
            component: 'core',
            entityType: 'backup',
            entityUid: $backup->id,
            action: $backup->verified ? 'created' : 'verification_failed',
            metadata: [
                'backupComponent' => $component,
                'itemCount' => $backup->itemCount,
                'checksum' => $backup->checksum,
            ],
        );

        if ($backup->verified) {
            $this->message(sprintf(
                $this->_('Verified %s snapshot created with %d items.'),
                $component,
                $backup->itemCount
            ));
        } else {
            $this->error($this->_('Backup was created but verification failed.'));
        }

        $this->wire()->session->redirect('../backups/');
    }

    public function ___executeBackupDownload(): void
    {
        $this->requirePost();
        $this->requirePermission('kontor-backups-download');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $path = $this->backupManager()->findById($id);

        if ($path === null) {
            throw new WireException($this->_('Backup was not found.'));
        }

        try {
            $metadata = json_decode(
                (string) file_get_contents($path . DIRECTORY_SEPARATOR . 'metadata.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            throw new WireException($this->_('Backup metadata is missing or invalid.'));
        }

        $component = (string) ($metadata['component'] ?? '');
        $kind = (string) ($metadata['kind'] ?? 'snapshot');

        if (
            !in_array($component, ['core', 'contacts', 'catalog'], true)
            || !$this->backupManager()->verify($path, $component, $this->organizationUid(), $kind)
        ) {
            throw new WireException($this->_('Backup verification failed; download was blocked.'));
        }

        $backupUid = substr($id, -26);

        try {
            Uid::fromString($backupUid);
        } catch (\InvalidArgumentException) {
            throw new WireException($this->_('Backup identifier is invalid.'));
        }

        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_backup_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create a temporary archive.'));
        }

        $archivePath = $temporary . '.zip';
        @unlink($temporary);
        register_shutdown_function(static function () use ($archivePath): void {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        });
        $this->backupArchiveBuilder()->create($path, $archivePath);
        $this->audit(
            'core',
            'backup',
            $backupUid,
            'downloaded',
            metadata: [
                'backupId' => $id,
                'backupComponent' => $component,
                'sizeBytes' => filesize($archivePath) ?: 0,
            ],
        );
        wireSendFile($archivePath, [
            'forceDownload' => true,
            'downloadFilename' => $id . '.zip',
            'exit' => true,
        ]);
    }

    public function ___executeAddress(): void
    {
        $this->requirePost();
        $ownerType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('owner_type'),
            ['contact', 'company']
        );
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['add', 'delete']
        );
        $this->requireAction($ownerType, ['contact', 'company']);
        $this->requireAction($action, ['add', 'delete']);
        $ownerUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('owner_uid'));
        $owner = $ownerType === 'contact'
            ? $this->contactRepository()->require($ownerUid)
            : $this->companyRepository()->require($ownerUid);
        $this->requireSameOrganization($owner->organizationId);
        $this->requirePermission($ownerType === 'contact'
            ? 'kontor-contacts-contact-edit'
            : 'kontor-contacts-company-edit');

        if ($action === 'delete') {
            $addressUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('address_uid'));
            $address = $this->addressRepository()->find($addressUid);

            if (
                $address === null
                || $address->ownerType !== $ownerType
                || !hash_equals($address->ownerUid, $ownerUid)
                || !hash_equals($address->organizationId, $this->organizationUid())
            ) {
                throw new WirePermissionException($this->_('Address does not belong to this record.'));
            }

            $this->addressRepository()->delete($addressUid);
            $this->audit(
                'contacts',
                'address',
                $addressUid,
                'deleted',
                previous: $this->addressAuditSnapshot($address),
                metadata: ['ownerType' => $ownerType, 'ownerUid' => $ownerUid],
            );
            $this->message($this->_('Address removed.'));
        } else {
            $line1 = $this->wire()->sanitizer->text(trim((string) $this->wire()->input->post('line1')));
            $city = $this->wire()->sanitizer->text(trim((string) $this->wire()->input->post('city')));
            $countryCode = strtoupper(trim((string) $this->wire()->input->post('country_code')));

            if ($line1 === '' || $city === '' || preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                $this->error($this->_('Street, city and a two-letter country code are required.'));
                $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
            }

            $addresses = $this->addressRepository()->forOwner($ownerType, $ownerUid);
            $addressType = $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('address_type'),
                ['billing', 'shipping', 'home', 'work', 'other']
            ) ?? 'billing';
            $address = Address::create(
                organizationId: $this->organizationUid(),
                ownerType: $ownerType,
                ownerUid: $ownerUid,
                line1: $line1,
                city: $city,
                countryCode: $countryCode,
                addressType: $addressType,
                line2: $this->nullablePostText('line2'),
                region: $this->nullablePostText('region'),
                postalCode: $this->nullablePostText('postal_code'),
                isPrimary: $addresses === [] || (bool) $this->wire()->input->post('is_primary'),
            );
            $this->addressRepository()->save($address);
            $this->audit(
                'contacts',
                'address',
                $address->uid->toString(),
                'created',
                current: $this->addressAuditSnapshot($address),
                metadata: ['ownerType' => $ownerType, 'ownerUid' => $ownerUid],
            );
            $this->message($this->_('Address added.'));
        }

        $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
    }

    public function ___executeTags(): void
    {
        $this->requirePost();
        $ownerType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('owner_type'),
            ['contact', 'company']
        );
        $this->requireAction($ownerType, ['contact', 'company']);
        $ownerUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('owner_uid'));
        $owner = $ownerType === 'contact'
            ? $this->contactRepository()->require($ownerUid)
            : $this->companyRepository()->require($ownerUid);
        $this->requireSameOrganization($owner->organizationId);
        $this->requirePermission($ownerType === 'contact'
            ? 'kontor-contacts-contact-edit'
            : 'kontor-contacts-company-edit');
        $previousTags = $this->tagService()->tagsFor($this->organizationUid(), $ownerType, $ownerUid);
        $rawTags = explode(',', (string) $this->wire()->input->post('tags'));
        $tags = [];

        foreach (array_slice($rawTags, 0, 20) as $rawTag) {
            $tag = mb_substr($this->wire()->sanitizer->text(trim($rawTag)), 0, 50);

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        $this->tagService()->setTags($this->organizationUid(), $ownerType, $ownerUid, $tags);
        $currentTags = $this->tagService()->tagsFor($this->organizationUid(), $ownerType, $ownerUid);
        $this->audit(
            'contacts',
            $ownerType,
            $ownerUid,
            'tags_updated',
            previous: ['tags' => $previousTags],
            current: ['tags' => $currentTags],
        );
        $this->message($this->_('Tags updated.'));
        $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
    }

    public function ___executeExport(): void
    {
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('entity'),
            ['contact', 'company', 'catalog_item']
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('format'),
            ['csv', 'json', 'jsonl', 'xlsx']
        );
        $this->requireAction($entityType, ['contact', 'company', 'catalog_item']);
        $this->requireAction($format, ['csv', 'json', 'jsonl', 'xlsx']);
        $this->requireDataExchangeEntity($entityType);
        $this->requirePermission($entityType === 'catalog_item'
            ? 'kontor-catalog-export'
            : 'kontor-contacts-export');

        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_export_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create an export file.'));
        }

        $path = $temporary . '.' . $format;
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        $total = $this->exportManager()->run(
            entityType: $entityType,
            filters: [],
            fields: [],
            context: new ExportContext(
                organizationId: $this->organizationUid(),
                actorType: 'user',
                actorId: (string) $this->wire()->user->id,
            ),
            writer: (new FormatResolver())->writer($format),
            path: $path,
        );
        $date = (new \DateTimeImmutable())->format('Y-m-d');
        $filename = 'kontor-' . match ($entityType) {
            'contact' => 'contacts',
            'company' => 'companies',
            default => 'catalog-items',
        } . "-{$date}.{$format}";
        $this->audit(
            $entityType === 'catalog_item' ? 'catalog' : 'core',
            $entityType,
            'bulk',
            'exported',
            metadata: ['format' => $format, 'recordCount' => $total],
        );
        $this->wire()->log->save('kontor', "Exported {$total} {$entityType} records as {$format}.");
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => $filename,
            'exit' => true,
        ]);
    }

    public function ___executeImport(): string
    {
        $this->requirePermission('kontor-import');
        $this->setPageTitle($this->_('Kontor · Import preview'));
        $entityType = $this->wire()->sanitizer->option(
            (string) ($this->wire()->input->post('entity') ?: $this->wire()->input->get('entity')),
            ['contact', 'company', 'catalog_item']
        ) ?? 'contact';
        $this->requireDataExchangeEntity($entityType);
        $result = null;
        $filename = null;
        $previewToken = null;
        $backupId = null;
        $this->cleanupImportPreviews();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->requirePost();

            try {
                if ($this->wire()->input->post('commit_import')) {
                    [$result, $filename, $backupId] = $this->commitImportPreview(
                        $this->wire()->sanitizer->text((string) $this->wire()->input->post('preview_token'))
                    );
                    $entityType = $result->entityType;
                    $this->message(sprintf(
                        $this->_('Import completed: %d created, %d updated. Verified backup %s was created first.'),
                        $result->created,
                        $result->updated,
                        $backupId
                    ));
                } else {
                    [$path, $format, $filename] = $this->receiveImportFile();
                    $batchId = Uid::generate()->toString();

                    try {
                        $result = $this->importManager()->run(
                            entityType: $entityType,
                            reader: (new FormatResolver())->reader($format),
                            path: $path,
                            context: new ImportContext(
                                organizationId: $this->organizationUid(),
                                batchId: $batchId,
                                dryRun: true,
                                actorType: 'user',
                                actorId: (string) $this->wire()->user->id,
                            ),
                        );

                        if ($result->totalRows > 0 && $result->failed === 0) {
                            $previewToken = $this->storeImportPreview(
                                $batchId,
                                $path,
                                $format,
                                $filename,
                                $entityType,
                                $result->updated
                            );
                            $path = '';
                        }
                    } finally {
                        if ($path !== '' && is_file($path)) {
                            unlink($path);
                        }
                    }
                }
            } catch (\Throwable $exception) {
                $this->error($this->_('Import failed: ') . $exception->getMessage());
            }
        }

        return $this->renderTemplate('import', [
            'entityType' => $entityType,
            'result' => $result,
            'filename' => $filename,
            'previewToken' => $previewToken,
            'backupId' => $backupId,
            'availableEntityTypes' => $this->availableImportEntityTypes(),
            'backupLabel' => $entityType === 'catalog_item' ? 'Catalog' : 'Contacts',
        ]);
    }

    public function ___executeContactStatus(): void
    {
        $this->requirePost();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $contact = $this->contactRepository()->require($id);
        $this->requireSameOrganization($contact->organizationId);
        $this->requirePermission('kontor-contacts-contact-archive');

        $action === 'restore'
            ? $this->contactRepository()->restore($id)
            : $this->contactRepository()->archive($id);
        $this->audit('contacts', 'contact', $id, $action === 'restore' ? 'restored' : 'archived');
        $this->message($action === 'restore' ? $this->_('Contact restored.') : $this->_('Contact archived.'));
        $this->wire()->session->redirect('../contacts/' . ($action === 'restore' ? '?archived=1' : ''));
    }

    public function ___executeCompanyStatus(): void
    {
        $this->requirePost();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $company = $this->companyRepository()->require($id);
        $this->requireSameOrganization($company->organizationId);
        $this->requirePermission('kontor-contacts-company-archive');

        $action === 'restore'
            ? $this->companyRepository()->restore($id)
            : $this->companyRepository()->archive($id);
        $this->audit('contacts', 'company', $id, $action === 'restore' ? 'restored' : 'archived');
        $this->message($action === 'restore' ? $this->_('Company restored.') : $this->_('Company archived.'));
        $this->wire()->session->redirect('../companies/' . ($action === 'restore' ? '?archived=1' : ''));
    }

    public function ___executeRelationship(): void
    {
        $this->requirePost();
        $contactId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('contact_id'));
        $companyId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('company_id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['add', 'end']
        );
        $this->requireAction($action, ['add', 'end']);
        $contact = $this->contactRepository()->require($contactId);
        $company = $this->companyRepository()->require($companyId);
        $this->requireSameOrganization($contact->organizationId);
        $this->requireSameOrganization($company->organizationId);
        $this->requirePermission('kontor-contacts-contact-edit');

        if ($action === 'end') {
            $this->membershipRepository()->end($contactId, $companyId, new \DateTimeImmutable('today'));
            $this->audit(
                'contacts',
                'membership',
                $contactId,
                'ended',
                metadata: ['companyUid' => $companyId],
            );
            $this->message($this->_('Company relationship ended.'));
        } else {
            $role = $this->wire()->sanitizer->text((string) $this->wire()->input->post('role'));
            $department = $this->wire()->sanitizer->text((string) $this->wire()->input->post('department'));
            $this->membershipRepository()->save(new ContactCompanyMembership(
                organizationId: $this->organizationUid(),
                contactUid: $contactId,
                companyUid: $companyId,
                role: $role !== '' ? $role : $this->_('Member'),
                department: $department !== '' ? $department : null,
                isPrimary: false,
                startedAt: new \DateTimeImmutable('today'),
                endedAt: null,
            ));
            $this->audit(
                'contacts',
                'membership',
                $contactId,
                'created',
                current: ['role' => $role !== '' ? $role : $this->_('Member'), 'department' => $department ?: null],
                metadata: ['companyUid' => $companyId],
            );
            $this->message($this->_('Company relationship added.'));
        }

        $this->wire()->session->redirect('../contact/?id=' . rawurlencode($contactId));
    }

    public function ___executeComponents(): string
    {
        $this->requirePermission('kontor-components-view');
        $this->setPageTitle($this->_('Kontor · Components'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['enabled', 'disabled', 'installed', 'uninstalled', 'attention']
        ) ?? '';
        $rows = $this->componentRegistry()->all();
        $builder = new ComponentOverviewBuilder();
        $runtimeInfo = [];

        foreach ($rows as $row) {
            $moduleName = $builder->moduleName((string) ($row['name'] ?? ''));
            $info = $this->wire()->modules->getModuleInfoVerbose($moduleName);
            $runtimeInfo[$moduleName] = is_array($info) ? $info : [];
        }
        $overview = $builder->build($rows, $runtimeInfo, $query, $status);

        return $this->renderTemplate('components', [
            'components' => $overview['components'],
            'counts' => $overview['counts'],
            'query' => $query,
            'selectedStatus' => $status,
            'canSyncComponents' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-components-update'),
        ]);
    }

    public function ___executeComponentSync(): void
    {
        $this->requirePost();
        $this->requirePermission('kontor-components-update');
        $name = $this->wire()->sanitizer->text((string) $this->wire()->input->post('component'));
        $registered = $this->componentRegistry()->find($name);

        if ($registered === null) {
            throw new WireException($this->_('Component is not registered.'));
        }

        $moduleName = (new ComponentOverviewBuilder())->moduleName($name);
        $runtime = $this->wire()->modules->getModuleInfoVerbose($moduleName);

        if (
            !is_array($runtime)
            || !($runtime['installed'] ?? false)
            || (string) ($runtime['version'] ?? '') === ''
        ) {
            throw new WireException($this->_('The matching ProcessWire module is not installed.'));
        }

        $previous = [
            'version' => (string) ($registered['version'] ?? ''),
            'status' => (string) ($registered['status'] ?? ''),
        ];
        $version = (string) $runtime['version'];
        $this->componentRegistry()->markInstalled(
            $name,
            $version,
            (string) ($registered['source'] ?? $name),
            isset($registered['checksum']) ? (string) $registered['checksum'] : null,
        );
        $this->componentRegistry()->enable($name);
        $this->audit(
            'core',
            'component',
            $name,
            'synchronized',
            previous: $previous,
            current: ['version' => $version, 'status' => 'enabled'],
            metadata: ['moduleName' => $moduleName],
        );
        $this->message(sprintf(
            $this->_('%s synchronized with ProcessWire runtime version %s.'),
            (string) ($runtime['title'] ?? $moduleName),
            (string) ($runtime['versionStr'] ?? $version),
        ));
        $this->wire()->session->redirect('../components/');
    }

    private function buildContactForm(?Contact $contact): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($contact ? '?id=' . rawurlencode($contact->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');

        $this->addTextField($form, 'display_name', $this->_('Display name'), $contact?->displayName, true, 100);
        $this->addTextField($form, 'first_name', $this->_('First name'), $contact?->firstName, false, 50);
        $this->addTextField($form, 'last_name', $this->_('Last name'), $contact?->lastName, false, 50);
        $this->addEmailField($form, 'email', $this->_('Email'), $contact?->email, 50);
        $this->addTextField($form, 'phone', $this->_('Phone'), $contact?->phone, false, 50);
        $this->addTextField($form, 'mobile', $this->_('Mobile'), $contact?->mobile, false, 50);
        $this->addTextField($form, 'job_title', $this->_('Job title'), $contact?->jobTitle, false, 50);
        $this->addStatusField($form, $contact?->status ?? 'active');
        $this->addTextareaField($form, 'notes', $this->_('Notes'), $contact?->notes);
        $this->addSubmit($form, $this->_('Save contact'));

        return $form;
    }

    private function buildCompanyForm(?Company $company): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($company ? '?id=' . rawurlencode($company->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');

        $this->addTextField($form, 'legal_name', $this->_('Legal name'), $company?->legalName, true, 100);
        $this->addTextField($form, 'trading_name', $this->_('Trading name'), $company?->tradingName, false, 50);
        $this->addTextField($form, 'registration_number', $this->_('Registration number'), $company?->registrationNumber, false, 50);
        $this->addEmailField($form, 'email', $this->_('Email'), $company?->email, 50);
        $this->addTextField($form, 'phone', $this->_('Phone'), $company?->phone, false, 50);
        $this->addTextField($form, 'website', $this->_('Website'), $company?->website, false, 50);
        $this->addTextField($form, 'vat_number', $this->_('VAT number'), $company?->vatNumber, false, 50);
        $this->addStatusField($form, $company?->status ?? 'active');
        $this->addTextareaField($form, 'notes', $this->_('Notes'), $company?->notes);
        $this->addSubmit($form, $this->_('Save company'));

        return $form;
    }

    private function buildCatalogItemForm(?CatalogItem $item): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($item ? '?id=' . rawurlencode($item->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');
        $language = $this->organization()->defaultLanguage;
        $languages = $this->catalogFormLanguages();
        $currency = $this->organization()->defaultCurrency;

        $this->addSelectField(
            $form,
            'item_type',
            $this->_('Item type'),
            ['product' => $this->_('Product'), 'service' => $this->_('Service')],
            $item?->itemType ?? 'product',
            25,
        );
        $this->addSelectField(
            $form,
            'status',
            $this->_('Status'),
            [
                'active' => $this->_('Active'),
                'inactive' => $this->_('Inactive'),
                'discontinued' => $this->_('Discontinued'),
            ],
            $item?->status ?? 'active',
            25,
        );
        foreach ($languages as $locale => $label) {
            $this->addTextField(
                $form,
                'title_' . $locale,
                sprintf(
                    $locale === $language
                        ? $this->_('Title (%s) · Default')
                        : $this->_('Title (%s)'),
                    strtoupper($locale)
                ),
                $item?->title[$locale] ?? null,
                $locale === $language,
                50,
            );
        }
        $this->addTextField($form, 'sku', $this->_('SKU'), $item?->sku, false, 50);
        $this->addTextField($form, 'barcode', $this->_('Barcode'), $item?->barcode, false, 50);
        $this->addSelectField(
            $form,
            'unit_code',
            $this->_('Unit'),
            (new UnitOfMeasure())->all(),
            $item?->unitCode ?? 'pcs',
            25,
        );

        /** @var InputfieldSelect $tax */
        $tax = $this->wire()->modules->get('InputfieldSelect');
        $tax->name = 'tax_code';
        $tax->label = $this->_('Tax code');
        $tax->addOption('', $this->_('Not specified'));
        $tax->addOptions((new TaxCode())->all());
        $tax->value = $item?->taxCode ?? '';
        $tax->columnWidth = 25;
        $form->add($tax);

        /** @var InputfieldSelect $categoryField */
        $categoryField = $this->wire()->modules->get('InputfieldSelect');
        $categoryField->name = 'category_uid';
        $categoryField->label = $this->_('Category');
        $categoryField->addOption('', $this->_('Uncategorized'));
        $categoryOptions = [];

        foreach ($this->categoryRepository()->findAll($this->organizationUid(), limit: 250) as $category) {
            $categoryOptions[$category->uid->toString()] = $this->categoryName($category);
        }

        if ($item?->categoryUid !== null && !isset($categoryOptions[$item->categoryUid])) {
            $assigned = $this->categoryRepository()->find($item->categoryUid);

            if ($assigned !== null && hash_equals($assigned->organizationId, $this->organizationUid())) {
                $categoryOptions[$item->categoryUid] = $this->categoryName($assigned) . ' · archived';
            }
        }

        foreach ($categoryOptions as $uid => $label) {
            $categoryField->addOption($uid, $label);
        }

        $categoryField->value = $item?->categoryUid ?? '';
        $categoryField->columnWidth = 50;
        $form->add($categoryField);

        $currencies = array_fill_keys(
            array_values(array_unique([$currency, 'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD'])),
            '',
        );
        $currencies = array_combine(array_keys($currencies), array_keys($currencies)) ?: [];

        foreach ([
            'sales' => [$this->_('Sales price'), $item?->salesPrice],
            'purchase' => [$this->_('Purchase price'), $item?->purchasePrice],
            'cost' => [$this->_('Cost price'), $item?->costPrice],
        ] as $prefix => [$label, $money]) {
            $this->addTextField(
                $form,
                $prefix . '_price',
                $label,
                $this->moneyFormValue($money),
                false,
                25,
            );
            $this->addSelectField(
                $form,
                $prefix . '_currency',
                $this->_('Currency'),
                $currencies,
                $money?->currencyCode() ?? $currency,
                25,
            );
        }

        /** @var InputfieldCheckbox $inventory */
        $inventory = $this->wire()->modules->get('InputfieldCheckbox');
        $inventory->name = 'track_inventory';
        $inventory->label = $this->_('Track inventory for this item');
        $inventory->checked = $item?->trackInventory ?? false;
        $form->add($inventory);
        foreach ($languages as $locale => $label) {
            $this->addTextareaField(
                $form,
                'description_' . $locale,
                sprintf(
                    $locale === $language
                        ? $this->_('Description (%s) · Default')
                        : $this->_('Description (%s)'),
                    strtoupper($locale)
                ),
                $item?->description[$locale] ?? null,
                50,
            );
        }
        $this->addSubmit($form, $this->_('Save catalog item'));

        return $form;
    }

    private function buildCatalogCategoryForm(?Category $category): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($category ? '?id=' . rawurlencode($category->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');
        $language = $this->organization()->defaultLanguage;

        foreach ($this->catalogFormLanguages() as $locale => $label) {
            $this->addTextField(
                $form,
                'name_' . $locale,
                sprintf(
                    $locale === $language
                        ? $this->_('Name (%s) · Default')
                        : $this->_('Name (%s)'),
                    strtoupper($locale)
                ),
                $category?->name[$locale] ?? null,
                $locale === $language,
                50,
            );
        }

        /** @var InputfieldSelect $parent */
        $parent = $this->wire()->modules->get('InputfieldSelect');
        $parent->name = 'parent_uid';
        $parent->label = $this->_('Parent category');
        $parent->addOption('', $this->_('Top level'));

        foreach ($this->categoryRepository()->findAll($this->organizationUid(), limit: 250) as $candidate) {
            if ($candidate->uid->toString() !== $category?->uid->toString()) {
                $parent->addOption($candidate->uid->toString(), $this->categoryName($candidate));
            }
        }

        $parent->value = $category?->parentUid ?? '';
        $parent->columnWidth = 50;
        $form->add($parent);
        $this->addTextField(
            $form,
            'sort_order',
            $this->_('Sort order'),
            (string) ($category?->sortOrder ?? 0),
            true,
            50,
        );
        $this->addSelectField(
            $form,
            'status',
            $this->_('Status'),
            ['active' => $this->_('Active'), 'inactive' => $this->_('Inactive')],
            $category?->status ?? 'active',
            50,
        );
        $this->addSubmit($form, $this->_('Save category'));

        return $form;
    }

    private function buildCatalogPriceListForm(?PriceList $priceList): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($priceList ? '?id=' . rawurlencode($priceList->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');
        $currency = $priceList?->currencyCode ?? $this->organization()->defaultCurrency;
        $currencies = array_values(array_unique([$currency, 'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD']));
        $currencyOptions = array_combine($currencies, $currencies) ?: [];

        $this->addTextField($form, 'name', $this->_('Name'), $priceList?->name, true, 50);
        $this->addSelectField(
            $form,
            'currency_code',
            $this->_('Currency'),
            $currencyOptions,
            $currency,
            25,
        );
        $this->addSelectField(
            $form,
            'status',
            $this->_('Status'),
            ['active' => $this->_('Active'), 'inactive' => $this->_('Inactive')],
            $priceList?->status ?? 'active',
            25,
        );
        $this->addTextField(
            $form,
            'valid_from',
            $this->_('Valid from (YYYY-MM-DD)'),
            $priceList?->validFrom?->format('Y-m-d'),
            false,
            50,
        );
        $this->addTextField(
            $form,
            'valid_to',
            $this->_('Valid to (YYYY-MM-DD)'),
            $priceList?->validTo?->format('Y-m-d'),
            false,
            50,
        );
        $this->addSubmit($form, $this->_('Save price list'));

        return $form;
    }

    private function buildCatalogPriceEntryForm(
        PriceList $priceList,
        ?PriceListEntry $entry,
        ?string $prefillItemUid = null,
    ): InputfieldForm {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $query = ['list' => $priceList->uid->toString()];

        if ($entry !== null) {
            $query['item'] = $entry->itemUid;
            $query['quantity'] = $this->quantityFormValue($entry->minQuantity);
        } elseif ($prefillItemUid !== null) {
            $query['item'] = $prefillItemUid;
        }

        $form->action = './?' . http_build_query($query);
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');
        /** @var InputfieldSelect $item */
        $item = $this->wire()->modules->get('InputfieldSelect');
        $item->name = 'item_uid';
        $item->label = $this->_('Catalog item');
        $item->required = true;
        $itemNames = $this->catalogItemNames();

        $selectedItemUid = $entry?->itemUid ?? $prefillItemUid;

        if ($selectedItemUid !== null && !isset($itemNames[$selectedItemUid])) {
            $assignedItem = $this->catalogItemRepository()->find($selectedItemUid);

            if (
                $assignedItem !== null
                && hash_equals($assignedItem->organizationId, $this->organizationUid())
            ) {
                $itemNames[$selectedItemUid] = $this->catalogItemTitle($assignedItem) . ' · archived';
            }
        }

        foreach ($itemNames as $uid => $name) {
            $item->addOption($uid, $name);
        }

        $item->value = $selectedItemUid ?? '';
        $item->columnWidth = 50;
        $form->add($item);
        $this->addTextField(
            $form,
            'min_quantity',
            $this->_('Minimum quantity'),
            $entry !== null ? $this->quantityFormValue($entry->minQuantity) : '1',
            true,
            25,
        );
        $this->addTextField(
            $form,
            'entry_price',
            $this->_('Price'),
            $this->moneyFormValue($entry?->price),
            true,
            25,
        );
        $this->addSelectField(
            $form,
            'entry_currency',
            $this->_('Currency'),
            [$priceList->currencyCode => $priceList->currencyCode],
            $priceList->currencyCode,
            25,
        );
        $this->addTextField(
            $form,
            'valid_from',
            $this->_('Valid from (YYYY-MM-DD)'),
            $entry?->validFrom?->format('Y-m-d'),
            false,
            37,
        );
        $this->addTextField(
            $form,
            'valid_to',
            $this->_('Valid to (YYYY-MM-DD)'),
            $entry?->validTo?->format('Y-m-d'),
            false,
            38,
        );
        $this->addSubmit($form, $this->_('Save price tier'));

        return $form;
    }

    private function buildOrganizationForm(Organization $organization): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './';
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form kontor-organization-form');
        $this->addTextField($form, 'name', $this->_('Display name'), $organization->name, true, 50);
        $this->addTextField($form, 'legal_name', $this->_('Legal name'), $organization->legalName, false, 50);
        $this->addTextField($form, 'country_code', $this->_('Country code'), $organization->countryCode, true, 33);
        $languageOptions = [
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'uk' => 'Українська',
        ];

        if (!isset($languageOptions[$organization->defaultLanguage])) {
            $languageOptions[$organization->defaultLanguage] = $organization->defaultLanguage;
        }

        $this->addSelectField(
            $form,
            'default_language',
            $this->_('Default language'),
            $languageOptions,
            $organization->defaultLanguage,
            33
        );
        $this->addTextField(
            $form,
            'default_currency',
            $this->_('Default currency'),
            $organization->defaultCurrency,
            true,
            34
        );
        $timezoneIdentifiers = $this->allowedTimezones();
        $timezones = array_combine($timezoneIdentifiers, $timezoneIdentifiers) ?: [];
        $this->addSelectField(
            $form,
            'timezone',
            $this->_('Timezone'),
            $timezones,
            $organization->timezone,
            50
        );
        $dateFormat = (string) ($organization->settings['dateFormat'] ?? 'Y-m-d');
        $dateFormats = [
            'Y-m-d' => date('Y-m-d'),
            'd.m.Y' => date('d.m.Y'),
            'm/d/Y' => date('m/d/Y'),
            'd/m/Y' => date('d/m/Y'),
        ];

        if (!isset($dateFormats[$dateFormat])) {
            $dateFormats[$dateFormat] = date($dateFormat);
        }

        $this->addSelectField(
            $form,
            'date_format',
            $this->_('Date format'),
            $dateFormats,
            $dateFormat,
            50
        );
        $this->addSubmit($form, $this->_('Save organization'));

        return $form;
    }

    private function saveContactFromForm(InputfieldForm $form, ?Contact $contact): Contact
    {
        if ($contact === null) {
            $contact = Contact::create(
                organizationId: $this->organizationUid(),
                firstName: $this->formValue($form, 'first_name'),
                middleName: null,
                lastName: $this->formValue($form, 'last_name'),
                displayName: $this->requiredFormValue($form, 'display_name'),
            );
        }

        $contact->displayName = $this->requiredFormValue($form, 'display_name');
        $contact->firstName = $this->formValue($form, 'first_name');
        $contact->lastName = $this->formValue($form, 'last_name');
        $contact->email = $this->formValue($form, 'email');
        $contact->phone = $this->formValue($form, 'phone');
        $contact->mobile = $this->formValue($form, 'mobile');
        $contact->jobTitle = $this->formValue($form, 'job_title');
        $contact->status = $this->requiredFormValue($form, 'status');
        $contact->notes = $this->formValue($form, 'notes');
        $this->contactRepository()->save($contact);

        return $contact;
    }

    private function saveCompanyFromForm(InputfieldForm $form, ?Company $company): Company
    {
        if ($company === null) {
            $company = Company::create(
                organizationId: $this->organizationUid(),
                legalName: $this->requiredFormValue($form, 'legal_name'),
            );
        }

        $company->legalName = $this->requiredFormValue($form, 'legal_name');
        $company->tradingName = $this->formValue($form, 'trading_name');
        $company->registrationNumber = $this->formValue($form, 'registration_number');
        $company->email = $this->formValue($form, 'email');
        $company->phone = $this->formValue($form, 'phone');
        $company->website = $this->formValue($form, 'website');
        $company->vatNumber = $this->formValue($form, 'vat_number');
        $company->status = $this->requiredFormValue($form, 'status');
        $company->notes = $this->formValue($form, 'notes');
        $this->companyRepository()->save($company);

        return $company;
    }

    private function validateCatalogItemForm(InputfieldForm $form, ?CatalogItem $item): void
    {
        foreach (['sales', 'purchase', 'cost'] as $prefix) {
            $value = $this->formValue($form, $prefix . '_price');

            if ($value !== null && preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value) !== 1) {
                $form->getChildByName($prefix . '_price')?->error(
                    $this->_('Use a number with no more than two decimal places.')
                );
            }
        }

        $sku = $this->formValue($form, 'sku');

        if ($sku !== null) {
            $existing = $this->catalogItemRepository()->findBySku($this->organizationUid(), $sku);

            if ($existing !== null && $existing->uid->toString() !== $item?->uid->toString()) {
                $form->getChildByName('sku')?->error(
                    $this->_('This SKU is already used by another catalog item.')
                );
            }
        }

        $categoryUid = $this->formValue($form, 'category_uid');

        if ($categoryUid !== null) {
            $category = $this->categoryRepository()->find($categoryUid);

            if ($category === null || !hash_equals($category->organizationId, $this->organizationUid())) {
                $form->getChildByName('category_uid')?->error(
                    $this->_('Choose a category from this organization.')
                );
            }
        }
    }

    private function saveCatalogItemFromForm(InputfieldForm $form, ?CatalogItem $item): CatalogItem
    {
        $titles = $item?->title ?? [];
        $descriptions = $item?->description ?? [];

        foreach ($this->catalogFormLanguages() as $locale => $label) {
            $title = $this->formValue($form, 'title_' . $locale);
            $description = $this->formValue($form, 'description_' . $locale);

            if ($title === null) {
                unset($titles[$locale]);
            } else {
                $titles[$locale] = $title;
            }

            if ($description === null) {
                unset($descriptions[$locale]);
            } else {
                $descriptions[$locale] = $description;
            }
        }

        if ($item === null) {
            $item = CatalogItem::create(
                organizationId: $this->organizationUid(),
                title: $titles,
                description: $descriptions,
            );
        }

        $item->title = $titles;
        $item->description = $descriptions;
        $item->itemType = $this->requiredFormValue($form, 'item_type');
        $item->status = $this->requiredFormValue($form, 'status');
        $item->sku = $this->formValue($form, 'sku');
        $item->barcode = $this->formValue($form, 'barcode');
        $item->unitCode = $this->requiredFormValue($form, 'unit_code');
        $item->taxCode = $this->formValue($form, 'tax_code');
        $item->categoryUid = $this->formValue($form, 'category_uid');
        $item->salesPrice = $this->moneyFromForm($form, 'sales');
        $item->purchasePrice = $this->moneyFromForm($form, 'purchase');
        $item->costPrice = $this->moneyFromForm($form, 'cost');
        $item->trackInventory = (bool) $form->getChildByName('track_inventory')?->value;
        $this->catalogItemRepository()->save($item);

        return $item;
    }

    private function validateCatalogCategoryForm(InputfieldForm $form, ?Category $category): void
    {
        $sortOrder = $this->requiredFormValue($form, 'sort_order');

        if (preg_match('/^-?\d+$/', $sortOrder) !== 1) {
            $form->getChildByName('sort_order')?->error($this->_('Sort order must be a whole number.'));
        }

        $parentUid = $this->formValue($form, 'parent_uid');

        if ($parentUid === null) {
            return;
        }

        $parent = $this->categoryRepository()->find($parentUid);

        if ($parent === null || !hash_equals($parent->organizationId, $this->organizationUid())) {
            $form->getChildByName('parent_uid')?->error($this->_('Choose a category from this organization.'));

            return;
        }

        $visited = [];

        while ($parent !== null) {
            $uid = $parent->uid->toString();

            if ($uid === $category?->uid->toString()) {
                $form->getChildByName('parent_uid')?->error(
                    $this->_('A category cannot be placed inside one of its descendants.')
                );

                return;
            }

            if (isset($visited[$uid])) {
                $form->getChildByName('parent_uid')?->error(
                    $this->_('The selected category hierarchy already contains a cycle.')
                );

                return;
            }

            $visited[$uid] = true;
            $parent = $parent->parentUid !== null
                ? $this->categoryRepository()->find($parent->parentUid)
                : null;
        }
    }

    private function saveCatalogCategoryFromForm(InputfieldForm $form, ?Category $category): Category
    {
        $names = $category?->name ?? [];

        foreach ($this->catalogFormLanguages() as $locale => $label) {
            $name = $this->formValue($form, 'name_' . $locale);

            if ($name === null) {
                unset($names[$locale]);
            } else {
                $names[$locale] = $name;
            }
        }

        if ($category === null) {
            $category = Category::create(
                $this->organizationUid(),
                $names,
            );
        }

        $category->name = $names;
        $category->parentUid = $this->formValue($form, 'parent_uid');
        $category->sortOrder = (int) $this->requiredFormValue($form, 'sort_order');
        $category->status = $this->requiredFormValue($form, 'status');
        $this->categoryRepository()->save($category);

        return $category;
    }

    private function validateCatalogPriceListForm(
        InputfieldForm $form,
        ?PriceList $priceList,
    ): void
    {
        $this->validateDateRangeForm($form);

        if (
            $priceList !== null
            && $priceList->currencyCode !== strtoupper($this->requiredFormValue($form, 'currency_code'))
            && $this->priceRepository()->forPriceList($priceList->uid->toString()) !== []
        ) {
            $form->getChildByName('currency_code')?->error(
                $this->_('Remove existing price tiers before changing the currency.')
            );
        }
    }

    private function saveCatalogPriceListFromForm(
        InputfieldForm $form,
        ?PriceList $priceList,
    ): PriceList {
        if ($priceList === null) {
            $priceList = PriceList::create(
                $this->organizationUid(),
                $this->requiredFormValue($form, 'name'),
                $this->requiredFormValue($form, 'currency_code'),
            );
        }

        $priceList->name = $this->requiredFormValue($form, 'name');
        $priceList->currencyCode = strtoupper($this->requiredFormValue($form, 'currency_code'));
        $priceList->status = $this->requiredFormValue($form, 'status');
        $priceList->validFrom = $this->dateFromForm($form, 'valid_from');
        $priceList->validTo = $this->dateFromForm($form, 'valid_to');
        $this->priceListRepository()->save($priceList);

        return $priceList;
    }

    private function validateCatalogPriceEntryForm(InputfieldForm $form): void
    {
        $itemUid = $this->requiredFormValue($form, 'item_uid');
        $item = $this->catalogItemRepository()->find($itemUid);

        if ($item === null || !hash_equals($item->organizationId, $this->organizationUid())) {
            $form->getChildByName('item_uid')?->error(
                $this->_('Choose a catalog item from this organization.')
            );
        }

        $quantity = $this->requiredFormValue($form, 'min_quantity');

        if (preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/', $quantity) !== 1 || (float) $quantity <= 0) {
            $form->getChildByName('min_quantity')?->error(
                $this->_('Minimum quantity must be greater than zero with at most six decimal places.')
            );
        }

        $price = $this->requiredFormValue($form, 'entry_price');

        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $price) !== 1) {
            $form->getChildByName('entry_price')?->error(
                $this->_('Use a number with no more than two decimal places.')
            );
        }

        $this->validateDateRangeForm($form);
    }

    private function saveCatalogPriceEntryFromForm(
        InputfieldForm $form,
        PriceList $priceList,
        ?PriceListEntry $entry,
    ): PriceListEntry {
        $saved = new PriceListEntry(
            priceListUid: $priceList->uid->toString(),
            itemUid: $this->requiredFormValue($form, 'item_uid'),
            price: $this->moneyFromForm($form, 'entry')
                ?? throw new WireException($this->_('Price is required.')),
            minQuantity: (float) $this->requiredFormValue($form, 'min_quantity'),
            validFrom: $this->dateFromForm($form, 'valid_from'),
            validTo: $this->dateFromForm($form, 'valid_to'),
        );

        if ($entry === null) {
            $this->priceRepository()->save($saved);
        } else {
            $this->priceRepository()->replace($entry->itemUid, $entry->minQuantity, $saved);
        }

        return $saved;
    }

    private function validateDateRangeForm(InputfieldForm $form): void
    {
        $from = $this->validatedDateFromForm($form, 'valid_from');
        $to = $this->validatedDateFromForm($form, 'valid_to');

        if ($from !== null && $to !== null && $from > $to) {
            $form->getChildByName('valid_to')?->error(
                $this->_('The end date must be on or after the start date.')
            );
        }
    }

    private function validatedDateFromForm(
        InputfieldForm $form,
        string $field,
    ): ?\DateTimeImmutable {
        $value = $this->formValue($form, $field);

        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            $form->getChildByName($field)?->error($this->_('Use a valid date in YYYY-MM-DD format.'));

            return null;
        }

        return $date;
    }

    private function dateFromForm(InputfieldForm $form, string $field): ?\DateTimeImmutable
    {
        $value = $this->formValue($form, $field);

        return $value === null ? null : new \DateTimeImmutable($value);
    }

    private function quantityFormValue(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 6, '.', ''), '0'), '.');
    }

    private function moneyFromForm(InputfieldForm $form, string $prefix): ?Money
    {
        $amount = $this->formValue($form, $prefix . '_price');

        if ($amount === null) {
            return null;
        }

        $negative = str_starts_with($amount, '-');
        $unsigned = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return Money::ofMinor(
            $negative ? -$minor : $minor,
            $this->requiredFormValue($form, $prefix . '_currency'),
        );
    }

    private function moneyFormValue(?Money $money): ?string
    {
        if ($money === null) {
            return null;
        }

        $minor = $money->amountMinor();
        $negative = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return sprintf('%s%d.%02d', $negative, intdiv($minor, 100), $minor % 100);
    }

    private function addTextField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value,
        bool $required,
        int $width
    ): void {
        /** @var InputfieldText $field */
        $field = $this->wire()->modules->get('InputfieldText');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->required = $required;
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addEmailField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value,
        int $width
    ): void {
        /** @var InputfieldEmail $field */
        $field = $this->wire()->modules->get('InputfieldEmail');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addStatusField(InputfieldForm $form, string $value): void
    {
        /** @var InputfieldSelect $field */
        $field = $this->wire()->modules->get('InputfieldSelect');
        $field->name = 'status';
        $field->label = $this->_('Status');
        $field->addOptions([
            'active' => $this->_('Active'),
            'inactive' => $this->_('Inactive'),
        ]);
        $field->value = $value;
        $field->columnWidth = 50;
        $form->add($field);
    }

    /**
     * @return string[]
     */
    private function allowedTimezones(): array
    {
        return ['UTC', ...\DateTimeZone::listIdentifiers()];
    }

    /**
     * @param array<string, string> $options
     */
    private function addSelectField(
        InputfieldForm $form,
        string $name,
        string $label,
        array $options,
        string $value,
        int $width
    ): void {
        /** @var InputfieldSelect $field */
        $field = $this->wire()->modules->get('InputfieldSelect');
        $field->name = $name;
        $field->label = $label;
        $field->addOptions($options);
        $field->value = $value;
        $field->required = true;
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addTextareaField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value,
        int $width = 100,
    ): void {
        /** @var InputfieldTextarea $field */
        $field = $this->wire()->modules->get('InputfieldTextarea');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->rows = 5;
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addSubmit(InputfieldForm $form, string $label): void
    {
        /** @var InputfieldSubmit $submit */
        $submit = $this->wire()->modules->get('InputfieldSubmit');
        $submit->name = 'submit_save';
        $submit->value = $label;
        $submit->icon = 'save';
        $form->add($submit);
    }

    private function addDuplicateConfirmation(InputfieldForm $form): void
    {
        /** @var InputfieldCheckbox $field */
        $field = $this->wire()->modules->get('InputfieldCheckbox');
        $field->name = 'confirm_duplicate';
        $field->label = $this->_('Create this contact anyway');
        $field->description = $this->_('I reviewed the possible duplicate contacts shown above.');
        $field->required = true;
        $form->insertBefore($field, $form->getChildByName('submit_save'));
    }

    private function formValue(InputfieldForm $form, string $name): ?string
    {
        $value = trim((string) $form->getChildByName($name)?->value);

        return $value === '' ? null : $value;
    }

    private function requiredFormValue(InputfieldForm $form, string $name): string
    {
        return trim((string) $form->getChildByName($name)?->value);
    }

    private function nullablePostText(string $name): ?string
    {
        $value = trim((string) $this->wire()->input->post($name));

        return $value === '' ? null : $this->wire()->sanitizer->text($value);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function receiveImportFile(): array
    {
        $upload = $_FILES['import_file'] ?? null;

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new WireException($this->_('Choose a file to preview.'));
        }

        if ((int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new WireException($this->_('Import files are limited to 10 MB.'));
        }

        $originalName = basename((string) ($upload['name'] ?? 'import'));
        $format = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $format = $format === 'ndjson' ? 'jsonl' : $format;
        $this->requireAction($format, ['csv', 'json', 'jsonl', 'xlsx']);
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_import_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create a temporary import file.'));
        }

        $path = $temporary . '.' . $format;
        rename($temporary, $path);

        if (!move_uploaded_file((string) $upload['tmp_name'], $path)) {
            @unlink($path);
            throw new WireException($this->_('Could not store the uploaded file.'));
        }

        return [$path, $format, $originalName];
    }

    private function storeImportPreview(
        string $batchId,
        string $path,
        string $format,
        string $filename,
        string $entityType,
        int $updated
    ): string {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $previews = is_array($previews) ? $previews : [];
        $previews[$batchId] = [
            'path' => $path,
            'format' => $format,
            'filename' => $filename,
            'entityType' => $entityType,
            'updated' => $updated,
            'checksum' => hash_file('sha256', $path),
            'expires' => time() + 3600,
        ];
        $this->wire()->session->set('kontorImportPreviews', $previews);

        return $batchId;
    }

    /**
     * @return array{0: ImportBatchResult, 1: string, 2: string}
     */
    private function commitImportPreview(string $token): array
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $preview = is_array($previews) ? ($previews[$token] ?? null) : null;

        if (!is_array($preview) || (int) ($preview['expires'] ?? 0) < time()) {
            throw new WireException($this->_('This import preview has expired. Upload the file again.'));
        }

        $path = (string) ($preview['path'] ?? '');

        if (
            !$this->isManagedImportPath($path)
            || !is_file($path)
            || !hash_equals((string) ($preview['checksum'] ?? ''), (string) hash_file('sha256', $path))
        ) {
            $this->removeImportPreview($token);
            throw new WireException($this->_('The preview file is no longer available or has changed.'));
        }

        $entityType = (string) $preview['entityType'];
        $format = (string) $preview['format'];

        if ((int) $preview['updated'] > 0) {
            $this->requirePermission('kontor-import-update');
        }

        $backupComponent = $entityType === 'catalog_item' ? 'catalog' : 'contacts';
        $backupLabel = $backupComponent === 'catalog' ? 'Catalog' : 'Contacts';
        $backup = $this->backupManager()->create(
            component: $backupComponent,
            kind: 'snapshot',
            organizationId: $this->organizationUid(),
            reason: "Before import {$token}",
        );

        if (!$backup->verified) {
            throw new WireException(sprintf(
                $this->_('The pre-import %s backup could not be verified.'),
                $backupLabel
            ));
        }

        try {
            $result = $this->importManager()->run(
                entityType: $entityType,
                reader: (new FormatResolver())->reader($format),
                path: $path,
                context: new ImportContext(
                    organizationId: $this->organizationUid(),
                    batchId: $token,
                    dryRun: false,
                    actorType: 'user',
                    actorId: (string) $this->wire()->user->id,
                ),
                backupVerification: new BackupVerification(
                    verified: true,
                    checksum: $backup->checksum,
                ),
                auditOrganizationId: $this->organizationInternalId(),
            );

            if ($result->failed > 0) {
                throw new WireException($this->_('The live import reported failed rows.'));
            }
        } catch (\Throwable $exception) {
            $restore = $this->backupManager()->restore(
                $backup->path,
                $backupComponent,
                $this->organizationUid()
            );

            if (!$restore->success) {
                throw new WireException(
                    $this->_('Import failed and automatic restore also failed: ') . implode('; ', $restore->errors),
                    previous: $exception
                );
            }

            $this->markImportAuditRestored($token);
            throw new WireException(
                sprintf(
                    $this->_('Import failed; %s data was restored from the verified backup.'),
                    $backupLabel
                ),
                previous: $exception
            );
        } finally {
            $this->removeImportPreview($token);
        }

        return [$result, (string) $preview['filename'], $backup->id];
    }

    private function cleanupImportPreviews(): void
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');

        if (!is_array($previews)) {
            return;
        }

        foreach ($previews as $token => $preview) {
            if (!is_array($preview) || (int) ($preview['expires'] ?? 0) < time()) {
                $this->removeImportPreview((string) $token);
            }
        }
    }

    private function removeImportPreview(string $token): void
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $previews = is_array($previews) ? $previews : [];
        $preview = $previews[$token] ?? null;

        if (is_array($preview) && $this->isManagedImportPath((string) ($preview['path'] ?? ''))) {
            @unlink((string) $preview['path']);
        }

        unset($previews[$token]);
        $this->wire()->session->set('kontorImportPreviews', $previews);
    }

    private function isManagedImportPath(string $path): bool
    {
        return $path !== ''
            && dirname($path) === rtrim($this->wire()->config->paths->cache, '/\\')
            && str_starts_with(basename($path), 'kontor_import_');
    }

    private function markImportAuditRestored(string $batchId): void
    {
        $statement = $this->wire()->database->pdo()->prepare(
            "UPDATE kontor_audit_events
             SET action = 'import.restored'
             WHERE correlation_id = :batch_id
               AND action IN ('import.created', 'import.updated')"
        );
        $statement->execute(['batch_id' => $batchId]);
    }

    /**
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed>|null $current
     * @param array<string, mixed> $metadata
     */
    private function audit(
        string $component,
        string $entityType,
        string $entityUid,
        string $action,
        ?array $previous = null,
        ?array $current = null,
        array $metadata = [],
    ): void {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $kontor->container()->get(AuditLogger::class)->record(
            organizationId: $this->organizationInternalId(),
            component: $component,
            entityType: $entityType,
            entityUid: $entityUid,
            action: $action,
            actorType: 'user',
            actorUid: (string) $this->wire()->user->id,
            previous: $previous,
            current: $current,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contactAuditSnapshot(Contact $contact): array
    {
        return [
            'displayName' => $contact->displayName,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'status' => $contact->status,
            'jobTitle' => $contact->jobTitle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function companyAuditSnapshot(Company $company): array
    {
        return [
            'legalName' => $company->legalName,
            'email' => $company->email,
            'phone' => $company->phone,
            'status' => $company->status,
            'registrationNumber' => $company->registrationNumber,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogItemAuditSnapshot(CatalogItem $item): array
    {
        return [
            'title' => $item->title,
            'description' => $item->description,
            'itemType' => $item->itemType,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'categoryUid' => $item->categoryUid,
            'unitCode' => $item->unitCode,
            'taxCode' => $item->taxCode,
            'salesPrice' => $item->salesPrice?->toString(),
            'purchasePrice' => $item->purchasePrice?->toString(),
            'costPrice' => $item->costPrice?->toString(),
            'trackInventory' => $item->trackInventory,
            'status' => $item->status,
        ];
    }

    private function catalogItemTitle(CatalogItem $item): string
    {
        $language = $this->organization()->defaultLanguage;
        $fallback = reset($item->title);

        return $item->titleIn($language)
            ?? $item->titleIn('en')
            ?? (is_string($fallback) && $fallback !== '' ? $fallback : $this->_('Untitled item'));
    }

    /**
     * @return array<string, string>
     */
    private function catalogFormLanguages(): array
    {
        $languages = [
            'en' => $this->_('English'),
            'fr' => $this->_('French'),
            'de' => $this->_('German'),
            'es' => $this->_('Spanish'),
        ];
        $default = $this->organization()->defaultLanguage;

        if (!isset($languages[$default])) {
            return [$default => strtoupper($default), ...$languages];
        }

        if (array_key_first($languages) === $default) {
            return $languages;
        }

        return [
            $default => $languages[$default],
            ...array_diff_key($languages, [$default => true]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogCategoryAuditSnapshot(Category $category): array
    {
        return [
            'name' => $category->name,
            'parentUid' => $category->parentUid,
            'sortOrder' => $category->sortOrder,
            'status' => $category->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPriceListAuditSnapshot(PriceList $priceList): array
    {
        return [
            'name' => $priceList->name,
            'currencyCode' => $priceList->currencyCode,
            'status' => $priceList->status,
            'validFrom' => $priceList->validFrom?->format('Y-m-d'),
            'validTo' => $priceList->validTo?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPriceEntryAuditSnapshot(PriceListEntry $entry): array
    {
        return [
            'priceListUid' => $entry->priceListUid,
            'itemUid' => $entry->itemUid,
            'price' => $entry->price->toString(),
            'minQuantity' => $entry->minQuantity,
            'validFrom' => $entry->validFrom?->format('Y-m-d'),
            'validTo' => $entry->validTo?->format('Y-m-d'),
        ];
    }

    private function categoryName(Category $category): string
    {
        $language = $this->organization()->defaultLanguage;
        $fallback = reset($category->name);

        return $category->nameIn($language)
            ?? $category->nameIn('en')
            ?? (is_string($fallback) && $fallback !== '' ? $fallback : $this->_('Untitled category'));
    }

    /**
     * @return array<string, string>
     */
    private function catalogItemNames(): array
    {
        $names = [];

        foreach ($this->catalogItemRepository()->findAll($this->organizationUid(), limit: 500) as $item) {
            $names[$item->uid->toString()] = $this->catalogItemTitle($item);
        }

        return $names;
    }

    /**
     * @return array<string, int>
     */
    private function priceEntryCounts(array $priceLists): array
    {
        $uids = array_map(
            static fn (PriceList $priceList): string => $priceList->uid->toString(),
            $priceLists,
        );

        return $this->priceRepository()->countsForPriceLists($uids);
    }

    private function findPriceEntry(
        string $priceListUid,
        string $itemUid,
        float $minQuantity,
    ): ?PriceListEntry {
        foreach ($this->priceRepository()->forItem($priceListUid, $itemUid) as $entry) {
            if (abs($entry->minQuantity - $minQuantity) < 0.0000001) {
                return $entry;
            }
        }

        throw new Wire404Exception($this->_('Price tier was not found.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function addressAuditSnapshot(Address $address): array
    {
        return [
            'type' => $address->addressType,
            'line1' => $address->line1,
            'city' => $address->city,
            'region' => $address->region,
            'postalCode' => $address->postalCode,
            'countryCode' => $address->countryCode,
            'isPrimary' => $address->isPrimary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationAuditSnapshot(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'legalName' => $organization->legalName,
            'countryCode' => $organization->countryCode,
            'defaultLanguage' => $organization->defaultLanguage,
            'defaultCurrency' => $organization->defaultCurrency,
            'timezone' => $organization->timezone,
            'dateFormat' => $organization->settings['dateFormat'] ?? 'Y-m-d',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function backupSummaries(): array
    {
        $summaries = [];

        foreach (array_reverse($this->backupManager()->list()) as $path) {
            $metadataPath = $path . DIRECTORY_SEPARATOR . 'metadata.json';

            try {
                $metadata = is_file($metadataPath)
                    ? json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR)
                    : [];
                $component = (string) ($metadata['component'] ?? '');

                if (!in_array($component, ['core', 'contacts', 'catalog'], true)) {
                    continue;
                }

                $kind = (string) ($metadata['kind'] ?? 'snapshot');
                $verified = $this->backupManager()->verify(
                    $path,
                    $component,
                    $this->organizationUid(),
                    $kind
                );
                $summaries[] = [
                    'id' => basename($path),
                    'component' => $component,
                    'kind' => $kind,
                    'createdAt' => (string) ($metadata['exportedAt'] ?? date(DATE_ATOM, filemtime($path) ?: time())),
                    'itemCount' => array_sum(array_map('intval', $metadata['rowCounts'] ?? [])),
                    'sizeBytes' => $this->directorySize($path),
                    'verified' => $verified,
                ];
            } catch (\Throwable) {
                $summaries[] = [
                    'id' => basename($path),
                    'component' => 'unknown',
                    'kind' => 'unknown',
                    'createdAt' => date(DATE_ATOM, filemtime($path) ?: time()),
                    'itemCount' => 0,
                    'sizeBytes' => $this->directorySize($path),
                    'verified' => false,
                ];
            }
        }

        return $summaries;
    }

    private function directorySize(string $path): int
    {
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }

    private function renderTemplate(string $name, array $variables): string
    {
        $variables['adminUrl'] = $this->wire()->config->urls->admin . 'kontor/';
        $variables['csrfName'] = $this->wire()->session->CSRF->getTokenName();
        $variables['csrfValue'] = $this->wire()->session->CSRF->getTokenValue();
        $variables['e'] = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        extract($variables, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/templates/admin/' . $name . '.php';

        return (string) ob_get_clean();
    }

    private function setPageTitle(string $title): void
    {
        $this->headline($title);
        $this->browserTitle($title);
    }

    private function requirePermission(string $permission): void
    {
        $user = $this->wire()->user;

        if (!$user->isSuperuser() && !$user->hasPermission($permission)) {
            throw new WirePermissionException(
                sprintf($this->_('You do not have the required permission: %s'), $permission)
            );
        }
    }

    private function requirePost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new WireException($this->_('This action requires a POST request.'));
        }

        $this->wire()->session->CSRF->validate();
    }

    private function requireSameOrganization(string $organizationUid): void
    {
        if (!hash_equals($this->organizationUid(), $organizationUid)) {
            throw new WirePermissionException($this->_('This record belongs to another organization.'));
        }
    }

    /**
     * @param string[] $allowed
     */
    private function requireAction(?string $action, array $allowed): void
    {
        if ($action === null || !in_array($action, $allowed, true)) {
            throw new WireException($this->_('Invalid action.'));
        }
    }

    /**
     * @return array{
     *   query: string,
     *   component: string|null,
     *   entityType: string|null,
     *   action: string|null,
     *   options: array{components: string[], entityTypes: string[], actions: string[]}
     * }
     */
    private function activityFilterSelection(int $organizationId): array
    {
        $options = $this->auditEventRepository()->filterOptions($organizationId);
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $component = $this->wire()->sanitizer->text((string) $this->wire()->input->get('component'));
        $entityType = $this->wire()->sanitizer->text((string) $this->wire()->input->get('entity_type'));
        $action = $this->wire()->sanitizer->text((string) $this->wire()->input->get('action'));

        return [
            'query' => $query,
            'component' => $component !== '' ? $component : null,
            'entityType' => $entityType !== '' ? $entityType : null,
            'action' => $action !== '' ? $action : null,
            'options' => $options,
        ];
    }

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
     * @return array<string, string>
     */
    private function collaborationTaskLabels(): array
    {
        if (!$this->tasksReady()) {
            return [];
        }

        $labels = [];
        foreach ($this->taskModule()->taskRepository()->findMatching(
            $this->organizationUid(),
            limit: 250,
        ) as $task) {
            $labels[$task->uid->toString()] = $task->title;
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
