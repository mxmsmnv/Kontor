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
trait ProcessKontorCatalogTrait
{

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
            'unitLabels' => $this->catalogUnitOptions($item),
            'selectedUnitCode' => $this->wire()->input->post('submit_save')
                ? $this->requiredFormValue($form, 'unit_code')
                : ($item?->unitCode ?? 'pcs'),
            'formLanguages' => $this->catalogFormLanguages(),
            'defaultLanguage' => $this->organization()->defaultLanguage,
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
}
