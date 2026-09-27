<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Queue\Migrations\Migration0001CreateJobsTable;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors kontor/core's own DatabaseTestCase (each component's tests are
 * self-contained per kontor.md section 7 — a package's dev dependencies
 * are not visible to sibling packages). Configure with the same
 * environment variables and docker-compose.test.yml as kontor/core.
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

        $this->pdo->exec('DROP TABLE IF EXISTS kontor_jobs');
        $this->pdo->exec('DROP TABLE IF EXISTS kontor_migrations');

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run([new Migration0001CreateJobsTable()]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_jobs');
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_migrations');
        }
    }
}
