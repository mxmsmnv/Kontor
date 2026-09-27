<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Database;

use Kontor\Core\Infrastructure\Database\TranslatingPDO;
use PHPUnit\Framework\TestCase;

final class TranslatingPDOTest extends TestCase
{
    public function testDelegatesQueriesAndTransactionsToWrapper(): void
    {
        $wrapper = new SQLiteDatabaseWrapper();
        $pdo = TranslatingPDO::wrap($wrapper);

        self::assertInstanceOf(\PDO::class, $pdo);
        self::assertSame($pdo, TranslatingPDO::wrap($wrapper));
        self::assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        self::assertTrue($pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC));
        self::assertSame("'Kontor'", $pdo->quote('Kontor'));
        self::assertIsArray($pdo->errorInfo());
        self::assertTrue($pdo->errorCode() === null || is_string($pdo->errorCode()));

        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO jobs (status) VALUES (:status)');
        $statement->execute(['status' => 'pending']);
        self::assertSame('1', $pdo->lastInsertId());

        self::assertTrue($pdo->beginTransaction());
        self::assertTrue($pdo->inTransaction());
        $row = $pdo->query("SELECT * FROM jobs WHERE status = 'pending' LIMIT 1 FOR UPDATE SKIP LOCKED")
            ->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('pending', $row['status']);
        self::assertTrue($pdo->commit());

        self::assertStringNotContainsString('FOR UPDATE', end($wrapper->queries));
    }

    public function testNativePdoPassesThroughUnchanged(): void
    {
        $pdo = new \PDO('sqlite::memory:');

        self::assertSame($pdo, TranslatingPDO::wrap($pdo));
    }

    public function testPostgresqlUpsertQualifiesSelfReferentialArithmetic(): void
    {
        $wrapper = new RecordingPgsqlDatabaseWrapper();
        $pdo = TranslatingPDO::wrap($wrapper);

        self::assertFalse($pdo->prepare(
            'INSERT INTO kontor_organizations (uid, version) VALUES (:uid, 1) '
            . 'ON DUPLICATE KEY UPDATE uid = VALUES(uid), version = version + 1'
        ));
        self::assertStringContainsString(
            'version = `kontor_organizations`.`version` + 1',
            $wrapper->queries[0]
        );
        self::assertStringContainsString('uid = VALUES(uid)', $wrapper->queries[0]);
    }
}

final class RecordingPgsqlDatabaseWrapper
{
    /** @var string[] */
    public array $queries = [];

    public function dialect(): object
    {
        return new class {
            public function name(): string
            {
                return 'pgsql';
            }
        };
    }

    public function prepare(string $query, array $options = []): false
    {
        $this->queries[] = $query;
        return false;
    }
}

final class SQLiteDatabaseWrapper
{
    private readonly \PDO $pdo;

    /** @var string[] */
    public array $queries = [];

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function dialect(): object
    {
        return new class {
            public function name(): string
            {
                return 'sqlite';
            }
        };
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->queries[] = $query;
        return $this->pdo->prepare($query, $options);
    }

    public function query(string $query): \PDOStatement|false
    {
        $this->queries[] = $query;
        return $this->pdo->query($query);
    }

    public function exec(string $statement): int|false
    {
        $this->queries[] = $statement;
        return $this->pdo->exec($statement);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    public function quote(string $string): string|false
    {
        return $this->pdo->quote($string);
    }

    public function errorCode(): ?string
    {
        return $this->pdo->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }
}
