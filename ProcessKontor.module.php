<?php

namespace ProcessWire;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
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
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

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
            'version' => '028',
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
        $version = (string) (@filemtime(__DIR__ . '/assets/kontor.admin.css') ?: self::getModuleInfo()['version']);
        $this->wire()->config->styles->add($moduleUrl . 'assets/kontor.admin.css?v=' . $version);
    }

    public function ___execute(): string
    {
        $this->setPageTitle($this->_('Kontor · Dashboard'));
        $components = $this->componentRegistry()->all();
        $contactsReady = $this->contactsReady();
        $organizationUid = $contactsReady ? $this->organizationUid() : null;
        $contacts = $contactsReady ? $this->contactRepository()->countActive($organizationUid) : 0;
        $companies = $contactsReady ? $this->companyRepository()->countActive($organizationUid) : 0;
        $recentContacts = $contactsReady ? $this->contactRepository()->findAll($organizationUid, '', 6) : [];
        $user = $this->wire()->user;
        $canViewActivity = $user->isSuperuser() || $user->hasPermission('kontor-audit-view');
        $canViewQueue = $user->isSuperuser() || $user->hasPermission('kontor-queue-view');
        $queueReady = $this->queueReady();

        return $this->renderTemplate('dashboard', [
            'components' => $components,
            'contactsReady' => $contactsReady,
            'contactCount' => $contacts,
            'companyCount' => $companies,
            'recentContacts' => $recentContacts,
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
        ]);
    }

    public function ___executeContacts(): string
    {
        $this->requirePermission('kontor-contacts-contact-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Contacts'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalRecords = $this->contactRepository()->countMatching(
            $organizationUid,
            $query,
            $showArchived,
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
                )
                : $this->contactRepository()->findAll(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                ),
            'query' => $query,
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
        ]);
    }

    public function ___executeCompanies(): string
    {
        $this->requirePermission('kontor-contacts-company-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Companies'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalRecords = $this->companyRepository()->countMatching(
            $organizationUid,
            $query,
            $showArchived,
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
                )
                : $this->companyRepository()->findAll(
                    $organizationUid,
                    $query,
                    $pageSize,
                    ($page - 1) * $pageSize,
                ),
            'query' => $query,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
        ]);
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
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('type'),
            ['contact', 'company']
        ) ?? '';
        $pageSize = 20;
        $page = max(1, (int) $this->wire()->input->get('page'));
        $totalPages = 1;
        $result = null;

        if (mb_strlen($query) >= 2) {
            $result = $this->searchService()->search(new SearchQuery(
                organizationId: $this->organizationUid(),
                term: $query,
                entityTypes: $entityType !== '' ? [$entityType] : ['contact', 'company'],
                limit: $pageSize,
                offset: ($page - 1) * $pageSize,
            ));
            $totalPages = max(1, (int) ceil($result->total / $pageSize));

            if ($page > $totalPages) {
                $page = $totalPages;
                $result = $this->searchService()->search(new SearchQuery(
                    organizationId: $this->organizationUid(),
                    term: $query,
                    entityTypes: $entityType !== '' ? [$entityType] : ['contact', 'company'],
                    limit: $pageSize,
                    offset: ($page - 1) * $pageSize,
                ));
            }
        }

        return $this->renderTemplate('search', [
            'query' => $query,
            'result' => $result,
            'selectedEntityType' => $entityType,
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
        $showArchived = (string) $this->wire()->input->get('archived') === '1';
        $organizationUid = $this->organizationUid();
        $pageSize = 25;
        $totalItems = $this->catalogItemRepository()->countMatching(
            $organizationUid,
            $query,
            $itemType,
            $showArchived,
        );
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = min($totalPages, max(1, (int) $this->wire()->input->get('page')));

        return $this->renderTemplate('catalog', [
            'items' => $this->catalogItemRepository()->findAll(
                $organizationUid,
                $query,
                $itemType,
                $showArchived,
                $pageSize,
                ($page - 1) * $pageSize,
            ),
            'query' => $query,
            'selectedType' => $itemType,
            'showArchived' => $showArchived,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
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

        return $this->renderTemplate('catalog-form', [
            'form' => $form,
            'item' => $item,
            'title' => $item === null ? $this->_('Create catalog item') : $this->catalogItemTitle($item),
        ]);
    }

    public function ___executeCatalogItemAction(): void
    {
        $this->requirePost();
        $this->requireCatalog();
        $this->requirePermission('kontor-catalog-item-archive');
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
        $this->wire()->session->redirect('../catalog/' . ($action === 'restore' ? '?archived=1' : ''));
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
            ['core', 'contacts', 'unknown']
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
            ['pending', 'reserved', 'completed', 'dead', 'cancelled']
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
            ['core', 'contacts']
        );
        $this->requireAction($component, ['core', 'contacts']);
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
            !in_array($component, ['core', 'contacts'], true)
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
        $this->requireContacts();
        $this->requirePermission('kontor-contacts-export');
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('entity'),
            ['contact', 'company']
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('format'),
            ['csv', 'json', 'jsonl', 'xlsx']
        );
        $this->requireAction($entityType, ['contact', 'company']);
        $this->requireAction($format, ['csv', 'json', 'jsonl', 'xlsx']);

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
        $filename = 'kontor-' . ($entityType === 'contact' ? 'contacts' : 'companies') . "-{$date}.{$format}";
        $this->audit(
            'core',
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
        $this->requireContacts();
        $this->requirePermission('kontor-import');
        $this->setPageTitle($this->_('Kontor · Import preview'));
        $entityType = $this->wire()->sanitizer->option(
            (string) ($this->wire()->input->post('entity') ?: $this->wire()->input->get('entity')),
            ['contact', 'company']
        ) ?? 'contact';
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
        $this->addTextField(
            $form,
            'title',
            sprintf($this->_('Title (%s)'), strtoupper($language)),
            $item?->titleIn($language),
            true,
            50,
        );
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
        $this->addTextareaField(
            $form,
            'description',
            sprintf($this->_('Description (%s)'), strtoupper($language)),
            $item?->description[$language] ?? null,
        );
        $this->addSubmit($form, $this->_('Save catalog item'));

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
    }

    private function saveCatalogItemFromForm(InputfieldForm $form, ?CatalogItem $item): CatalogItem
    {
        $language = $this->organization()->defaultLanguage;

        if ($item === null) {
            $item = CatalogItem::create(
                organizationId: $this->organizationUid(),
                title: [$language => $this->requiredFormValue($form, 'title')],
            );
        }

        $item->title[$language] = $this->requiredFormValue($form, 'title');
        $description = $this->formValue($form, 'description');

        if ($description === null) {
            unset($item->description[$language]);
        } else {
            $item->description[$language] = $description;
        }

        $item->itemType = $this->requiredFormValue($form, 'item_type');
        $item->status = $this->requiredFormValue($form, 'status');
        $item->sku = $this->formValue($form, 'sku');
        $item->barcode = $this->formValue($form, 'barcode');
        $item->unitCode = $this->requiredFormValue($form, 'unit_code');
        $item->taxCode = $this->formValue($form, 'tax_code');
        $item->salesPrice = $this->moneyFromForm($form, 'sales');
        $item->purchasePrice = $this->moneyFromForm($form, 'purchase');
        $item->costPrice = $this->moneyFromForm($form, 'cost');
        $item->trackInventory = (bool) $form->getChildByName('track_inventory')?->value;
        $this->catalogItemRepository()->save($item);

        return $item;
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
        ?string $value
    ): void {
        /** @var InputfieldTextarea $field */
        $field = $this->wire()->modules->get('InputfieldTextarea');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->rows = 5;
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

        $backup = $this->backupManager()->create(
            component: 'contacts',
            kind: 'snapshot',
            organizationId: $this->organizationUid(),
            reason: "Before import {$token}",
        );

        if (!$backup->verified) {
            throw new WireException($this->_('The pre-import Contacts backup could not be verified.'));
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
                'contacts',
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
                $this->_('Import failed; Contacts data was restored from the verified backup.'),
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
            'itemType' => $item->itemType,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
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

                if (!in_array($component, ['core', 'contacts'], true)) {
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

    private function requireCatalog(): void
    {
        if (!$this->catalogReady()) {
            throw new WireException($this->_('The Kontor Catalog component is not installed.'));
        }
    }

    private function requireContacts(): void
    {
        if (!$this->contactsReady()) {
            throw new WireException($this->_('The Kontor Contacts component is not installed.'));
        }
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
