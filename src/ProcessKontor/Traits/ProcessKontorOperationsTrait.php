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
trait ProcessKontorOperationsTrait
{

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
        $canViewSuppliers = $this->can('kontor-purchasing-supplier-view');
        $suppliers = $canViewSuppliers
            ? $module->supplierRepository()->forOrganization($this->organizationUid())
            : [];
        $supplierLabels = array_column(array_map(
            static fn (Supplier $supplier): array => [
                $supplier->uid->toString(),
                $supplier->code . ' · ' . $supplier->legalName,
            ],
            $suppliers,
        ), 1, 0);
        $allOrders = $module->purchaseOrderRepository()->forOrganization($this->organizationUid());
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $selectedStatus = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('status')
        ));
        $statuses = [];
        foreach ($allOrders as $order) {
            $statuses[$order->status] = true;
        }
        ksort($statuses);
        if ($selectedStatus !== '' && !isset($statuses[$selectedStatus])) {
            $selectedStatus = '';
        }
        $orders = array_values(array_filter(
            $allOrders,
            static function ($order) use ($query, $selectedStatus, $supplierLabels): bool {
                if ($selectedStatus !== '' && $order->status !== $selectedStatus) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }
                $haystack = implode(' ', [
                    (string) ($order->number ?? 'Draft'),
                    (string) ($supplierLabels[$order->supplierUid] ?? ''),
                ]);

                return mb_stripos($haystack, $query) !== false;
            }
        ));

        return $this->renderTemplate('purchasing', [
            'suppliers' => $suppliers,
            'supplierLabels' => $supplierLabels,
            'orders' => $orders,
            'allOrders' => $allOrders,
            'query' => $query,
            'selectedStatus' => $selectedStatus,
            'statuses' => array_keys($statuses),
            'canViewSuppliers' => $canViewSuppliers,
            'canCreateSupplier' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-purchasing-supplier-create'),
            'canCreateOrder' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-purchasing-po-create'),
            'canReceive' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-purchasing-receipt-create'),
            'canViewInventory' => $this->inventoryReady() && $this->can('kontor-inventory-stock-view'),
            'canViewExpenses' => $this->expensesReady() && $this->can('kontor-expenses-expense-view'),
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
                'email' => trim($this->wire()->sanitizer->text(
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
            } elseif ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
                $error = $this->_('Enter a valid ordering email address.');
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
            $this->requirePermission('kontor-purchasing-supplier-view');
        }
        $canViewSuppliers = $this->can('kontor-purchasing-supplier-view');
        $allSuppliers = $canViewSuppliers
            ? $module->supplierRepository()->forOrganization($this->organizationUid())
            : [];
        $suppliers = array_values(array_filter(
            $allSuppliers,
            static fn (Supplier $supplier): bool => $supplier->isActive(),
        ));
        $allWarehouses = $this->inventoryModule()->warehouseRepository()->forOrganization($this->organizationUid());
        $warehouses = array_values(array_filter(
            $allWarehouses,
            static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
        ));
        $supplierLabels = array_column(array_map(
            static fn (Supplier $supplier): array => [$supplier->uid->toString(), $supplier->legalName],
            $allSuppliers,
        ), 1, 0);
        $warehouseLabels = array_column(array_map(
            static fn (Warehouse $warehouse): array => [$warehouse->uid->toString(), $warehouse->name],
            $allWarehouses,
        ), 1, 0);
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
            'supplierLabel' => $order !== null
                ? ($supplierLabels[$order->supplierUid] ?? ($canViewSuppliers
                    ? $this->_('Supplier record unavailable')
                    : $this->_('Restricted supplier')))
                : '',
            'warehouseLabel' => $order !== null
                ? ($warehouseLabels[$order->warehouseUid ?? ''] ?? $this->_('Warehouse record unavailable'))
                : '',
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
            'canCreateSupplier' => $this->can('kontor-purchasing-supplier-create'),
            'canViewInventory' => $this->inventoryReady() && $this->can('kontor-inventory-stock-view'),
            'canManageWarehouses' => $this->inventoryReady()
                && $this->can('kontor-inventory-warehouse-admin'),
            'canViewCatalog' => $this->catalogReady() && $this->can('kontor-catalog-item-view'),
            'canCreateCatalogItem' => $this->catalogReady()
                && $this->can('kontor-catalog-item-create'),
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
        $canViewCategories = $this->can('kontor-expenses-category-view');
        $categories = $canViewCategories
            ? $module->categoryRepository()->forOrganization($this->organizationUid())
            : [];
        $categoryLabels = array_column(array_map(
            static fn (ExpenseCategory $category): array => [
                $category->uid->toString(),
                $category->name,
            ],
            $categories,
        ), 1, 0);
        $selectedCategory = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('category')
        );
        if ($selectedCategory !== '' && !isset($categoryLabels[$selectedCategory])) {
            $selectedCategory = '';
        }
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $allExpenses = $module->expenseRepository()->forOrganization($this->organizationUid());
        $expenses = array_values(array_filter(
            $allExpenses,
            static function (Expense $expense) use ($status, $selectedCategory, $query, $categoryLabels): bool {
                if ($status !== null && $expense->status !== $status) {
                    return false;
                }
                if ($selectedCategory !== '' && $expense->categoryUid !== $selectedCategory) {
                    return false;
                }
                if ($query !== '') {
                    $category = $categoryLabels[$expense->categoryUid] ?? '';
                    if (mb_stripos($expense->description . ' ' . $category, $query) === false) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $this->setPageTitle($this->_('Kontor · Expenses'));

        return $this->renderTemplate('expenses', [
            'expenses' => $expenses,
            'allExpenses' => $allExpenses,
            'categories' => $categories,
            'categoryLabels' => $categoryLabels,
            'selectedStatus' => $status,
            'selectedCategory' => $selectedCategory,
            'query' => $query,
            'canViewCategories' => $canViewCategories,
            'canCreateExpense' => $canViewCategories && ($this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-expenses-expense-create')),
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
            $this->requirePermission('kontor-expenses-category-view');
        }
        $canViewCategories = $this->can('kontor-expenses-category-view');
        $categories = $canViewCategories
            ? array_values(array_filter(
                $module->categoryRepository()->forOrganization($this->organizationUid()),
                static fn (ExpenseCategory $category): bool => $category->isActive(),
            ))
            : [];
        $canViewSuppliers = $this->purchasingReady()
            && $this->can('kontor-purchasing-supplier-view');
        $suppliers = $canViewSuppliers
            ? array_values(array_filter(
                $this->purchasingModule()->supplierRepository()->forOrganization($this->organizationUid()),
                static fn (Supplier $supplier): bool => $supplier->isActive(),
            ))
            : [];
        $canUseFiles = $this->filesReady() && $this->can('kontor-files-file-view');
        $receiptFiles = $canUseFiles
            ? array_values(array_filter(
                $this->filesModule()->fileRepository()->forOrganization($this->organizationInternalId()),
                static fn (array $file): bool => $file['archived_at'] === null,
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
            $receiptFileMap = [];
            foreach ($receiptFiles as $receiptFile) {
                $receiptFileMap[(string) $receiptFile['uid']] = $receiptFile;
            }
            $amount = str_replace(',', '.', $values['amount']);
            if (!isset($categoryMap[$values['categoryUid']])) {
                $error = $this->_('Select an active expense category.');
            } elseif ($values['supplierUid'] !== '' && !isset($supplierMap[$values['supplierUid']])) {
                $error = $this->_('Selected supplier is invalid.');
            } elseif ($values['receiptFileUid'] !== '' && !isset($receiptFileMap[$values['receiptFileUid']])) {
                $error = $this->_('Selected receipt file is unavailable.');
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
            ? $this->_('Kontor · Record expense')
            : sprintf($this->_('Kontor · %s'), $expense->description));

        $workflowCoordinator = $expense !== null ? $module->workflowCoordinator() : null;

        return $this->renderTemplate('expense', [
            'expense' => $expense,
            'values' => $values,
            'error' => $error,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'receiptFiles' => $receiptFiles,
            'canUseFiles' => $canUseFiles,
            'canManageCategories' => $this->can('kontor-expenses-category-manage'),
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
        $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
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

        $module = $this->projectsModule();
        $customerLabels = $this->salesCustomerLabels();
        $allProjects = $module->projectRepository()->forOrganization($this->organizationUid());
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $selectedStatus = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('status')
        ));
        $statuses = [];
        $projectContexts = [];
        $today = new \DateTimeImmutable('today');

        foreach ($allProjects as $project) {
            $projectUid = $project->uid->toString();
            $customerKey = ($project->customerType ?? '') . ':' . ($project->customerUid ?? '');
            $milestones = $module->milestoneRepository()->forProject($projectUid);
            $timeEntries = $module->timeEntryRepository()->forProject($projectUid);
            $billableItems = $module->billableItemRepository()->forProject($projectUid);
            $completedMilestones = 0;
            $overdueMilestones = 0;
            foreach ($milestones as $milestone) {
                if ($milestone->isCompleted()) {
                    ++$completedMilestones;
                } elseif ($milestone->dueDate !== null && $milestone->dueDate < $today) {
                    ++$overdueMilestones;
                }
            }

            $trackedMinutes = 0;
            $unbilledMinutes = 0;
            $runningTimers = 0;
            $unbilledByCurrency = [];
            foreach ($timeEntries as $entry) {
                $trackedMinutes += $entry->durationMinutes ?? 0;
                if ($entry->isRunning()) {
                    ++$runningTimers;
                }
                if (!$entry->billable || $entry->isInvoiced() || $entry->durationMinutes === null) {
                    continue;
                }
                $unbilledMinutes += $entry->durationMinutes;
                $rate = $entry->hourlyRateMinor ?? $project->defaultHourlyRateMinor;
                $currency = $entry->currencyCode ?? $project->currencyCode;
                if ($rate !== null && $currency !== null) {
                    $unbilledByCurrency[$currency] = ($unbilledByCurrency[$currency] ?? 0)
                        + (int) round(($entry->durationMinutes / 60) * $rate);
                }
            }
            foreach ($billableItems as $item) {
                if ($item->isInvoiced()) {
                    continue;
                }
                $total = $item->total();
                $currency = $total->currencyCode();
                $unbilledByCurrency[$currency] = ($unbilledByCurrency[$currency] ?? 0)
                    + $total->amountMinor();
            }
            ksort($unbilledByCurrency);

            $customerRoute = null;
            if ($this->contactsReady() && $project->customerUid !== null) {
                if ($project->customerType === 'contact' && $this->can('kontor-contacts-contact-view')) {
                    $customerRoute = 'contact/?id=' . rawurlencode($project->customerUid);
                } elseif ($project->customerType === 'company' && $this->can('kontor-contacts-company-view')) {
                    $customerRoute = 'company/?id=' . rawurlencode($project->customerUid);
                }
            }

            $statuses[$project->status] = true;
            $projectContexts[$projectUid] = [
                'customerLabel' => $customerLabels[$customerKey] ?? null,
                'customerRoute' => $customerRoute,
                'milestoneCount' => count($milestones),
                'completedMilestones' => $completedMilestones,
                'overdueMilestones' => $overdueMilestones,
                'trackedMinutes' => $trackedMinutes,
                'unbilledMinutes' => $unbilledMinutes,
                'runningTimers' => $runningTimers,
                'unbilledByCurrency' => $unbilledByCurrency,
            ];
        }
        ksort($statuses);
        if ($selectedStatus !== '' && !isset($statuses[$selectedStatus])) {
            $selectedStatus = '';
        }

        $projects = array_values(array_filter(
            $allProjects,
            static function ($project) use ($query, $selectedStatus, $projectContexts): bool {
                if ($selectedStatus !== '' && $project->status !== $selectedStatus) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }
                $context = $projectContexts[$project->uid->toString()];
                $haystack = implode(' ', [
                    $project->code,
                    $project->name,
                    (string) ($context['customerLabel'] ?? ''),
                ]);

                return mb_stripos($haystack, $query) !== false;
            }
        ));

        return $this->renderTemplate('projects', [
            'projects' => $projects,
            'allProjects' => $allProjects,
            'projectContexts' => $projectContexts,
            'query' => $query,
            'selectedStatus' => $selectedStatus,
            'statuses' => array_keys($statuses),
            'canCreate' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-projects-project-create'),
            'canViewTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-view'),
            'canViewInvoices' => $this->invoicesReady() && $this->can('kontor-invoices-invoice-view'),
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

        $customerRoute = null;
        if ($project !== null && $this->contactsReady() && $project->customerUid !== null) {
            if ($project->customerType === 'contact' && $this->can('kontor-contacts-contact-view')) {
                $customerRoute = 'contact/?id=' . rawurlencode($project->customerUid);
            } elseif ($project->customerType === 'company' && $this->can('kontor-contacts-company-view')) {
                $customerRoute = 'company/?id=' . rawurlencode($project->customerUid);
            }
        }

        return $this->renderTemplate('project', [
            'project' => $project,
            'values' => $values,
            'error' => $error,
            'customers' => $customers,
            'customerLabel' => $project !== null
                ? ($customers[($project->customerType ?? '') . ':' . ($project->customerUid ?? '')] ?? '—')
                : '—',
            'customerRoute' => $customerRoute,
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
            'canViewTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-view'),
            'canViewInvoices' => $this->invoicesReady() && $this->can('kontor-invoices-invoice-view'),
            'canViewContacts' => $this->contactsReady() && $this->can('kontor-contacts-contact-view'),
            'canViewCompanies' => $this->contactsReady() && $this->can('kontor-contacts-company-view'),
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
}
