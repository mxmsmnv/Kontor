<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Database;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Database\TranslatingPDO;
use PHPUnit\Framework\TestCase;

final class DatabaseConcurrencyTest extends TestCase
{
    public function testSqliteUsesImmediateTransactionWithoutRowLockClause(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kontor-sqlite-lock-');
        self::assertNotFalse($path);

        try {
            $first = new \PDO('sqlite:' . $path);
            $second = new \PDO('sqlite:' . $path);
            foreach ([$first, $second] as $pdo) {
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->exec('PRAGMA busy_timeout = 0');
            }
            $first->exec('CREATE TABLE rows (id INTEGER PRIMARY KEY, value INTEGER NOT NULL)');

            self::assertTrue(DatabaseConcurrency::beginWriteTransaction($first));
            self::assertTrue($first->inTransaction());
            self::assertSame('', DatabaseConcurrency::forUpdate($first));

            $this->expectException(\PDOException::class);
            $second->exec('INSERT INTO rows (value) VALUES (1)');
        } finally {
            if (isset($first) && $first->inTransaction()) {
                $first->rollBack();
            }
            @unlink($path);
        }
    }

    public function testProcessWirePostgresqlWrapperKeepsSkipLockedClause(): void
    {
        $pdo = TranslatingPDO::wrap(new ConcurrencyPgsqlWrapper());

        self::assertSame(' FOR UPDATE', DatabaseConcurrency::forUpdate($pdo));
        self::assertSame(' FOR UPDATE SKIP LOCKED', DatabaseConcurrency::forUpdate($pdo, true));
    }
}

final class ConcurrencyPgsqlWrapper
{
    public function dialect(): object
    {
        return new class {
            public function name(): string
            {
                return 'pgsql';
            }
        };
    }
}
