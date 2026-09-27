<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Database;

/**
 * PDO-compatible bridge that keeps Kontor's standalone persistence API while
 * routing ProcessWire runtime queries through WireDatabasePDO.
 *
 * Kontor repositories intentionally accept PDO so they can be tested and used
 * outside ProcessWire. Inside ProcessWire, however, calling database->pdo()
 * bypasses SQL translation and the schema log. This bridge is a PDO subtype,
 * so existing repository contracts remain intact, while all operations used by
 * Kontor delegate to the translating WireDatabasePDO wrapper.
 */
final class TranslatingPDO extends \PDO
{
    /** @var \WeakMap<object, self>|null */
    private static ?\WeakMap $instances = null;

    private function __construct(private readonly object $database)
    {
        // Deliberately do not initialize PDO: every supported operation is
        // delegated to ProcessWire's already configured database wrapper.
    }

    public static function wrap(object $database): \PDO
    {
        if($database instanceof \PDO) {
            return $database;
        }

        self::$instances ??= new \WeakMap();

        return self::$instances[$database] ??= new self($database);
    }

    public function dialectName(): string
    {
        if(method_exists($this->database, 'dialect')) {
            return (string) $this->database->dialect()->name();
        }

        return 'mysql';
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->database->prepare($this->portableSql($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $statement = $this->database->query($this->portableSql($query));
        if($statement !== false && $fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        return $statement;
    }

    public function exec(string $statement): int|false
    {
        return $this->database->exec($this->portableSql($statement));
    }

    public function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->database->commit();
    }

    public function rollBack(): bool
    {
        return $this->database->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->database->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->database->lastInsertId($name);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->database->getAttribute($attribute);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->database->setAttribute($attribute, $value);
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        // WireDatabasePDO performs dialect-aware quoting. Its public contract
        // does not expose PDO's optional type, and Kontor uses string quoting.
        return $this->database->quote($string);
    }

    public function errorCode(): ?string
    {
        return $this->database->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->database->errorInfo();
    }

    private function portableSql(string $sql): string
    {
        $dialect = $this->dialectName();

        if($dialect === 'pgsql') {
            $sql = $this->qualifyPgsqlUpsertSelfReferences($sql);
        }

        if($dialect === 'sqlite') {
            // WireDatabasePDO opens SQLite write transactions with BEGIN IMMEDIATE,
            // which provides the serialization Kontor's row locks require. SQLite
            // has no FOR UPDATE syntax, so remove only that trailing lock clause.
            $sql = (string) preg_replace('/\s+FOR\s+UPDATE(?:\s+SKIP\s+LOCKED)?(?=\s*(?:;|$))/i', '', $sql);
        }

        return $sql;
    }

    /**
     * PostgreSQL exposes both the target and excluded rows in ON CONFLICT.
     * Qualify self-referential arithmetic in MySQL upserts so expressions such
     * as `version = version + 1` remain unambiguous after PW translates them.
     */
    private function qualifyPgsqlUpsertSelfReferences(string $sql): string
    {
        if(!preg_match('/^\s*INSERT\s+INTO\s+`?([A-Za-z_][A-Za-z0-9_]*)`?/i', $sql, $tableMatch)) {
            return $sql;
        }
        if(!preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql, $updateMatch, PREG_OFFSET_CAPTURE)) {
            return $sql;
        }

        $marker = $updateMatch[0][0];
        $offset = $updateMatch[0][1] + strlen($marker);
        $updates = substr($sql, $offset);
        $table = $tableMatch[1];

        $updates = preg_replace_callback(
            '/(`?([A-Za-z_][A-Za-z0-9_]*)`?)(\s*=\s*)(`?\2`?)(?=\s*[+*\/-])/',
            static fn(array $match): string => $match[1] . $match[3] . "`{$table}`.`{$match[2]}`",
            $updates
        );

        return substr($sql, 0, $offset) . $updates;
    }
}
