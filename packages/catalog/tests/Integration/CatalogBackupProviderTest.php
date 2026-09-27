<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Backup\CatalogBackupProvider;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\SDK\ValueObjects\Money;

final class CatalogBackupProviderTest extends DatabaseTestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupRoot = sys_get_temp_dir() . '/kontor-catalog-backup-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->backupRoot ?? '');
        parent::tearDown();
    }

    public function test_verified_backup_restores_all_catalog_tables(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $categories = new CategoryRepository($this->pdo, $organizations);
        $items = new CatalogItemRepository($this->pdo, $organizations);
        $priceLists = new PriceListRepository($this->pdo, $organizations);
        $prices = new PriceRepository($this->pdo);
        $category = Category::create($this->organizationUid, ['en' => 'Equipment']);
        $categories->save($category);
        $item = CatalogItem::create($this->organizationUid, ['en' => 'Field kit'], sku: 'KIT-001');
        $item->categoryUid = $category->uid->toString();
        $items->save($item);
        $priceList = PriceList::create($this->organizationUid, 'Retail', 'EUR');
        $priceLists->save($priceList);
        $prices->save(new PriceListEntry(
            $priceList->uid->toString(),
            $item->uid->toString(),
            Money::ofMinor(9900, 'EUR'),
            1,
            null,
            null,
        ));

        $registry = new BackupProviderRegistry();
        $registry->register('catalog', new CatalogBackupProvider($this->pdo, $organizations));
        $manager = new BackupManager($registry, $this->backupRoot);
        $backup = $manager->create('catalog', 'snapshot', $this->organizationUid, 'integration test');

        $this->assertTrue($backup->verified);
        $this->assertSame(4, $backup->itemCount);

        $this->pdo->exec('DELETE FROM kontor_catalog_prices');
        $this->pdo->exec('DELETE FROM kontor_catalog_price_lists');
        $this->pdo->exec('DELETE FROM kontor_catalog_items');
        $this->pdo->exec('DELETE FROM kontor_catalog_categories');
        $result = $manager->restore($backup->path, 'catalog', $this->organizationUid);

        $this->assertTrue($result->success);
        $this->assertSame(4, $result->restoredCount);
        $this->assertSame('Equipment', $categories->require($category->uid->toString())->nameIn('en'));
        $this->assertSame('KIT-001', $items->require($item->uid->toString())->sku);
        $this->assertSame('Retail', $priceLists->require($priceList->uid->toString())->name);
        $this->assertSame(
            9900,
            $prices->forItem($priceList->uid->toString(), $item->uid->toString())[0]->price->amountMinor(),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
