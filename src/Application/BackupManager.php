<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\BackupRecord;
use Kontor\Core\Infrastructure\Backup\Local\LocalFilesystemBackupReader;
use Kontor\Core\Infrastructure\Backup\Local\LocalFilesystemBackupWriter;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;
use Kontor\SDK\ValueObjects\Uid;

/**
 * Orchestrates backup/verify/restore for any registered component
 * (Substage 1.4). create() always exports then verifies in the same call,
 * per kontor.md#24: "create backup; verify backup; mark backup Verified;
 * only then continue" — there is no code path that produces an unverified
 * BackupRecord marked as done.
 */
final class BackupManager
{
    public function __construct(
        private readonly BackupProviderRegistry $providers,
        private readonly string $storageRootDir,
    ) {
    }

    /**
     * @param 'full'|'data'|'configuration'|'component'|'pre-update'|'snapshot' $kind
     */
    public function create(string $component, string $kind, string $organizationId, ?string $reason = null): BackupRecord
    {
        $provider = $this->providers->get($component);
        $context = new BackupContext(organizationId: $organizationId, kind: $kind, reason: $reason);

        $estimate = $provider->estimate($context);

        $id = Uid::generate()->toString();
        $path = $this->pathFor($component, $kind, $id);

        $provider->export(new LocalFilesystemBackupWriter($path), $context);
        $verification = $provider->verify(new LocalFilesystemBackupReader($path), $context);

        return new BackupRecord(
            id: $id,
            component: $component,
            kind: $kind,
            path: $path,
            createdAt: new \DateTimeImmutable(),
            itemCount: $estimate->itemCount,
            estimatedSizeBytes: $estimate->estimatedSizeBytes,
            verified: $verification->verified,
            checksum: $verification->checksum,
            verificationErrors: $verification->errors,
        );
    }

    public function verify(string $path, string $component, string $organizationId, string $kind = 'component'): bool
    {
        $provider = $this->providers->get($component);
        $reader = new LocalFilesystemBackupReader($path);

        return $provider->verify($reader, new BackupContext($organizationId, $kind))->verified;
    }

    public function restore(string $path, string $component, string $organizationId, bool $dryRun = false): RestoreResult
    {
        $provider = $this->providers->get($component);
        $reader = new LocalFilesystemBackupReader($path);

        return $provider->restore($reader, new RestoreContext($organizationId, $dryRun));
    }

    /**
     * @return string[] absolute paths of every backup on disk, most recent last
     */
    public function list(): array
    {
        if (!is_dir($this->storageRootDir)) {
            return [];
        }

        $entries = [];

        foreach (scandir($this->storageRootDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $this->storageRootDir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $entries[] = $full;
            }
        }

        sort($entries);

        return $entries;
    }

    public function findById(string $id): ?string
    {
        if (
            $id === ''
            || basename($id) !== $id
            || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1
        ) {
            return null;
        }

        foreach ($this->list() as $path) {
            if (hash_equals(basename($path), $id)) {
                return $path;
            }
        }

        return null;
    }

    private function pathFor(string $component, string $kind, string $id): string
    {
        return rtrim($this->storageRootDir, '/\\') . DIRECTORY_SEPARATOR . "{$component}-{$kind}-{$id}";
    }
}
