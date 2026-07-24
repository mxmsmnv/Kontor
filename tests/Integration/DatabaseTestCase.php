<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0002CreateComponentsTable;
use Kontor\Core\Migrations\Migration0003CreateAuditEventsTable;
use Kontor\Core\Migrations\Migration0004CreateSequencesTable;
use Kontor\Core\Migrations\Migration0005CreateExtensionsTable;
use Kontor\Core\Migrations\Migration0006CreateRelationsTable;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that need a real MySQL/MariaDB connection, per kontor.md
 * section 10.1. There is no fake in-memory substitute here: the core
 * migrations use MySQL-specific DDL (JSON columns, `ON DUPLICATE KEY
 * UPDATE`), so SQLite would validate a different schema than production.
 *
 * Configure via environment variables and run for real:
 *   KONTOR_TEST_DB_DSN, KONTOR_TEST_DB_USER, KONTOR_TEST_DB_PASS
 * e.g. with the bundled docker-compose.test.yml:
 *   docker compose -f docker-compose.test.yml up -d
 *   KONTOR_TEST_DB_DSN="mysql:host=127.0.0.1;port=3399;dbname=kontor_test" \
 *   KONTOR_TEST_DB_USER=kontor KONTOR_TEST_DB_PASS=kontor \
 *   vendor/bin/phpunit --testsuite integration,migration
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
                'instance to run this test. See docker-compose.test.yml.'
            );
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->dropCoreTables();

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateOrganizationsTable(),
            new Migration0002CreateComponentsTable(),
            new Migration0003CreateAuditEventsTable(),
            new Migration0004CreateSequencesTable(),
            new Migration0005CreateExtensionsTable(),
            new Migration0006CreateRelationsTable(),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropCoreTables();
        }
    }

    private function dropCoreTables(): void
    {
        foreach (
            [
                'kontor_relations',
                'kontor_extensions',
                'kontor_sequences',
                'kontor_audit_events',
                'kontor_components',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
