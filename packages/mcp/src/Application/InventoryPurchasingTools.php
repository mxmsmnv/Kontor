<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Domain\InventoryMovement;
use Kontor\Inventory\Domain\StockBalance;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait InventoryPurchasingTools
{
    private function inventoryPurchasingTools(): array
    {
        $modules = $this->wire()->modules;
        $tools = [];
        $uid = $this->uidSchema();
        $limit = ['type' => 'integer', 'minimum' => 1, 'maximum' => 50];
        $offset = ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000];

        if ($modules->isInstalled('KontorInventory')) {
            $tools = [
                $this->tool('kontor_inventory_warehouses_list', 'List inventory warehouses',
                    'List warehouse codes, names and statuses in the current organization.',
                    $this->mcpInventoryWarehousesList(...), 'read', ['limit' => $limit, 'offset' => $offset]),
                $this->tool('kontor_inventory_balances_list', 'List stock balances',
                    'List recent stock balances by item and warehouse.',
                    $this->mcpInventoryBalancesList(...), 'read', ['limit' => $limit]),
                $this->tool('kontor_inventory_movements_list', 'List inventory movements',
                    'List recent recorded stock movements.',
                    $this->mcpInventoryMovementsList(...), 'read', ['limit' => $limit]),
            ];
        }

        if (!$modules->isInstalled('KontorPurchasing')) {
            return $tools;
        }

        array_push($tools,
            $this->tool('kontor_purchasing_suppliers_list', 'List purchasing suppliers',
                'List suppliers and their ordering defaults in this organization.',
                $this->mcpPurchasingSuppliersList(...), 'read', ['limit' => $limit, 'offset' => $offset]),
            $this->tool('kontor_purchase_orders_list', 'List purchase orders',
                'List recent purchase orders and draft status in the current organization.',
                $this->mcpPurchaseOrdersList(...), 'read', ['limit' => $limit]),
            $this->tool('kontor_purchase_order_get', 'Read a purchase order',
                'Read a purchase order and the first 50 document lines.',
                $this->mcpPurchaseOrderGet(...), 'read', ['uid' => $uid], ['uid']),
        );

        if ($modules->isInstalled('KontorInventory') && $modules->isInstalled('KontorCatalog')) {
            $tools[] = $this->tool('kontor_purchase_order_create_draft', 'Create a draft purchase order',
                'Create a draft with active tracked products, without issuing an order or receiving stock.',
                $this->mcpPurchaseOrderCreateDraft(...), 'draft', [
                    'supplier_uid' => $uid,
                    'warehouse_uid' => $uid,
                    'currency_code' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
                    'expected_date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    'lines' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 25, 'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['item_uid', 'quantity', 'unit_price_minor'],
                        'properties' => [
                            'item_uid' => $uid,
                            'quantity' => ['type' => 'number', 'minimum' => 0.000001, 'maximum' => 1000000],
                            'unit_price_minor' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000000],
                        ],
                    ]],
                    'idempotency_key' => $this->idempotencySchema(),
                ], ['supplier_uid', 'warehouse_uid', 'currency_code', 'lines', 'idempotency_key']);
        }
        return $tools;
    }

    public function mcpInventoryWarehousesList(int $limit = 20, int $offset = 0): array
    {
        $this->requireInventoryPurchasingActor('kontor-inventory-stock-view');
        $this->validateInventoryPurchasingPage($limit, $offset);
        $module = $this->inventoryPurchasingModule('KontorInventory');
        $rows = $module->warehouseRepository()->forOrganization($this->inventoryPurchasingOrganization()->uid->toString(), $limit, $offset);

        return ['items' => array_map(static fn (Warehouse $warehouse): array => [
            'uid' => $warehouse->uid->toString(), 'code' => $warehouse->code,
            'name' => $warehouse->name, 'status' => $warehouse->status,
        ], $rows)];
    }

    public function mcpInventoryBalancesList(int $limit = 20): array
    {
        $this->requireInventoryPurchasingActor('kontor-inventory-stock-view');
        $this->validateInventoryPurchasingPage($limit);
        $module = $this->inventoryPurchasingModule('KontorInventory');
        $rows = $module->balanceRepository()->forOrganization($this->inventoryPurchasingOrganization()->uid->toString(), $limit);
        return ['items' => array_map(static fn (StockBalance $balance): array => [
            'itemUid' => $balance->itemUid, 'warehouseUid' => $balance->warehouseUid,
            'onHand' => $balance->quantityOnHand, 'reserved' => $balance->quantityReserved, 'available' => $balance->quantityAvailable,
        ], $rows)];
    }

    public function mcpInventoryMovementsList(int $limit = 20): array
    {
        $this->requireInventoryPurchasingActor('kontor-inventory-movement-view');
        $this->validateInventoryPurchasingPage($limit);
        $module = $this->inventoryPurchasingModule('KontorInventory');
        $rows = $module->movementRepository()->recentForOrganization($this->inventoryPurchasingOrganization()->uid->toString(), $limit);
        return ['items' => array_map(static fn (InventoryMovement $movement): array => [
            'uid' => $movement->uid->toString(), 'type' => $movement->movementType, 'itemUid' => $movement->itemUid,
            'sourceWarehouseUid' => $movement->sourceWarehouseUid, 'destinationWarehouseUid' => $movement->destinationWarehouseUid,
            'quantity' => $movement->quantity, 'unitCode' => $movement->unitCode, 'occurredAt' => $movement->occurredAt->format(DATE_ATOM),
        ], $rows)];
    }

    public function mcpPurchasingSuppliersList(int $limit = 20, int $offset = 0): array
    {
        $this->requireInventoryPurchasingActor('kontor-purchasing-supplier-view');
        $this->validateInventoryPurchasingPage($limit, $offset);
        $module = $this->inventoryPurchasingModule('KontorPurchasing');
        $rows = $module->supplierRepository()->forOrganization($this->inventoryPurchasingOrganization()->uid->toString(), limit: $limit, offset: $offset);
        return ['items' => array_map(static fn (Supplier $supplier): array => [
            'uid' => $supplier->uid->toString(), 'code' => $supplier->code, 'name' => $supplier->legalName,
            'currencyCode' => $supplier->currencyCode, 'paymentTermsDays' => $supplier->paymentTermsDays, 'status' => $supplier->status,
        ], $rows)];
    }

    public function mcpPurchaseOrdersList(int $limit = 20): array
    {
        $actor = $this->requireInventoryPurchasingActor('kontor-purchasing-po-view');
        $this->validateInventoryPurchasingPage($limit);
        $module = $this->inventoryPurchasingModule('KontorPurchasing');
        $rows = $module->purchaseOrderRepository()->forOrganization($this->inventoryPurchasingOrganization()->uid->toString(), $limit);
        return ['items' => array_map(fn (PurchaseOrder $order): array => $this->purchaseOrderSummary($order, $actor), $rows)];
    }

    public function mcpPurchaseOrderGet(string $uid): array
    {
        $actor = $this->requireInventoryPurchasingActor('kontor-purchasing-po-view');
        $this->validateInventoryPurchasingUid($uid);
        $module = $this->inventoryPurchasingModule('KontorPurchasing');
        $organizationUid = $this->inventoryPurchasingOrganization()->uid->toString();
        $order = $module->purchaseOrderRepository()->find($uid);
        if ($order === null || !hash_equals($organizationUid, $order->organizationId)) {
            throw new Wire404Exception('Purchase order was not found.');
        }
        $lines = $module->documentLineRepository()->forDocument('purchase_order', $uid, $organizationUid, 51);
        return $this->purchaseOrderSummary($order, $actor) + [
            'lines' => array_map(static fn (DocumentLine $line): array => [
                'itemUid' => $line->itemUid, 'title' => $line->title, 'quantity' => $line->quantity,
                'unitCode' => $line->unitCode, 'unitPriceMinor' => $line->unitPrice->amountMinor(),
            ], array_slice($lines, 0, 50)),
            'moreLines' => count($lines) > 50,
        ];
    }

    public function mcpPurchaseOrderCreateDraft(
        string $supplier_uid,
        string $warehouse_uid,
        string $currency_code,
        array $lines,
        string $idempotency_key,
        ?string $expected_date = null,
    ): array {
        $actor = $this->requireInventoryPurchasingActor('kontor-purchasing-po-create', 'kontor-purchasing-supplier-view');
        $this->validateInventoryPurchasingUid($supplier_uid);
        $this->validateInventoryPurchasingUid($warehouse_uid);
        if (preg_match('/^[A-Z]{3}$/D', $currency_code) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key) !== 1) {
            throw new WireException('Use an uppercase currency code and a valid idempotency key.');
        }
        $date = $expected_date === null ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $expected_date);
        if ($expected_date !== null && ($date === false || $date?->format('Y-m-d') !== $expected_date)) {
            throw new WireException('Use a valid expected date in YYYY-MM-DD format.');
        }
        $lines = $this->normalizePurchaseOrderLines($lines);
        $organization = $this->inventoryPurchasingOrganization();
        $module = $this->inventoryPurchasingModule('KontorPurchasing');
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $pdo = $container->get(PDO::class);
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $organizationUid = $organization->uid->toString();
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $receipt = $idempotency->remember(
                $organizationUid,
                'mcp:purchase-draft:' . $actor->id . ':' . hash('sha256', $idempotency_key),
                [$supplier_uid, $warehouse_uid, $currency_code, $lines, $expected_date],
                function () use ($actor, $module, $container, $organization, $organizationUid, $supplier_uid, $warehouse_uid, $currency_code, $lines, $date): array {
                    $supplier = $module->supplierRepository()->find($supplier_uid, activeOnly: true);
                    if ($supplier === null || !hash_equals($organizationUid, $supplier->organizationId)) {
                        throw new Wire404Exception('Active supplier was not found.');
                    }
                    $warehouse = $this->inventoryPurchasingModule('KontorInventory')->warehouseRepository()->find($warehouse_uid);
                    if ($warehouse === null || !$warehouse->isActive() || !hash_equals($organizationUid, $warehouse->organizationId)) {
                        throw new Wire404Exception('Active warehouse was not found.');
                    }
                    $catalog = $this->inventoryPurchasingModule('KontorCatalog')->itemRepository();
                    $order = PurchaseOrder::create($organizationUid, $supplier_uid, $currency_code, $warehouse_uid, $date);
                    $uid = $order->uid->toString();
                    $documents = [];
                    foreach ($lines as $index => $line) {
                        $item = $catalog->findActiveStockItem($organizationUid, $line['item_uid']);
                        if ($item === null) {
                            throw new Wire404Exception('Active inventory item was not found.');
                        }
                        $documents[] = DocumentLine::create($organizationUid, 'purchase_order', $uid,
                            $item->titleIn($organization->defaultLanguage) ?? $item->uid->toString(), $line['quantity'],
                            Money::ofMinor($line['unit_price_minor'], $currency_code), itemUid: $line['item_uid'],
                            itemType: 'product', unitCode: $item->unitCode, sortOrder: $index);
                    }
                    $order->applyTotalsFromLines($documents);
                    $module->purchaseOrderRepository()->save($order);
                    foreach ($documents as $document) {
                        $module->documentLineRepository()->save($document);
                    }
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organizationUid),
                        'purchasing', 'purchase_order', $uid, 'created', 'user', (string) $actor->id,
                        metadata: ['transport' => 'mcp'],
                    );
                    return ['status' => 201, 'body' => ['uid' => $uid, 'status' => 'draft']];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $receipt->body;
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

    private function normalizePurchaseOrderLines(array $lines): array
    {
        if (!array_is_list($lines) || $lines === [] || count($lines) > 25) {
            throw new WireException('Provide 1 to 25 purchase-order lines.');
        }
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line) || count($line) !== 3 || !isset($line['item_uid'], $line['quantity'], $line['unit_price_minor'])
                || !is_string($line['item_uid']) || (!is_int($line['quantity']) && !is_float($line['quantity']))
                || !is_int($line['unit_price_minor'])) {
                throw new WireException('Each line requires an item UID, quantity and unit price in minor units.');
            }
            $this->validateInventoryPurchasingUid($line['item_uid']);
            $quantity = (float) $line['quantity'];
            $price = $line['unit_price_minor'];
            if (!is_finite($quantity) || $quantity < 0.000001 || $quantity > 1000000 || round($quantity, 6) !== $quantity
                || $price < 0 || $price > 1000000000) {
                throw new WireException('Invalid purchase-order line quantity or unit price.');
            }
            $normalized[] = ['item_uid' => $line['item_uid'], 'quantity' => $quantity, 'unit_price_minor' => $price];
        }
        return $normalized;
    }

    private function purchaseOrderSummary(PurchaseOrder $order, User $actor): array
    {
        $data = [
            'uid' => $order->uid->toString(), 'number' => $order->number, 'warehouseUid' => $order->warehouseUid,
            'status' => $order->status, 'expectedDate' => $order->expectedDate?->format('Y-m-d'),
            'totalMinor' => $order->total->amountMinor(), 'currencyCode' => $order->currencyCode,
        ];
        if ($actor->isSuperuser() || $actor->hasPermission('kontor-purchasing-supplier-view')) {
            $data['supplierUid'] = $order->supplierUid;
        }
        return $data;
    }

    private function requireInventoryPurchasingActor(string ...$permissions): User
    {
        $actor = $this->mcpActor();
        if ($actor === null) {
            throw new WirePermissionException('An authenticated Kontor MCP user is required.');
        }
        foreach ($permissions as $permission) {
            if (!$actor->isSuperuser() && !$actor->hasPermission($permission)) {
                throw new WirePermissionException('The linked user cannot perform this inventory or purchasing operation.');
            }
        }
        return $actor;
    }

    private function inventoryPurchasingModule(string $name): object
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled($name)) {
            throw new Wire404Exception('Required Kontor component is unavailable.');
        }
        return $modules->get($name);
    }

    private function inventoryPurchasingOrganization(): Organization
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
    }

    private function validateInventoryPurchasingPage(int $limit, int $offset = 0): void
    {
        if ($limit < 1 || $limit > 50 || $offset < 0 || $offset > 10000) {
            throw new WireException('Invalid limit or offset.');
        }
    }

    private function validateInventoryPurchasingUid(string $uid): void
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('A valid record UID is required.');
        }
    }
}
