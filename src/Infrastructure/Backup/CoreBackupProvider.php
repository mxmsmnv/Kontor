<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Backup;

use Kontor\SDK\Contracts\BackupProviderInterface;
use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\BackupEstimate;
use Kontor\SDK\DTO\BackupReader;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\BackupWriter;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;

/**
 * Backs up the tables Kontor Core owns (kontor.md#11, kontor.json
 * storage.tables). Every business component ships its own
 * BackupProviderInterface the same way — this is the reference
 * implementation for the Substage 1.4 "component backup" milestone.
 */
final class CoreBackupProvider implements BackupProviderInterface
{
    private const TABLES = [
        'kontor_organizations',
        'kontor_components',
        'kontor_migrations',
        'kontor_audit_events',
        'kontor_sequences',
        'kontor_extensions',
        'kontor_relations',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function component(): string
    {
        return 'core';
    }

    public function estimate(BackupContext $context): BackupEstimate
    {
        $itemCount = 0;
        $tables = [];

        foreach (self::TABLES as $table) {
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $tables[$table] = $count;
            $itemCount += $count;
        }

        return new BackupEstimate(itemCount: $itemCount, estimatedSizeBytes: $itemCount * 512, tables: $tables);
    }

    public function export(BackupWriter $writer, BackupContext $context): void
    {
        $rowCounts = [];
        $tableHashes = [];

        foreach (self::TABLES as $table) {
            $rowCounts[$table] = 0;
            $writer->writeTable($table, $this->streamTable($table, $rowCounts, $tableHashes));
        }

        $writer->writeMetadata([
            'component' => $this->component(),
            'kind' => $context->kind,
            'exportedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'tables' => self::TABLES,
            'rowCounts' => $rowCounts,
            'tableHashes' => $tableHashes,
        ]);
    }

    public function verify(BackupReader $reader, BackupContext $context): BackupVerification
    {
        $metadata = $reader->readMetadata();
        $errors = [];

        if (($metadata['component'] ?? null) !== $this->component()) {
            $errors[] = 'Backup metadata does not identify this as a core backup.';
        }

        $expectedCounts = $metadata['rowCounts'] ?? [];
        $expectedHashes = $metadata['tableHashes'] ?? [];
        $hasHashMetadata = array_key_exists('tableHashes', $metadata);
        $tableHashes = [];

        foreach (self::TABLES as $table) {
            if (!$reader->hasTable($table)) {
                $errors[] = "Backup is missing table \"{$table}\".";

                continue;
            }

            [$actualCount, $hash] = $this->hashTable($reader, $table);
            $tableHashes[$table] = $hash;

            $expected = $expectedCounts[$table] ?? null;

            if ($expected !== null && (int) $expected !== $actualCount) {
                $errors[] = "Table \"{$table}\" expected {$expected} rows, found {$actualCount}.";
            }

            $expectedHash = $expectedHashes[$table] ?? null;

            if ($hasHashMetadata
                && ($expectedHash === null || !hash_equals((string) $expectedHash, $hash))) {
                $errors[] = "Table \"{$table}\" failed checksum verification.";
            }
        }

        return new BackupVerification(
            verified: $errors === [],
            errors: $errors,
            checksum: hash('sha256', json_encode($tableHashes, JSON_THROW_ON_ERROR)),
        );
    }

    public function restore(BackupReader $reader, RestoreContext $context): RestoreResult
    {
        if ($context->dryRun) {
            $restoredCount = 0;

            foreach (self::TABLES as $table) {
                foreach ($reader->readTable($table) as $ignored) {
                    $restoredCount++;
                }
            }

            return new RestoreResult(success: true, restoredCount: $restoredCount);
        }

        $restoredCount = 0;
        $this->pdo->beginTransaction();

        try {
            foreach (array_reverse(self::TABLES) as $table) {
                $this->pdo->exec("DELETE FROM {$table}");
            }

            foreach (self::TABLES as $table) {
                foreach ($reader->readTable($table) as $row) {
                    $this->insertRow($table, $row);
                    $restoredCount++;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return new RestoreResult(success: false, restoredCount: 0, errors: [$e->getMessage()]);
        }

        return new RestoreResult(success: true, restoredCount: $restoredCount);
    }

    /**
     * @param array<string, int> $rowCounts
     * @param array<string, string> $tableHashes
     * @return iterable<array<string, mixed>>
     */
    private function streamTable(string $table, array &$rowCounts, array &$tableHashes): iterable
    {
        $statement = $this->pdo->query("SELECT * FROM {$table}");
        $context = hash_init('sha256');

        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $rowCounts[$table]++;
            hash_update($context, json_encode($row, JSON_THROW_ON_ERROR));

            yield $row;
        }

        $tableHashes[$table] = hash_final($context);
    }

    /**
     * @return array{0: int, 1: string} row count and a content hash for the table
     */
    private function hashTable(BackupReader $reader, string $table): array
    {
        $count = 0;
        $context = hash_init('sha256');

        foreach ($reader->readTable($table) as $row) {
            $count++;
            hash_update($context, json_encode($row, JSON_THROW_ON_ERROR));
        }

        return [$count, hash_final($context)];
    }

    private function insertRow(string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $statement = $this->pdo->prepare(
            "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($row);
    }
}
