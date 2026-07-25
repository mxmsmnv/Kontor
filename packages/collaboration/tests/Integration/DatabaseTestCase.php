<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Migrations\Migration0001CreateNotesTable;
use Kontor\Collaboration\Migrations\Migration0002CreateCommentsTable;
use Kontor\Collaboration\Migrations\Migration0003CreateMentionsTable;
use Kontor\Collaboration\Migrations\Migration0004CreateFollowersTable;
use Kontor\Collaboration\Migrations\Migration0005CreateUnreadStatesTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
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
            new Migration0001CreateNotesTable(),
            new Migration0002CreateCommentsTable(),
            new Migration0003CreateMentionsTable(),
            new Migration0004CreateFollowersTable(),
            new Migration0005CreateUnreadStatesTable(),
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
                'kontor_unread_states',
                'kontor_followers',
                'kontor_mentions',
                'kontor_comments',
                'kontor_notes',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
