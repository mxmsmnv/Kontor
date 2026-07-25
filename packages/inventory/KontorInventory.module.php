<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Inventory\Application\InventoryMovementService;
use Kontor\Inventory\Health\InventoryHealthCheck;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Inventory\Infrastructure\Persistence\BarcodeRepository;
use Kontor\Inventory\Infrastructure\Persistence\MovementRepository;
use Kontor\Inventory\Infrastructure\Persistence\WarehouseRepository;
use Kontor\Inventory\Migrations\Migration0001CreateWarehousesTable;
use Kontor\Inventory\Migrations\Migration0002CreateBalancesTable;
use Kontor\Inventory\Migrations\Migration0003CreateMovementsTable;
use Kontor\Inventory\Migrations\Migration0004CreateBarcodesTable;

/**
 * KontorInventory bootstrap module (kontor.md Substage 6.1). First
 * component of Stage 6 (Operations). Depends only on kontor/core —
 * item_uid stays a loose reference (kontor.md#10.7), no hard dependency
 * on kontor/catalog.
 */
class KontorInventory extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Inventory',
            'summary' => 'Warehouses, balances, movements, reservations, transfers, barcode support.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorInventory',
            'icon' => 'cubes',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-inventory-stock-view' => 'View stock balances',
                'kontor-inventory-movement-view' => 'View inventory movements',
                'kontor-inventory-receive' => 'Receive stock',
                'kontor-inventory-transfer' => 'Transfer stock between warehouses',
                'kontor-inventory-adjust' => 'Adjust stock',
                'kontor-inventory-reserve' => 'Reserve stock',
                'kontor-inventory-release' => 'Release reserved stock',
                'kontor-inventory-negative-stock-override' => 'Allow adjustments that take stock negative',
                'kontor-inventory-warehouse-admin' => 'Administer warehouses',
            ],
        ];
    }

    private ?WarehouseRepository $warehouseRepository = null;
    private ?BalanceRepository $balanceRepository = null;
    private ?MovementRepository $movementRepository = null;
    private ?BarcodeRepository $barcodeRepository = null;

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
            $translations->register('KontorInventory', $language, $strings);
        }
    }

    public function warehouseRepository(): WarehouseRepository
    {
        return $this->warehouseRepository ??= new WarehouseRepository($this->pdo(), $this->organizations());
    }

    public function balanceRepository(): BalanceRepository
    {
        return $this->balanceRepository ??= new BalanceRepository($this->pdo(), $this->organizations());
    }

    public function movementRepository(): MovementRepository
    {
        return $this->movementRepository ??= new MovementRepository($this->pdo(), $this->organizations());
    }

    public function barcodeRepository(): BarcodeRepository
    {
        return $this->barcodeRepository ??= new BarcodeRepository($this->pdo(), $this->organizations());
    }

    public function movements(): InventoryMovementService
    {
        return new InventoryMovementService(
            $this->pdo(),
            $this->organizations(),
            $this->warehouseRepository(),
            $this->balanceRepository(),
            $this->movementRepository(),
        );
    }

    public function healthCheck(): InventoryHealthCheck
    {
        return new InventoryHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
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
            new Migration0001CreateWarehousesTable(),
            new Migration0002CreateBalancesTable(),
            new Migration0003CreateMovementsTable(),
            new Migration0004CreateBarcodesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('inventory', self::getModuleInfo()['version'], 'inventory');
        $components->enable('inventory');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Inventory module removed. Warehouse and movement data was kept intact.'));
    }
}
