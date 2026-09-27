<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Database;

use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Inventory\Infrastructure\Persistence\BalanceRepository;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use PHPUnit\Framework\TestCase;

final class SQLiteLockingPathsTest extends TestCase
{
    private \PDO $pdo;
    private OrganizationRepository $organizations;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE kontor_organizations (id INTEGER PRIMARY KEY, uid TEXT NOT NULL UNIQUE)');
        $this->pdo->exec("INSERT INTO kontor_organizations (id, uid) VALUES (1, 'org_test')");
        $this->organizations = new OrganizationRepository($this->pdo);
    }

    public function testSequenceAllocationUsesSqliteSafeWriteLocking(): void
    {
        $this->pdo->exec(
            'CREATE TABLE kontor_sequences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                component TEXT NOT NULL,
                sequence_key TEXT NOT NULL,
                prefix TEXT NULL,
                suffix TEXT NULL,
                next_number INTEGER NOT NULL,
                padding INTEGER NOT NULL,
                reset_policy TEXT NOT NULL,
                reset_marker TEXT NULL,
                updated_at TEXT NOT NULL,
                version INTEGER NOT NULL,
                UNIQUE (organization_id, component, sequence_key)
            )'
        );
        $sequences = new SequenceService($this->pdo, $this->organizations);

        self::assertSame('INV-0001', $sequences->next('org_test', 'sales', 'invoice', 'INV-', null, 4));
        self::assertSame('INV-0002', $sequences->next('org_test', 'sales', 'invoice'));
    }

    public function testCatalogBulkLocksAreOmittedOnSqlite(): void
    {
        $itemUid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $categoryUid = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
        $priceListUid = '01ARZ3NDEKTSV4RRFFQ69G5FAX';
        $this->pdo->exec('CREATE TABLE kontor_catalog_items (uid TEXT PRIMARY KEY, organization_id INTEGER NOT NULL, archived_at TEXT NULL, status TEXT NOT NULL, updated_at TEXT NULL)');
        $this->pdo->exec('CREATE TABLE kontor_catalog_categories (uid TEXT PRIMARY KEY, organization_id INTEGER NOT NULL, archived_at TEXT NULL, status TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE kontor_catalog_price_lists (uid TEXT PRIMARY KEY, organization_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO kontor_catalog_items VALUES ('{$itemUid}', 1, NULL, 'active', NULL)");
        $this->pdo->exec("INSERT INTO kontor_catalog_categories VALUES ('{$categoryUid}', 1, NULL, 'active')");
        $this->pdo->exec("INSERT INTO kontor_catalog_price_lists VALUES ('{$priceListUid}', 1, 'active')");

        $items = new CatalogItemRepository($this->pdo, $this->organizations);
        $categories = new CategoryRepository($this->pdo, $this->organizations);
        $priceLists = new PriceListRepository($this->pdo, $this->organizations);

        self::assertSame([$itemUid], $items->archiveMany('org_test', [$itemUid]));
        self::assertSame([$itemUid], $items->deactivateMany('org_test', [$itemUid]));
        self::assertSame([$categoryUid], $categories->archiveMany('org_test', [$categoryUid]));
        self::assertSame([$categoryUid], $categories->deactivateMany('org_test', [$categoryUid]));
        self::assertSame([$priceListUid], $priceLists->deactivateMany('org_test', [$priceListUid]));
    }

    public function testInventoryBalanceLockUsesImmediateTransactionOnSqlite(): void
    {
        $this->pdo->exec(
            'CREATE TABLE kontor_inventory_balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                warehouse_uid TEXT NOT NULL,
                item_uid TEXT NOT NULL,
                quantity_on_hand NUMERIC NOT NULL,
                quantity_reserved NUMERIC NOT NULL,
                quantity_available NUMERIC NOT NULL,
                updated_at TEXT NOT NULL,
                version INTEGER NOT NULL,
                UNIQUE (organization_id, warehouse_uid, item_uid)
            )'
        );
        $balances = new BalanceRepository($this->pdo, $this->organizations);

        self::assertTrue(DatabaseConcurrency::beginWriteTransaction($this->pdo));
        $row = $balances->lockAndGetOrCreate(1, 'warehouse_a', 'item_a');
        $balances->updateQuantities((int) $row['id'], 10.0, 2.0, 8.0);
        $this->pdo->commit();

        self::assertSame(8.0, $balances->find('org_test', 'warehouse_a', 'item_a')->quantityAvailable);
    }

    public function testQueueReservationUsesSqliteSafeSerialization(): void
    {
        $this->pdo->exec(
            'CREATE TABLE kontor_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uid TEXT NOT NULL UNIQUE,
                queue TEXT NOT NULL,
                job_type TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                priority INTEGER NOT NULL,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL,
                max_attempts INTEGER NOT NULL,
                available_at TEXT NOT NULL,
                progress INTEGER NOT NULL,
                idempotency_key TEXT NULL UNIQUE,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                error_message TEXT NULL
            )'
        );
        $jobs = new JobRepository($this->pdo);
        $uid = $jobs->enqueue('default', 'test.job', [], 10, 3, new \DateTimeImmutable('-1 minute'), null);

        $reserved = $jobs->reserveNext('default');

        self::assertSame($uid, $reserved['uid']);
        self::assertSame('reserved', $reserved['status']);
        self::assertSame(1, $reserved['attempts']);
    }
}
