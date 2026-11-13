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
        $key = $this->idempotencySchema();
        $quantity = ['type' => 'number', 'minimum' => 0.000001, 'maximum' => 1000000];

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
            if ($modules->isInstalled('KontorCatalog')) {
                $tools[] = $this->tool('kontor_inventory_movement_record', 'Record a reviewed stock movement',
                    'Apply a stock movement after user review. Inventory units come from the catalog item.',
                    $this->mcpInventoryMovementRecord(...), 'draft', [
                        'action' => ['type' => 'string', 'enum' => ['receive', 'transfer', 'adjust_increase', 'adjust_decrease', 'reserve', 'release']],
                        'warehouse_uid' => $uid,
                        'item_uid' => $uid,
                        'quantity' => $quantity,
                        'idempotency_key' => $key,
                        'confirmation' => ['type' => 'string', 'const' => 'APPLY_INVENTORY_MOVEMENT'],
                        'destination_warehouse_uid' => $uid,
                        'reason' => ['type' => 'string', 'maxLength' => 255],
                    ], ['action', 'warehouse_uid', 'item_uid', 'quantity', 'idempotency_key', 'confirmation'], destructive: true);
            }
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

        $tools[] = $this->tool('kontor_purchase_order_action', 'Issue or cancel a reviewed purchase order',
            'Apply the reviewed issue or cancel action to a purchase order. Issuing assigns an order number.',
            $this->mcpPurchaseOrderAction(...), 'draft', [
                'uid' => $uid,
                'action' => ['type' => 'string', 'enum' => ['issue', 'cancel']],
                'idempotency_key' => $key,
                'confirmation' => ['type' => 'string', 'const' => 'APPLY_PURCHASE_ORDER_ACTION'],
            ], ['uid', 'action', 'idempotency_key', 'confirmation'], destructive: true);
        if ($modules->isInstalled('KontorInventory')) {
            $tools[] = $this->tool('kontor_purchase_order_receive', 'Record a reviewed goods receipt',
                'Record received quantities against purchase-order line UIDs and add the received stock to an active warehouse.',
                $this->mcpPurchaseOrderReceive(...), 'draft', [
                    'uid' => $uid,
                    'warehouse_uid' => $uid,
                    'lines' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 25, 'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['line_uid', 'quantity'],
                        'properties' => ['line_uid' => $uid, 'quantity' => $quantity],
                    ]],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'RECORD_PURCHASE_RECEIPT'],
                ], ['uid', 'warehouse_uid', 'lines', 'idempotency_key', 'confirmation'], destructive: true);
        }

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
                'uid' => $line->uid->toString(), 'itemUid' => $line->itemUid, 'title' => $line->title, 'quantity' => $line->quantity,
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

    public function mcpPurchaseOrderAction(string $uid, string $action, string $idempotency_key, string $confirmation): array
    {
        if ($confirmation !== 'APPLY_PURCHASE_ORDER_ACTION' || !in_array($action, ['issue', 'cancel'], true)) {
            throw new WireException('Confirm the purchase order issue or cancel action.');
        }
        $actor = $this->requireInventoryPurchasingActor('kontor-purchasing-po-' . $action);
        $this->validateInventoryPurchasingUid($uid);
        $purchasing = $this->inventoryPurchasingModule('KontorPurchasing');
        return $this->writeReviewedStockOperation(
            $actor, 'purchasing', $idempotency_key, [$uid, $action],
            static function (string $organizationUid) use ($purchasing, $uid, $action): array {
                $order = $purchasing->purchaseOrderRepository()->findForUpdate($uid, $organizationUid);
                if ($order === null) {
                    throw new Wire404Exception('Purchase order was not found.');
                }
                if ($action === 'issue' && !$order->isDraft() || $action === 'cancel' && !$order->isCancellable()) {
                    throw new WireException('Purchase order status does not allow this action.');
                }
                $workflow = $purchasing->purchaseOrderWorkflow();
                $updated = $action === 'issue' ? $workflow->issue($uid, $organizationUid) : $workflow->cancel($uid, $organizationUid);
                return [
                    'purchase_order', $uid, $action === 'issue' ? 'issued' : 'cancelled',
                    ['uid' => $uid, 'number' => $updated->number, 'status' => $updated->status],
                ];
            },
        );
    }

    public function mcpPurchaseOrderReceive(
        string $uid,
        string $warehouse_uid,
        array $lines,
        string $idempotency_key,
        string $confirmation,
    ): array {
        $actor = $this->requireInventoryPurchasingActor('kontor-purchasing-receipt-create');
        if ($confirmation !== 'RECORD_PURCHASE_RECEIPT') {
            throw new WireException('Confirm the goods receipt and stock change.');
        }
        $this->validateInventoryPurchasingUid($uid);
        $this->validateInventoryPurchasingUid($warehouse_uid);
        if (!array_is_list($lines) || $lines === [] || count($lines) > 25) {
            throw new WireException('Provide 1 to 25 purchase-order receipt lines.');
        }
        $requested = [];
        foreach ($lines as $line) {
            if (!is_array($line) || count($line) !== 2 || !isset($line['line_uid'], $line['quantity']) || !is_string($line['line_uid'])) {
                throw new WireException('Each receipt line requires a line UID and quantity.');
            }
            $lineUid = $line['line_uid'];
            $this->validateInventoryPurchasingUid($lineUid);
            if (isset($requested[$lineUid])) {
                throw new WireException('Supply each purchase-order line once.');
            }
            $requested[$lineUid] = ['poLineUid' => $lineUid, 'quantity' => $this->validatedStockQuantity($line['quantity'])];
        }
        ksort($requested);
        $requested = array_values($requested);

        $purchasing = $this->inventoryPurchasingModule('KontorPurchasing');
        $inventory = $this->inventoryPurchasingModule('KontorInventory');
        return $this->writeReviewedStockOperation(
            $actor, 'purchasing', $idempotency_key, [$uid, 'receive', $warehouse_uid, $requested],
            function (string $organizationUid) use ($actor, $purchasing, $inventory, $uid, $warehouse_uid, $requested): array {
                $this->requireReviewedWarehouse($inventory, $organizationUid, $warehouse_uid);
                $order = $purchasing->purchaseOrderRepository()->findForUpdate($uid, $organizationUid);
                if ($order === null) {
                    throw new Wire404Exception('Purchase order was not found.');
                }
                if (!$order->isReceivable()) {
                    throw new WireException('Purchase order status does not allow receiving goods.');
                }
                $receipt = $purchasing->goodsReceipt()->receive($organizationUid, $uid, $warehouse_uid, $requested, (int) $actor->id);
                $receiptUid = $receipt->uid->toString();
                return ['goods_receipt', $receiptUid, 'received', ['uid' => $receiptUid, 'purchaseOrderUid' => $uid]];
            },
        );
    }

    public function mcpInventoryMovementRecord(
        string $action,
        string $warehouse_uid,
        string $item_uid,
        float $quantity,
        string $idempotency_key,
        string $confirmation,
        ?string $destination_warehouse_uid = null,
        ?string $reason = null,
    ): array {
        $permission = match ($action) {
            'receive', 'transfer', 'reserve', 'release' => $action,
            'adjust_increase', 'adjust_decrease' => 'adjust',
            default => throw new WireException('Unknown stock movement.'),
        };
        $actor = $this->requireInventoryPurchasingActor('kontor-inventory-' . $permission);
        if ($confirmation !== 'APPLY_INVENTORY_MOVEMENT') {
            throw new WireException('Confirm the stock movement.');
        }
        $this->validateInventoryPurchasingUid($warehouse_uid);
        $this->validateInventoryPurchasingUid($item_uid);
        $quantity = $this->validatedStockQuantity($quantity);
        if (($action === 'transfer') !== ($destination_warehouse_uid !== null)) {
            throw new WireException('Provide a destination warehouse only for transfers.');
        }
        if ($destination_warehouse_uid !== null) {
            $this->validateInventoryPurchasingUid($destination_warehouse_uid);
            if ($destination_warehouse_uid === $warehouse_uid) {
                throw new WireException('Transfer to a different warehouse.');
            }
        }
        $reason = $reason === null ? null : trim(strip_tags($reason));
        if (($reason !== null && mb_strlen($reason) > 255) || ($permission === 'adjust' && ($reason === null || $reason === ''))) {
            throw new WireException('Stock adjustments need a reason of up to 255 characters.');
        }
        $inventory = $this->inventoryPurchasingModule('KontorInventory');
        $catalog = $this->inventoryPurchasingModule('KontorCatalog');
        return $this->writeReviewedStockOperation(
            $actor, 'inventory', $idempotency_key,
            [$action, $warehouse_uid, $item_uid, $quantity, $destination_warehouse_uid, $reason],
            function (string $organizationUid) use ($inventory, $catalog, $actor, $action, $warehouse_uid, $item_uid, $quantity, $destination_warehouse_uid, $reason): array {
                $this->requireReviewedWarehouse($inventory, $organizationUid, $warehouse_uid);
                if ($destination_warehouse_uid !== null) {
                    $this->requireReviewedWarehouse($inventory, $organizationUid, $destination_warehouse_uid);
                }
                $item = $catalog->itemRepository()->findActiveStockItem($organizationUid, $item_uid);
                if ($item === null) {
                    throw new Wire404Exception('Active inventory item was not found.');
                }
                $service = $inventory->movements();
                $args = [$organizationUid, $warehouse_uid, $item_uid, $quantity];
                $unit = $item->unitCode;
                $actorId = (int) $actor->id;
                $movement = match ($action) {
                    'receive' => $service->receive(...$args, unitCode: $unit, reason: $reason, createdBy: $actorId),
                    'transfer' => $service->transfer($organizationUid, $warehouse_uid, $destination_warehouse_uid, $item_uid, $quantity,
                        unitCode: $unit, reason: $reason, createdBy: $actorId),
                    'adjust_increase' => $service->adjustIncrease(...$args, reason: $reason, unitCode: $unit, createdBy: $actorId),
                    'adjust_decrease' => $service->adjustDecrease(...$args, reason: $reason, unitCode: $unit,
                        allowNegative: $actor->isSuperuser() || $actor->hasPermission('kontor-inventory-negative-stock-override'), createdBy: $actorId),
                    'reserve' => $service->reserve(...$args, unitCode: $unit, createdBy: $actorId),
                    'release' => $service->release(...$args, unitCode: $unit, createdBy: $actorId),
                };
                $uid = $movement->uid->toString();
                return ['movement', $uid, $action, ['uid' => $uid, 'action' => $action]];
            },
        );
    }

    private function validatedStockQuantity(mixed $quantity): float
    {
        if ((!is_int($quantity) && !is_float($quantity)) || !is_finite($quantity)
            || $quantity < 0.000001 || $quantity > 1000000 || round($quantity, 6) !== (float) $quantity) {
            throw new WireException('Use a positive quantity with at most six decimal places.');
        }
        return (float) $quantity;
    }

    private function requireReviewedWarehouse(object $inventory, string $organizationUid, string $uid): void
    {
        $warehouse = $inventory->warehouseRepository()->find($uid);
        if ($warehouse === null || !$warehouse->isActive() || !hash_equals($organizationUid, $warehouse->organizationId)) {
            throw new Wire404Exception('Active warehouse was not found.');
        }
    }

    private function writeReviewedStockOperation(User $actor, string $component, string $key, array $arguments, callable $operation): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('Use an idempotency key of 8 to 160 safe characters.');
        }
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $organizationUid = $this->inventoryPurchasingOrganization()->uid->toString();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $pdo = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $response = $idempotency->remember(
                $organizationUid,
                'mcp:reviewed:' . $component . ':' . $actor->id . ':' . hash('sha256', $key),
                $arguments,
                static function () use ($operation, $organizationUid, $container, $component, $actor): array {
                    [$entity, $uid, $action, $result] = $operation($organizationUid);
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organizationUid),
                        $component, $entity, $uid, $action, 'user', (string) $actor->id,
                        metadata: ['transport' => 'mcp'],
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
