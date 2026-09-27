<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Migration;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class MigrationRunnerTest extends DatabaseTestCase
{
    public function test_core_migrations_are_idempotent(): void
    {
        $runner = new MigrationRunner($this->pdo);

        $first = $runner->run([new Migration0007NoopForTest()]);
        $second = $runner->run([new Migration0007NoopForTest()]);

        $this->assertSame('ok', $first['0007_noop_for_test']);
        $this->assertSame('skipped', $second['0007_noop_for_test']);
    }

    public function test_failed_migration_is_recorded_and_stays_retryable(): void
    {
        $runner = new MigrationRunner($this->pdo);

        try {
            $runner->run([new Migration0008FailingForTest()]);
            $this->fail('Expected migration to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('0008_failing_for_test', $e->getMessage());
        }

        $statement = $this->pdo->prepare(
            'SELECT status, error_message FROM kontor_migrations WHERE migration = :migration'
        );
        $statement->execute(['migration' => '0008_failing_for_test']);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('failed', $row['status']);
        $this->assertStringContainsString('boom', $row['error_message']);

        // a failed migration must not be treated as "already executed" — it has to be retryable
        $retry = $runner->run([new Migration0007NoopForTest()]);
        $this->assertSame('ok', $retry['0007_noop_for_test']);

        $this->pdo->exec('DROP TABLE IF EXISTS kontor_never_committed');
    }

    public function test_organizations_table_created_by_the_core_migrations_is_queryable(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_organizations')->fetchColumn();

        $this->assertSame(0, $count);
    }
}

final class Migration0007NoopForTest implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0007_noop_for_test';
    }

    public function up(\PDO $pdo): void
    {
        // Intentionally empty: this fixture exercises migration-ledger
        // idempotency without leaving an unread result set on PDO drivers.
    }

    public function down(\PDO $pdo): void
    {
    }
}

final class Migration0008FailingForTest implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0008_failing_for_test';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE kontor_never_committed (id INT)');
        throw new \RuntimeException('boom');
    }

    public function down(\PDO $pdo): void
    {
    }
}
