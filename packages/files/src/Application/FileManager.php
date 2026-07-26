<?php

declare(strict_types=1);

namespace Kontor\Files\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * Orchestrates upload/version/share/archive for kontor_files
 * (Substage 2.2). Depends only on StorageInterface (kontor.md#9.11), never
 * on LocalPrivateStorage directly — swapping in an S3/R2/etc. adapter
 * (spec section 5.5) requires no change here.
 */
final class FileManager
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly FileRepository $files,
        private readonly OrganizationRepository $organizations,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Uploads a file. If entityType/entityUid are given and a current
     * version already exists for that entity + filename, this instead
     * creates a new version and archives the previous one.
     *
     * @param array<string, mixed> $metadata
     * @return array{uid: string, versionNumber: int}
     */
    public function upload(
        string $organizationUid,
        string $originalName,
        mixed $contents,
        string $visibility = 'private',
        ?string $classification = null,
        ?string $entityType = null,
        ?string $entityUid = null,
        array $metadata = [],
        ?int $actorId = null,
    ): array {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $existing = ($entityType !== null && $entityUid !== null)
            ? $this->files->findCurrentVersion($entityType, $entityUid, $originalName, $organizationId)
            : null;

        $path = $this->pathFor($organizationId, $originalName);
        $stored = $this->storage->put($path, $contents);
        $versionNumber = $existing !== null ? ((int) $existing['version_number']) + 1 : 1;

        try {
            $uid = $this->files->insert(
                organizationId: $organizationId,
                storage: $stored->storage,
                path: $stored->path,
                originalName: $originalName,
                mimeType: $stored->mimeType,
                sizeBytes: $stored->sizeBytes,
                checksum: $stored->checksum,
                visibility: $visibility,
                classification: $classification,
                entityType: $entityType,
                entityUid: $entityUid,
                versionNumber: $versionNumber,
                metadata: $metadata,
                createdBy: $actorId,
            );
        } catch (\Throwable $exception) {
            $this->storage->delete($stored->path);
            throw $exception;
        }

        if ($existing !== null) {
            $this->files->archive($existing['uid']);
            $this->emit('file.version_created', $uid, $organizationUid, [
                'originalName' => $originalName,
                'version' => $versionNumber,
                'supersedes' => $existing['uid'],
            ]);
        } else {
            $this->emit('file.uploaded', $uid, $organizationUid, ['originalName' => $originalName, 'version' => 1]);
        }

        return ['uid' => $uid, 'versionNumber' => $versionNumber];
    }

    public function temporaryUrl(string $uid, \DateTimeImmutable $expiresAt, string $organizationUid): string
    {
        $file = $this->requireFile($uid, $organizationUid);
        $url = $this->storage->temporaryUrl($file['path'], $expiresAt);

        $this->emit('file.shared', $uid, $organizationUid, ['expiresAt' => $expiresAt->format(DATE_ATOM)]);

        return $url;
    }

    public function read(string $uid, ?string $organizationUid = null)
    {
        return $this->storage->read($this->requireFile($uid, $organizationUid)['path']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $uid): ?array
    {
        return $this->files->find($uid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function versionHistory(
        string $entityType,
        string $entityUid,
        string $originalName,
        ?string $organizationUid = null,
    ): array
    {
        return $this->files->versionHistory(
            $entityType,
            $entityUid,
            $originalName,
            $organizationUid !== null ? $this->organizations->internalIdOf($organizationUid) : null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forEntity(
        string $entityType,
        string $entityUid,
        ?string $organizationUid = null,
    ): array
    {
        return $this->files->forEntity(
            $entityType,
            $entityUid,
            $organizationUid !== null ? $this->organizations->internalIdOf($organizationUid) : null,
        );
    }

    public function archive(string $uid, string $organizationUid): void
    {
        $this->requireFile($uid, $organizationUid);
        $this->files->archive($uid);
        $this->emit('file.archived', $uid, $organizationUid);
    }

    public function restore(string $uid, string $organizationUid): void
    {
        $this->requireFile($uid, $organizationUid);
        $this->files->restore($uid);
        $this->emit('file.restored', $uid, $organizationUid);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireFile(string $uid, ?string $organizationUid = null): array
    {
        $file = $this->files->find($uid) ?? throw new RuntimeException("File \"{$uid}\" was not found.");
        if ($organizationUid !== null
            && (int) $file['organization_id'] !== $this->organizations->internalIdOf($organizationUid)) {
            throw new RuntimeException("File \"{$uid}\" does not belong to this organization.");
        }

        return $file;
    }

    private function pathFor(int $organizationId, string $originalName): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'file';

        return "org-{$organizationId}/" . Uid::generate()->toString() . '-' . $safeName;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $eventName, string $fileUid, string $organizationUid, array $data = []): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: $organizationUid,
            entityType: 'file',
            entityId: $fileUid,
            actorType: 'system',
            actorId: null,
            data: $data,
        ));
    }
}
