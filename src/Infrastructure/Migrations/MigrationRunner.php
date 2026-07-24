<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Migrations;

use RuntimeException;

/**
 * Executes MigrationInterface instances at most once per component,
 * recording the outcome in kontor_migrations (kontor.md#11.3). Each
 * migration runs inside its own transaction for statements that support it;
 * note that MySQL/InnoDB DDL (CREATE TABLE etc.) auto-commits and cannot be
 * rolled back by this transaction, so a migration that fails partway
 * through a schema change is recorded as failed and left retryable rather
 * than silently undone — write migrations to be safe to re-run.
 */
final class MigrationRunner
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * Creates the migration ledger itself. Must run before any other
     * migration, and is not tracked in the ledger it creates.
     */
    public function ensureLedgerExists(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                component VARCHAR(191) NOT NULL,
                migration VARCHAR(191) NOT NULL,
                checksum CHAR(64) NOT NULL,
                executed_at DATETIME(6) NOT NULL,
                execution_time_ms INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL,
                error_message TEXT NULL,
                UNIQUE KEY uniq_component_migration (component, migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    /**
     * @param iterable<MigrationInterface> $migrations
     * @return array<string, 'skipped'|'ok'|'failed'> migration name => outcome
     */
    public function run(iterable $migrations): array
    {
        $outcomes = [];

        foreach ($migrations as $migration) {
            $outcomes[$migration->name()] = $this->runOne($migration);
        }

        return $outcomes;
    }

    private function runOne(MigrationInterface $migration): string
    {
        if ($this->wasExecuted($migration)) {
            return 'skipped';
        }

        $checksum = $this->checksumOf($migration);
        $start = microtime(true);

        $this->pdo->beginTransaction();

        try {
            $migration->up($this->pdo);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->recordExecution($migration, $checksum, $start, 'failed', $e->getMessage());

            throw new RuntimeException(
                "Migration \"{$migration->component()}::{$migration->name()}\" failed: {$e->getMessage()}",
                previous: $e
            );
        }

        $this->recordExecution($migration, $checksum, $start, 'ok', null);

        return 'ok';
    }

    /**
     * Only a migration that previously succeeded counts as executed — a
     * failed attempt must remain retryable on the next run() call.
     */
    private function wasExecuted(MigrationInterface $migration): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM kontor_migrations WHERE component = :component AND migration = :migration AND status = 'ok'"
        );
        $statement->execute(['component' => $migration->component(), 'migration' => $migration->name()]);

        return (bool) $statement->fetchColumn();
    }

    private function checksumOf(MigrationInterface $migration): string
    {
        $file = (new \ReflectionClass($migration))->getFileName();

        return $file !== false && is_readable($file)
            ? hash('sha256', (string) file_get_contents($file))
            : hash('sha256', $migration->component() . ':' . $migration->name());
    }

    private function recordExecution(
        MigrationInterface $migration,
        string $checksum,
        float $start,
        string $status,
        ?string $errorMessage
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_migrations
                (component, migration, checksum, executed_at, execution_time_ms, status, error_message)
             VALUES (:component, :migration, :checksum, :executed_at, :execution_time_ms, :status, :error_message)
             ON DUPLICATE KEY UPDATE
                checksum = VALUES(checksum),
                executed_at = VALUES(executed_at),
                execution_time_ms = VALUES(execution_time_ms),
                status = VALUES(status),
                error_message = VALUES(error_message)'
        );

        $statement->execute([
            'component' => $migration->component(),
            'migration' => $migration->name(),
            'checksum' => $checksum,
            'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'execution_time_ms' => (int) round((microtime(true) - $start) * 1000),
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
