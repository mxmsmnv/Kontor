<?php

declare(strict_types=1);

namespace Kontor\Search\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors the other packages' own DatabaseTestCase (kontor.md section 7:
 * each component's tests are self-contained). Needs Core's organizations
 * table since SqlFullTextSearchProvider resolves an organization uid to
 * its internal id via OrganizationRepository.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected \PDO $pdo;

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
        $runner->run([new Migration0001CreateOrganizationsTable()]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
    }

    private function dropTables(): void
    {
        foreach (['kontor_search_test_articles', 'kontor_organizations', 'kontor_migrations'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
