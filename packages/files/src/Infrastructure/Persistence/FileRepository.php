<?php

declare(strict_types=1);

namespace Kontor\Files\Infrastructure\Persistence;

use Kontor\SDK\ValueObjects\Uid;

/**
 * PDO-backed persistence for kontor_files (kontor.md#11.6). Versions of
 * "the same file" are modeled as separate rows sharing
 * (entity_type, entity_uid, original_name) with an incrementing
 * version_number — the schema has no separate family/group column, so that
 * triple is the version key; superseding a version archives the old row
 * rather than deleting it (codex rule #10).
 */
final class FileRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function insert(
        int $organizationId,
        string $storage,
        string $path,
        string $originalName,
        ?string $mimeType,
        int $sizeBytes,
        string $checksum,
        string $visibility,
        ?string $classification,
        ?string $entityType,
        ?string $entityUid,
        int $versionNumber,
        array $metadata,
        ?int $createdBy,
    ): string {
        $uid = Uid::generate()->toString();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_files
                (uid, organization_id, storage, path, original_name, mime_type, size_bytes, checksum,
                 visibility, classification, entity_type, entity_uid, version_number, metadata_json,
                 created_at, created_by)
             VALUES
                (:uid, :organization_id, :storage, :path, :original_name, :mime_type, :size_bytes, :checksum,
                 :visibility, :classification, :entity_type, :entity_uid, :version_number, :metadata_json,
                 :created_at, :created_by)'
        );

        $statement->execute([
            'uid' => $uid,
            'organization_id' => $organizationId,
            'storage' => $storage,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'checksum' => $checksum,
            'visibility' => $visibility,
            'classification' => $classification,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'version_number' => $versionNumber,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'created_by' => $createdBy,
        ]);

        return $uid;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $uid): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_files WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPath(int $organizationId, string $path): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_files WHERE organization_id = :organization_id AND path = :path LIMIT 1'
        );
        $statement->execute(['organization_id' => $organizationId, 'path' => $path]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forOrganization(int $organizationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_files
             WHERE organization_id = :organization_id
             ORDER BY created_at DESC, version_number DESC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The active (non-archived) row for a given entity + filename slot —
     * the "current version".
     *
     * @return array<string, mixed>|null
     */
    public function findCurrentVersion(
        string $entityType,
        string $entityUid,
        string $originalName,
        ?int $organizationId = null,
    ): ?array
    {
        $organizationFilter = $organizationId !== null
            ? ' AND organization_id = :organization_id'
            : '';
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_files
             WHERE entity_type = :entity_type AND entity_uid = :entity_uid AND original_name = :original_name
                AND archived_at IS NULL
             ' . $organizationFilter . '
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $parameters = [
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'original_name' => $originalName,
        ];
        if ($organizationId !== null) {
            $parameters['organization_id'] = $organizationId;
        }
        $statement->execute($parameters);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>> every version, newest first
     */
    public function versionHistory(
        string $entityType,
        string $entityUid,
        string $originalName,
        ?int $organizationId = null,
    ): array
    {
        $organizationFilter = $organizationId !== null
            ? ' AND organization_id = :organization_id'
            : '';
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_files
             WHERE entity_type = :entity_type AND entity_uid = :entity_uid AND original_name = :original_name
             ' . $organizationFilter . '
             ORDER BY version_number DESC'
        );
        $parameters = [
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'original_name' => $originalName,
        ];
        if ($organizationId !== null) {
            $parameters['organization_id'] = $organizationId;
        }
        $statement->execute($parameters);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forEntity(string $entityType, string $entityUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_files
             WHERE entity_type = :entity_type AND entity_uid = :entity_uid AND archived_at IS NULL
             ORDER BY original_name, version_number DESC'
        );
        $statement->execute(['entity_type' => $entityType, 'entity_uid' => $entityUid]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function archive(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_files SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $uid]);
    }

    public function restore(string $uid): void
    {
        $file = $this->find($uid);
        if ($file === null) {
            return;
        }

        if ($file['entity_type'] !== null && $file['entity_uid'] !== null) {
            $statement = $this->pdo->prepare(
                'UPDATE kontor_files
                 SET archived_at = :now
                 WHERE entity_type = :entity_type
                   AND entity_uid = :entity_uid
                   AND original_name = :original_name
                   AND organization_id = :organization_id
                   AND uid <> :uid
                   AND archived_at IS NULL'
            );
            $statement->execute([
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
                'entity_type' => $file['entity_type'],
                'entity_uid' => $file['entity_uid'],
                'original_name' => $file['original_name'],
                'organization_id' => $file['organization_id'],
                'uid' => $uid,
            ]);
        }

        $statement = $this->pdo->prepare('UPDATE kontor_files SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);
    }
}
