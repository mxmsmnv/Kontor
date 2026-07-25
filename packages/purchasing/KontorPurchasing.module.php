<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Purchasing\Application\GoodsReceiptService;
use Kontor\Purchasing\Application\PurchaseOrderWorkflowService;
use Kontor\Purchasing\Health\PurchasingHealthCheck;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptLineRepository;
use Kontor\Purchasing\Infrastructure\Persistence\GoodsReceiptRepository;
use Kontor\Purchasing\Infrastructure\Persistence\PurchaseOrderRepository;
use Kontor\Purchasing\Infrastructure\Persistence\SupplierRepository;
use Kontor\Purchasing\Migrations\Migration0001CreateSuppliersTable;
use Kontor\Purchasing\Migrations\Migration0002CreatePurchaseOrdersTable;
use Kontor\Purchasing\Migrations\Migration0003CreateGoodsReceiptsTable;
use Kontor\Purchasing\Migrations\Migration0004CreateGoodsReceiptLinesTable;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

/**
 * KontorPurchasing bootstrap module (kontor.md Substage 6.2). Requires
 * both KontorSales (reuses its document lines table/classes directly) and
 * KontorInventory (the "inventory integration" milestone: goods receipt
 * actually moves warehouse stock via InventoryMovementService).
 */
class KontorPurchasing extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Purchasing',
            'summary' => 'Suppliers, purchase orders, goods receipt, inventory integration.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorPurchasing',
            'icon' => 'truck',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorSales', 'KontorInventory'],
            'permissions' => [
                'kontor-purchasing-supplier-view' => 'View suppliers',
                'kontor-purchasing-supplier-create' => 'Create suppliers',
                'kontor-purchasing-supplier-edit' => 'Edit suppliers',
                'kontor-purchasing-supplier-archive' => 'Archive suppliers',
                'kontor-purchasing-po-view' => 'View purchase orders',
                'kontor-purchasing-po-create' => 'Create purchase orders',
                'kontor-purchasing-po-edit-draft' => 'Edit draft purchase orders',
                'kontor-purchasing-po-issue' => 'Issue purchase orders',
                'kontor-purchasing-po-cancel' => 'Cancel purchase orders',
                'kontor-purchasing-receipt-create' => 'Record goods receipts',
            ],
        ];
    }

    private ?SupplierRepository $supplierRepository = null;
    private ?PurchaseOrderRepository $purchaseOrderRepository = null;
    private ?DocumentLineRepository $documentLineRepository = null;
    private ?GoodsReceiptRepository $receiptRepository = null;
    private ?GoodsReceiptLineRepository $receiptLineRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorPurchasing', $language, $strings);
        }
    }

    public function supplierRepository(): SupplierRepository
    {
        return $this->supplierRepository ??= new SupplierRepository($this->pdo(), $this->organizations());
    }

    public function purchaseOrderRepository(): PurchaseOrderRepository
    {
        return $this->purchaseOrderRepository ??= new PurchaseOrderRepository($this->pdo(), $this->organizations());
    }

    public function documentLineRepository(): DocumentLineRepository
    {
        return $this->documentLineRepository ??= new DocumentLineRepository($this->pdo(), $this->organizations());
    }

    public function receiptRepository(): GoodsReceiptRepository
    {
        return $this->receiptRepository ??= new GoodsReceiptRepository($this->pdo(), $this->organizations());
    }

    public function receiptLineRepository(): GoodsReceiptLineRepository
    {
        return $this->receiptLineRepository ??= new GoodsReceiptLineRepository($this->pdo(), $this->organizations());
    }

    public function purchaseOrderWorkflow(): PurchaseOrderWorkflowService
    {
        return new PurchaseOrderWorkflowService($this->purchaseOrderRepository(), $this->documentLineRepository(), $this->sequences());
    }

    public function goodsReceipt(): GoodsReceiptService
    {
        /** @var KontorInventory $inventory */
        $inventory = $this->wire()->modules->get('KontorInventory');

        return new GoodsReceiptService(
            $this->pdo(),
            $this->purchaseOrderRepository(),
            $this->documentLineRepository(),
            $this->receiptRepository(),
            $this->receiptLineRepository(),
            $inventory->movements(),
        );
    }

    public function healthCheck(): PurchasingHealthCheck
    {
        return new PurchasingHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function sequences(): SequenceService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(SequenceService::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateSuppliersTable(),
            new Migration0002CreatePurchaseOrdersTable(),
            new Migration0003CreateGoodsReceiptsTable(),
            new Migration0004CreateGoodsReceiptLinesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('purchasing', self::getModuleInfo()['version'], 'purchasing');
        $components->enable('purchasing');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Purchasing module removed. Supplier and purchase order data was kept intact.'));
    }
}
