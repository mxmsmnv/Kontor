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

        foreach (self::TABLES as $table) {
            $rowCounts[$table] = 0;
            $writer->writeTable($table, $this->streamTable($table, $rowCounts));
        }

        $writer->writeMetadata([
            'component' => $this->component(),
            'kind' => $context->kind,
            'exportedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'tables' => self::TABLES,
            'rowCounts' => $rowCounts,
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
            foreach (self::TABLES as $table) {
                $this->pdo->exec("DELETE FROM {$table}");

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
     * @return iterable<array<string, mixed>>
     */
    private function streamTable(string $table, array &$rowCounts): iterable
    {
        $statement = $this->pdo->query("SELECT * FROM {$table}");

        foreach ($statement as $row) {
            $rowCounts[$table]++;

            yield $row;
        }
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
