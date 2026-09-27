<?php

declare(strict_types=1);

namespace Kontor\Entities\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Domain\EntityRecord;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class EntityRecordRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?EntityRecord
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_records WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findActive(string $id): ?EntityRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_entity_records WHERE uid = :uid AND archived_at IS NULL'
        );
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): EntityRecord
    {
        return $this->find($id) ?? throw new RuntimeException("Entity record \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof EntityRecord) {
            throw new InvalidArgumentException('EntityRecordRepository::save() expects an EntityRecord.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_entity_records (uid, organization_id, definition_uid, data_json, status, created_at, updated_at, created_by, version)
             VALUES (:uid, :organization_id, :definition_uid, :data_json, :status, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $entity->definitionUid,
            'data_json' => json_encode($entity->data, JSON_THROW_ON_ERROR),
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_entity_records SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_entity_records SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return EntityRecord[]
     */
    public function forDefinition(string $definitionUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_records WHERE definition_uid = :definition_uid AND archived_at IS NULL ORDER BY created_at ASC');
        $statement->execute(['definition_uid' => $definitionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return EntityRecord[]
     */
    public function forDefinitionPage(string $definitionUid, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_entity_records
             WHERE definition_uid = :definition_uid AND archived_at IS NULL
             ORDER BY created_at ASC, id ASC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':definition_uid', $definitionUid);
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countForDefinition(string $definitionUid): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM kontor_entity_records
             WHERE definition_uid = :definition_uid AND archived_at IS NULL'
        );
        $statement->execute(['definition_uid' => $definitionUid]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Records whose definition_uid doesn't match any existing definition
     * — the invariant EntitiesHealthCheck watches for.
     */
    public function countOrphaned(): int
    {
        $statement = $this->pdo->query(
            'SELECT COUNT(*) FROM kontor_entity_records r
             LEFT JOIN kontor_entity_definitions d ON d.uid = r.definition_uid
             WHERE d.uid IS NULL OR d.archived_at IS NOT NULL'
        );

        return (int) $statement->fetchColumn();
    }

    private function hydrate(array $row): EntityRecord
    {
        return new EntityRecord(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            data: json_decode($row['data_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            status: $row['status'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
