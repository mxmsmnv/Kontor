<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Domain\RollbackResult;
use Kontor\Core\Domain\ImportRowOutcome;
use Kontor\Core\Infrastructure\ImportExport\Format\RecordReaderInterface;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\Events\KontorEvent;
use RuntimeException;

/**
 * Orchestrates a chunked import run (Substage 1.5): field mapping, dry run
 * preview, and — via RepositoryRegistry — batch rollback. A live
 * (non-dry-run) import is gated behind a verified pre-import backup, the
 * same pattern ComponentManager::update() uses for component updates
 * (kontor.md#25, kontor.md#24).
 */
final class ImportManager
{
    public function __construct(
        private readonly ImportProviderRegistry $providers,
        private readonly ?RepositoryRegistry $repositories = null,
        private readonly ?AuditLogger $audit = null,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly ?\PDO $pdo = null,
        private readonly int $progressEvery = 500,
    ) {
    }

    public function run(
        string $entityType,
        RecordReaderInterface $reader,
        string $path,
        ImportContext $context,
        ?BackupVerification $backupVerification = null,
        bool $bypassBackup = false,
        ?int $auditOrganizationId = null,
    ): ImportBatchResult {
        $provider = $this->providers->get($entityType);

        if (!$context->dryRun && !$bypassBackup && ($backupVerification === null || !$backupVerification->verified)) {
            throw new PreImportBackupRequiredException(
                "Importing \"{$entityType}\" requires a verified pre-import backup first (kontor.md#25). ".
                'Pass a verified BackupVerification, or bypassBackup: true for an actor holding '.
                'kontor-updates-bypass-backup.'
            );
        }

        $rowNumber = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];

        foreach ($reader->read($path) as $rawRow) {
            $rowNumber++;
            $row = $this->applyFieldMapping($rawRow, $context->fieldMapping);

            $validation = $provider->validate($row, $context);

            if (!$validation->valid) {
                $failed++;
                $rows[] = new ImportRowOutcome($rowNumber, 'failed', $this->flattenErrors($validation->errors));

                continue;
            }

            if ($context->dryRun) {
                $existingUid = $provider->findExisting($row, $context);

                if ($existingUid !== null) {
                    $updated++;
                    $rows[] = new ImportRowOutcome($rowNumber, 'would_update', entityUid: $existingUid);
                } else {
                    $created++;
                    $rows[] = new ImportRowOutcome($rowNumber, 'would_create');
                }

                continue;
            }

            $result = $provider->import($row, $context);

            match ($result->outcome) {
                'created' => $created++,
                'updated' => $updated++,
                'skipped' => $skipped++,
                'failed' => $failed++,
                default => null,
            };

            if ($result->outcome === 'failed') {
                $rows[] = new ImportRowOutcome($rowNumber, 'failed', $result->errorMessage, $result->entityUid);
            } elseif (in_array($result->outcome, ['created', 'updated'], true)) {
                $this->logRowOutcome($entityType, $result, $context, $auditOrganizationId);
            }

            if ($this->progressEvery > 0 && $rowNumber % $this->progressEvery === 0) {
                $this->emit('import.progress', $entityType, $context, [
                    'processed' => $rowNumber,
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]);
            }
        }

        $this->emit($context->dryRun ? 'import.previewed' : 'import.completed', $entityType, $context, [
            'totalRows' => $rowNumber,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return new ImportBatchResult(
            batchId: $context->batchId,
            entityType: $entityType,
            dryRun: $context->dryRun,
            totalRows: $rowNumber,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failed: $failed,
            rows: $rows,
        );
    }

    /**
     * Archives (never deletes — codex rule #10) every record this batch
     * created or updated, found via the audit ledger's correlation_id.
     */
    public function rollback(string $batchId, string $entityType): RollbackResult
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Rollback requires a PDO connection to query the audit ledger.');
        }

        if ($this->repositories === null || !$this->repositories->has($entityType)) {
            throw new RuntimeException("Cannot roll back \"{$entityType}\": no repository is registered for it.");
        }

        $statement = $this->pdo->prepare(
            "SELECT DISTINCT entity_uid FROM kontor_audit_events
             WHERE correlation_id = :batch_id AND entity_type = :entity_type
               AND action IN ('import.created', 'import.updated')"
        );
        $statement->execute(['batch_id' => $batchId, 'entity_type' => $entityType]);

        $repository = $this->repositories->get($entityType);
        $archived = 0;
        $errors = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $entityUid) {
            try {
                $repository->archive($entityUid);
                $archived++;
            } catch (\Throwable $e) {
                $errors[] = "Could not archive \"{$entityUid}\": {$e->getMessage()}";
            }
        }

        $this->events?->dispatch(KontorEvent::create(
            event: 'import.rolled_back',
            organizationId: 'system',
            entityType: 'import_batch',
            entityId: $batchId,
            actorType: 'system',
            actorId: null,
            data: ['entityType' => $entityType, 'archivedCount' => $archived, 'errors' => $errors],
        ));

        return new RollbackResult(archivedCount: $archived, errors: $errors);
    }

    /**
     * @param array<string, string[]> $errors
     */
    private function flattenErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = "{$field}: {$message}";
            }
        }

        return implode('; ', $messages);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $mapping source column => target field
     * @return array<string, mixed>
     */
    private function applyFieldMapping(array $row, array $mapping): array
    {
        if ($mapping === []) {
            return $row;
        }

        $mapped = [];

        foreach ($row as $column => $value) {
            $mapped[$mapping[$column] ?? $column] = $value;
        }

        return $mapped;
    }

    private function logRowOutcome(
        string $entityType,
        ImportRecordResult $result,
        ImportContext $context,
        ?int $auditOrganizationId,
    ): void {
        if ($this->audit === null || $auditOrganizationId === null || $result->entityUid === null) {
            return;
        }

        $this->audit->record(
            organizationId: $auditOrganizationId,
            component: 'core',
            entityType: $entityType,
            entityUid: $result->entityUid,
            action: 'import.' . $result->outcome,
            actorType: $context->actorType,
            actorUid: $context->actorId,
            correlationId: $context->batchId,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $eventName, string $importedEntityType, ImportContext $context, array $data): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: $context->organizationId,
            entityType: 'import_batch',
            entityId: $context->batchId,
            actorType: $context->actorType,
            actorId: $context->actorId,
            data: ['entityType' => $importedEntityType, ...$data],
        ));
    }
}
