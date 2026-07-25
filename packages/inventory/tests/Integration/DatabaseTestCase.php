<?php

declare(strict_types=1);

namespace Kontor\Inventory\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Inventory\Migrations\Migration0001CreateWarehousesTable;
use Kontor\Inventory\Migrations\Migration0002CreateBalancesTable;
use Kontor\Inventory\Migrations\Migration0003CreateMovementsTable;
use Kontor\Inventory\Migrations\Migration0004CreateBarcodesTable;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected \PDO $pdo;
    protected string $organizationUid;

    protected function setUp(): void
    {
        $dsn = getenv('KONTOR_TEST_DB_DSN');

        if ($dsn === false) {
            $this->markTestSkipped(
                'Set KONTOR_TEST_DB_DSN (and _USER/_PASS) to a MySQL/MariaDB '.
                'instance to run this test. See ../../docker-compose.test.yml.'
            );
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->dropTables();

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateWarehousesTable(),
            new Migration0002CreateBalancesTable(),
            new Migration0003CreateMovementsTable(),
            new Migration0004CreateBarcodesTable(),
        ]);

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
    }

    private function dropTables(): void
    {
        foreach (
            [
                'kontor_inventory_barcodes',
                'kontor_inventory_movements',
                'kontor_inventory_balances',
                'kontor_inventory_warehouses',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
