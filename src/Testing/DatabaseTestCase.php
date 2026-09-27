<?php

declare(strict_types=1);

namespace Kontor\Core\Testing;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PHPUnit\Framework\TestCase;

/**
 * The "testing helpers" milestone (kontor.md Substage 7.4). Every
 * business component built before this substage hand-wrote its own
 * `tests/Integration/DatabaseTestCase.php` — same ~90 lines of
 * boilerplate repeated in `kontor/sales`, `kontor/invoices`,
 * `kontor/payments`, and every other package since: check
 * `KONTOR_TEST_DB_DSN`, skip cleanly if unset, connect, drop tables, run
 * migrations, seed a default organization, and reverse all of that in
 * `tearDown()`.
 *
 * This lives in `kontor/core` rather than `kontor/sdk` because it needs
 * `MigrationRunner`/`OrganizationRepository`, both of which depend on
 * `kontor/sdk` — putting it in the SDK would invert that dependency.
 *
 * Not retrofitted into any already-shipped package's own copy — the same
 * "built once, adopted by whoever wants it next" precedent every other
 * registry/extension point in this monorepo already followed (e.g.
 * `kontor/dashboard`'s `WidgetRegistry`). A future component can extend
 * this directly instead of copying the boilerplate again:
 *
 * ```php
 * final class MyPackageDatabaseTestCase extends DatabaseTestCase
 * {
 *     protected function migrations(): array
 *     {
 *         return [
 *             new Migration0001CreateOrganizationsTable(),
 *             new Migration0001CreateMyTable(),
 *         ];
 *     }
 *
 *     protected function tablesToDrop(): array
 *     {
 *         return ['kontor_my_table', 'kontor_organizations', 'kontor_migrations'];
 *     }
 * }
 * ```
 */
abstract class DatabaseTestCase extends TestCase
{
    protected \PDO $pdo;
    protected string $organizationUid;

    /**
     * @return MigrationInterface[] run in this order, earliest dependency first
     */
    abstract protected function migrations(): array;

    /**
     * @return string[] dropped in this order — children before the
     *                   tables they reference, since MySQL enforces
     *                   foreign-key-free `DROP TABLE IF EXISTS` in
     *                   whatever order it's given
     */
    abstract protected function tablesToDrop(): array;

    /**
     * Override to skip seeding a default organization (rare — most
     * subclasses need one to call `internalIdOf()` against).
     */
    protected function seedDefaultOrganization(): bool
    {
        return true;
    }

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

        $this->dropTables();

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run($this->migrations());

        if ($this->seedDefaultOrganization()) {
            $this->organizationUid = (new OrganizationRepository($this->pdo))
                ->defaultOrganization('US', 'en', 'EUR')
                ->uid
                ->toString();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
    }

    private function dropTables(): void
    {
        foreach ($this->tablesToDrop() as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
