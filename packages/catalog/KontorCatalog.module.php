<?php

namespace ProcessWire;

use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Health\CatalogHealthCheck;
use Kontor\Catalog\Infrastructure\Backup\CatalogBackupProvider;
use Kontor\Catalog\Infrastructure\Export\ItemExportProvider;
use Kontor\Catalog\Infrastructure\Import\ItemImportProvider;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Catalog\Infrastructure\Search\CatalogItemSearchProvider;
use Kontor\Catalog\Migrations\Migration0001CreateCatalogItemsTable;
use Kontor\Catalog\Migrations\Migration0002CreateCategoriesTable;
use Kontor\Catalog\Migrations\Migration0003CreatePriceListsTable;
use Kontor\Catalog\Migrations\Migration0004CreatePricesTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;

/**
 * KontorCatalog bootstrap module (kontor.md Substage 3.2). Registers
 * import/export providers and a repository (for ImportManager's rollback)
 * into Core's registries — Catalog consumes Core's infrastructure, it
 * doesn't provide a capability of its own, same as KontorContacts.
 */
class KontorCatalog extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Catalog',
            'summary' => 'Items (products and services), categories, price lists, units and tax code references.',
            'version' => '026',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorCatalog',
            'icon' => 'cubes',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorSearch'],
            'permissions' => [
                'kontor-catalog-item-view' => 'View catalog items',
                'kontor-catalog-item-create' => 'Create catalog items',
                'kontor-catalog-item-edit' => 'Edit catalog items',
                'kontor-catalog-item-archive' => 'Archive catalog items',
                'kontor-catalog-category-view' => 'View catalog categories',
                'kontor-catalog-category-create' => 'Create catalog categories',
                'kontor-catalog-category-edit' => 'Edit catalog categories',
                'kontor-catalog-pricelist-view' => 'View price lists',
                'kontor-catalog-pricelist-create' => 'Create price lists',
                'kontor-catalog-pricelist-edit' => 'Edit price lists',
                'kontor-catalog-export' => 'Export catalog items',
            ],
        ];
    }

    private ?CatalogItemRepository $itemRepository = null;
    private ?CategoryRepository $categoryRepository = null;
    private ?PriceListRepository $priceListRepository = null;
    private ?PriceRepository $priceRepository = null;
    private ?PriceListDuplicator $priceListDuplicator = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        /** @var KontorSearch $searchModule */
        $searchModule = $this->wire()->modules->get('KontorSearch');

        $kontor->container()->get(ImportProviderRegistry::class)->register(
            'catalog_item',
            new ItemImportProvider($this->itemRepository())
        );
        $kontor->container()->get(ExportProviderRegistry::class)->register(
            'catalog_item',
            new ItemExportProvider($this->pdo(), $kontor->container()->get(OrganizationRepository::class))
        );
        $kontor->container()->get(RepositoryRegistry::class)->register('catalog_item', $this->itemRepository());
        $searchModule->providerRegistry()->register(new CatalogItemSearchProvider(
            $this->pdo(),
            $kontor->container()->get(OrganizationRepository::class)
        ));

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $kontor->container()->get(BackupProviderRegistry::class)->register(
            'catalog',
            new CatalogBackupProvider($this->pdo(), $kontor->container()->get(OrganizationRepository::class))
        );
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorCatalog', $language, $strings);
        }
    }

    public function itemRepository(): CatalogItemRepository
    {
        return $this->itemRepository ??= new CatalogItemRepository($this->pdo(), $this->organizations());
    }

    public function categoryRepository(): CategoryRepository
    {
        return $this->categoryRepository ??= new CategoryRepository($this->pdo(), $this->organizations());
    }

    public function priceListRepository(): PriceListRepository
    {
        return $this->priceListRepository ??= new PriceListRepository($this->pdo(), $this->organizations());
    }

    public function priceRepository(): PriceRepository
    {
        return $this->priceRepository ??= new PriceRepository($this->pdo());
    }

    public function priceListDuplicator(): PriceListDuplicator
    {
        return $this->priceListDuplicator ??= new PriceListDuplicator(
            $this->pdo(),
            $this->priceListRepository(),
            $this->priceRepository(),
        );
    }

    public function healthCheck(): CatalogHealthCheck
    {
        return new CatalogHealthCheck($this->pdo());
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
            new Migration0001CreateCatalogItemsTable(),
            new Migration0002CreateCategoriesTable(),
            new Migration0003CreatePriceListsTable(),
            new Migration0004CreatePricesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('catalog', self::getModuleInfo()['version'], 'catalog');
        $components->enable('catalog');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Catalog module removed. Catalog data was kept intact.'));
    }
}
