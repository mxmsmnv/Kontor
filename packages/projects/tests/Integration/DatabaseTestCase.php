<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Invoices\Migrations\Migration0001CreateInvoicesTable;
use Kontor\Projects\Migrations\Migration0001CreateProjectsTable;
use Kontor\Projects\Migrations\Migration0002CreateProjectMilestonesTable;
use Kontor\Projects\Migrations\Migration0003CreateTimeEntriesTable;
use Kontor\Projects\Migrations\Migration0004CreateBillableItemsTable;
use Kontor\Sales\Migrations\Migration0003CreateDocumentLinesTable;
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
            new Migration0003CreateDocumentLinesTable(),
            new Migration0001CreateInvoicesTable(),
            new Migration0001CreateProjectsTable(),
            new Migration0002CreateProjectMilestonesTable(),
            new Migration0003CreateTimeEntriesTable(),
            new Migration0004CreateBillableItemsTable(),
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
                'kontor_project_billable_items',
                'kontor_project_time_entries',
                'kontor_project_milestones',
                'kontor_projects',
                'kontor_invoices',
                'kontor_document_lines',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
