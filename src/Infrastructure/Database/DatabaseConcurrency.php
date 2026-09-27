<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Database;

/**
 * Portable transaction and row-lock primitives for Kontor repositories.
 *
 * MySQL and PostgreSQL provide row-level SELECT locks. SQLite serializes
 * Kontor's short write transactions with BEGIN IMMEDIATE instead, so its
 * SELECT statements must not contain unsupported FOR UPDATE clauses.
 */
final class DatabaseConcurrency
{
    public static function dialectName(\PDO $pdo): string
    {
        if ($pdo instanceof TranslatingPDO) {
            return $pdo->dialectName();
        }

        return strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public static function beginWriteTransaction(\PDO $pdo): bool
    {
        if ($pdo->inTransaction()) {
            return false;
        }

        if (self::dialectName($pdo) === 'sqlite' && !($pdo instanceof TranslatingPDO)) {
            if ($pdo->exec('BEGIN IMMEDIATE') === false) {
                throw new \RuntimeException('Could not start the SQLite immediate write transaction.');
            }

            return true;
        }

        if (!$pdo->beginTransaction()) {
            throw new \RuntimeException('Could not start the database transaction.');
        }

        return true;
    }

    public static function forUpdate(\PDO $pdo, bool $skipLocked = false): string
    {
        if (self::dialectName($pdo) === 'sqlite') {
            return '';
        }

        return ' FOR UPDATE' . ($skipLocked ? ' SKIP LOCKED' : '');
    }
}
