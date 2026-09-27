<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Contacts\Migrations\Migration0002CreateCompaniesTable;
use Kontor\Contacts\Migrations\Migration0003CreateAddressesTable;
use Kontor\Contacts\Migrations\Migration0004CreateContactCompanyTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0005CreateExtensionsTable;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors the other packages' own DatabaseTestCase (kontor.md section 7).
 * Runs Core's organizations + extensions migrations (needed for
 * OrganizationRepository and TagService) plus this component's own four.
 */
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
            new Migration0005CreateExtensionsTable(),
            new Migration0001CreateContactsTable(),
            new Migration0002CreateCompaniesTable(),
            new Migration0003CreateAddressesTable(),
            new Migration0004CreateContactCompanyTable(),
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
                'kontor_contact_company',
                'kontor_addresses',
                'kontor_companies',
                'kontor_contacts',
                'kontor_extensions',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
