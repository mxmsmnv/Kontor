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
trait ProcessKontorNavigationTrait
{

    public function customizePrimaryNavigation(HookEvent $event): void
    {
        $navigation = $event->return;
        if (!is_array($navigation)) {
            return;
        }

        $adminUrl = $this->wire()->config->urls->admin . 'kontor/';
        $replace = function (array &$items) use (&$replace, $adminUrl): void {
            foreach ($items as &$item) {
                if (
                    isset($item['url'])
                    && rtrim((string) $item['url'], '/') === rtrim($adminUrl, '/')
                ) {
                    $children = [];
                    foreach ($this->quickNavigationItems() as $quickItem) {
                        $children[] = $this->primaryNavigationChild(
                            $quickItem,
                            (int) ($item['id'] ?? 0),
                        );
                    }
                    $available = $this->availableNavigationItems();
                    if (isset($available['components'])) {
                        $children[] = $this->primaryNavigationChild(
                            $available['components'],
                            (int) ($item['id'] ?? 0),
                        );
                    }
                    $children[] = $this->primaryNavigationChild([
                        'url' => 'sections/',
                        'label' => 'Quick Access',
                        'icon' => 'th-large',
                    ], (int) ($item['id'] ?? 0));
                    $item['children'] = $children;

                    return;
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $replace($item['children']);
                }
            }
        };
        $replace($navigation);
        $event->return = $navigation;
    }

    private function refreshNavigationCache(): void
    {
        $moduleInfo = self::getModuleInfo();
        $signature = hash('sha256', json_encode(
            ['mode' => 'personal-quick-access-v4', 'items' => $moduleInfo['kontorNavigation'] ?? []],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
        $session = $this->wire()->session;

        if ($session->getFor($this, 'navigationSignature') === $signature) {
            return;
        }

        $this->clearPrimaryNavigationCache();
        $session->setFor($this, 'navigationSignature', $signature);
    }

    private function clearPrimaryNavigationCache(): void
    {
        $adminTheme = $this->wire()->adminTheme;
        if (!is_object($adminTheme)) {
            return;
        }

        $session = $this->wire()->session;
        $session->removeFor($adminTheme, 'prnav');
        $session->removeFor($adminTheme, 'sidenav');
    }

    /**
     * @return array<string, array{url: string, label: string, icon: string, permission?: string}>
     */
    private function availableNavigationItems(): array
    {
        $user = $this->wire()->user;

        return (new NavigationAvailability())->resolve(
            self::getModuleInfo()['kontorNavigation'] ?? [],
            fn (string $module): bool => $this->wire()->modules->isInstalled($module),
            static fn (string $permission): bool => $user->isSuperuser()
                || $user->hasPermission($permission),
        );
    }

    /**
     * @return array<int, string>
     */
    private function quickNavigationKeys(): array
    {
        $stored = $this->wire()->user->meta(self::QUICK_NAVIGATION_META);
        $keys = is_array($stored) ? $stored : self::DEFAULT_QUICK_NAVIGATION;
        $available = $this->availableNavigationItems();

        return array_slice(array_values(array_filter(
            array_unique(array_map('strval', $keys)),
            static fn (string $key): bool => !in_array($key, ['dashboard', 'components'], true)
                && isset($available[$key]),
        )), 0, self::QUICK_NAVIGATION_LIMIT);
    }

    /**
     * @return array<int, array{url: string, label: string, icon: string, permission?: string}>
     */
    private function quickNavigationItems(): array
    {
        $available = $this->availableNavigationItems();
        $items = isset($available['dashboard']) ? [$available['dashboard']] : [];
        foreach ($this->quickNavigationKeys() as $key) {
            $items[] = $available[$key];
        }

        return $items;
    }

    /**
     * @param array{url: string, label: string, icon: string, permission?: string} $item
     * @return array<string, mixed>
     */
    private function primaryNavigationChild(array $item, int $parentId): array
    {
        return [
            'id' => 0,
            'parent_id' => $parentId,
            'title' => $this->wire()->sanitizer->entities1($this->_($item['label'])),
            'name' => '',
            'url' => $this->wire()->config->urls->admin . 'kontor/' . $item['url'],
            'icon' => $item['icon'],
            'children' => [],
            'navJSON' => '',
        ];
    }

    /**
     * @return array<string, array<int, array{key: string, url: string, label: string, icon: string, permission?: string}>>
     */
    private function navigationGroups(): array
    {
        $available = $this->availableNavigationItems();
        $definitions = [
            'Start' => ['dashboard', 'demo', 'search'],
            'Customers & revenue' => [
                'contacts', 'companies', 'crm', 'sales', 'invoices', 'payments',
            ],
            'Operations' => [
                'catalog', 'inventory', 'purchasing', 'expenses', 'projects',
                'tasks', 'collaboration', 'reports',
            ],
            'Automation & content' => [
                'workflows', 'automations', 'custom-entities', 'mail', 'portal',
                'files', 'documents', 'ai',
            ],
            'Finance & localization' => ['ledger', 'germany'],
            'Platform' => [
                'api', 'graphql', 'marketplace', 'cache', 'activity', 'backups',
                'health', 'queue', 'organization', 'settings-migration', 'components',
            ],
        ];
        $groups = [];
        $assigned = [];
        foreach ($definitions as $label => $keys) {
            foreach ($keys as $key) {
                if (!isset($available[$key])) {
                    continue;
                }
                $groups[$label][] = ['key' => $key, ...$available[$key]];
                $assigned[$key] = true;
            }
        }
        foreach ($available as $key => $item) {
            if (!isset($assigned[$key])) {
                $groups['More'][] = ['key' => $key, ...$item];
            }
        }

        return $groups;
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
        $storedDashboardIntro = $user->meta(self::DASHBOARD_INTRO_META);
        $dashboardHeadline = is_array($storedDashboardIntro)
            && is_string($storedDashboardIntro['headline'] ?? null)
            && trim($storedDashboardIntro['headline']) !== ''
                ? trim($storedDashboardIntro['headline'])
                : self::DEFAULT_DASHBOARD_HEADLINE;
        $dashboardMessage = is_array($storedDashboardIntro)
            && is_string($storedDashboardIntro['message'] ?? null)
                ? trim($storedDashboardIntro['message'])
                : self::DEFAULT_DASHBOARD_MESSAGE;

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
            'dashboardHeadline' => $dashboardHeadline,
            'dashboardMessage' => $dashboardMessage,
            'dashboardIntroCustomized' => is_array($storedDashboardIntro),
            'navigationGroups' => $this->navigationGroups(),
            'quickNavigationKeys' => $this->quickNavigationKeys(),
        ]);
    }

    public function ___executeSections(): string
    {
        $this->setPageTitle($this->_('Kontor · Quick Access'));

        return $this->renderTemplate('sections', [
            'navigationGroups' => $this->navigationGroups(),
            'quickNavigationKeys' => $this->quickNavigationKeys(),
            'quickNavigationLimit' => self::QUICK_NAVIGATION_LIMIT,
        ]);
    }

    public function ___executeQuickNavigationSave(): void
    {
        $this->requirePost();
        $raw = $this->wire()->input->post('quick_navigation');
        $requested = is_array($raw) ? $raw : [];
        $available = $this->availableNavigationItems();
        $keys = [];
        foreach ($requested as $value) {
            $key = $this->wire()->sanitizer->pageName((string) $value);
            if (
                !in_array($key, ['dashboard', 'components'], true)
                && isset($available[$key])
                && !in_array($key, $keys, true)
            ) {
                $keys[] = $key;
            }
            if (count($keys) >= self::QUICK_NAVIGATION_LIMIT) {
                break;
            }
        }

        $this->wire()->user->meta()->set(self::QUICK_NAVIGATION_META, $keys);
        $this->clearPrimaryNavigationCache();
        $this->message(sprintf(
            $this->_('Quick access updated: %d section(s).'),
            count($keys),
        ));
        $this->wire()->session->redirect('../sections/');
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

    public function ___executeDashboardIntroSave(): void
    {
        $this->requirePost();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['save', 'reset'],
        ) ?? 'save';

        if ($action === 'reset') {
            $this->wire()->user->meta()->set(self::DASHBOARD_INTRO_META, null);
            $this->message($this->_('Dashboard intro restored to the default.'));
            $this->wire()->session->redirect('../');

            return;
        }

        $headline = mb_substr(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('dashboard_headline')
        )), 0, 90);
        $message = mb_substr(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('dashboard_message')
        )), 0, 180);

        if ($headline === '') {
            throw new WireException($this->_('Dashboard headline is required.'));
        }

        $this->wire()->user->meta()->set(self::DASHBOARD_INTRO_META, [
            'headline' => $headline,
            'message' => $message,
        ]);
        $this->message($this->_('Dashboard intro updated.'));
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
                    max(
                        0,
                        min(12 - max(2, min(12, $widget->width)), $widget->positionX + ($action === 'move_left' ? -1 : 1)),
                    ),
                    $widget->positionY,
                );
            } else {
                $maximumWidth = max(2, 12 - max(0, min(10, $widget->positionX)));
                $module->dashboardService()->resizeWidget(
                    $widgetUid,
                    max(2, min($maximumWidth, $widget->width + ($action === 'narrower' ? -1 : 1))),
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
}
