<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Backup;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\BackupProviderInterface;
use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\BackupEstimate;
use Kontor\SDK\DTO\BackupReader;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\BackupWriter;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;

/**
 * Organization-scoped backup/restore for every table owned by Contacts.
 * Internal database ids are deliberately omitted so a verified backup can
 * be restored without colliding with rows belonging to another organization.
 */
final class ContactsBackupProvider implements BackupProviderInterface
{
    private const TABLES = [
        'kontor_contacts',
        'kontor_companies',
        'kontor_addresses',
        'kontor_contact_company',
        'kontor_extensions',
    ];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function component(): string
    {
        return 'contacts';
    }

    public function estimate(BackupContext $context): BackupEstimate
    {
        $organizationId = $this->organizations->internalIdOf($context->organizationId);
        $tables = [];
        $itemCount = 0;

        foreach (self::TABLES as $table) {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE " . $this->organizationWhere($table)
            );
            $statement->execute($this->organizationParams($table, $organizationId));
            $tables[$table] = (int) $statement->fetchColumn();
            $itemCount += $tables[$table];
        }

        return new BackupEstimate($itemCount, $itemCount * 768, $tables);
    }

    public function export(BackupWriter $writer, BackupContext $context): void
    {
        $organizationId = $this->organizations->internalIdOf($context->organizationId);
        $rowCounts = [];

        foreach (self::TABLES as $table) {
            $rowCounts[$table] = 0;
            $writer->writeTable($table, $this->streamTable($table, $organizationId, $rowCounts));
        }

        $writer->writeMetadata([
            'component' => $this->component(),
            'organizationId' => $context->organizationId,
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
            $errors[] = 'Backup metadata does not identify this as a Contacts backup.';
        }

        if (($metadata['organizationId'] ?? null) !== $context->organizationId) {
            $errors[] = 'Backup belongs to a different organization.';
        }

        $tableHashes = [];

        foreach (self::TABLES as $table) {
            if (!$reader->hasTable($table)) {
                $errors[] = "Backup is missing table \"{$table}\".";
                continue;
            }

            [$count, $hash] = $this->hashTable($reader, $table);
            $tableHashes[$table] = $hash;
            $expected = $metadata['rowCounts'][$table] ?? null;

            if ($expected !== null && (int) $expected !== $count) {
                $errors[] = "Table \"{$table}\" expected {$expected} rows, found {$count}.";
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
        $verification = $this->verify(
            $reader,
            new BackupContext($context->organizationId, 'snapshot')
        );

        if (!$verification->verified) {
            return new RestoreResult(false, errors: $verification->errors);
        }

        if ($context->dryRun) {
            $count = 0;
            foreach (self::TABLES as $table) {
                foreach ($reader->readTable($table) as $ignored) {
                    $count++;
                }
            }

            return new RestoreResult(true, $count);
        }

        $organizationId = $this->organizations->internalIdOf($context->organizationId);
        $restoredCount = 0;
        $this->pdo->beginTransaction();

        try {
            foreach (array_reverse(self::TABLES) as $table) {
                $statement = $this->pdo->prepare(
                    "DELETE FROM {$table} WHERE " . $this->organizationWhere($table)
                );
                $statement->execute($this->organizationParams($table, $organizationId));
            }

            foreach (self::TABLES as $table) {
                foreach ($reader->readTable($table) as $row) {
                    $this->insertRow($table, $row, $organizationId);
                    $restoredCount++;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return new RestoreResult(false, errors: [$exception->getMessage()]);
        }

        return new RestoreResult(true, $restoredCount);
    }

    /**
     * @param array<string, int> $rowCounts
     * @return iterable<array<string, mixed>>
     */
    private function streamTable(string $table, int $organizationId, array &$rowCounts): iterable
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE " . $this->organizationWhere($table) . ' ORDER BY id'
        );
        $statement->execute($this->organizationParams($table, $organizationId));

        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            unset($row['id'], $row['organization_id']);
            $rowCounts[$table]++;
            yield $row;
        }
    }

    /**
     * @return array{0: int, 1: string}
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

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(string $table, array $row, int $organizationId): void
    {
        unset($row['id'], $row['organization_id']);
        $row = ['organization_id' => $organizationId, ...$row];
        $columns = array_keys($row);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $this->pdo->prepare(
            "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($row);
    }

    private function organizationWhere(string $table): string
    {
        return $table === 'kontor_extensions'
            ? 'organization_id = :organization_id AND owner_component = :owner_component'
            : 'organization_id = :organization_id';
    }

    /**
     * @return array<string, int|string>
     */
    private function organizationParams(string $table, int $organizationId): array
    {
        $params = ['organization_id' => $organizationId];

        if ($table === 'kontor_extensions') {
            $params['owner_component'] = 'KontorContacts';
        }

        return $params;
    }
}
