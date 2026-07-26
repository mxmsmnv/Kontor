<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Infrastructure\Persistence;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class CommentRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Comment
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_comments WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Comment
    {
        return $this->find($id) ?? throw new RuntimeException("Comment \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Comment) {
            throw new InvalidArgumentException('CommentRepository::save() expects a Comment.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_comments
                (uid, organization_id, entity_type, entity_uid, parent_uid, body, created_at, updated_at,
                 created_by, updated_by, version)
             VALUES
                (:uid, :organization_id, :entity_type, :entity_uid, :parent_uid, :body, :created_at, :updated_at,
                 :created_by, :updated_by, 1)
             ON DUPLICATE KEY UPDATE
                body = VALUES(body), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'entity_type' => $entity->entityType,
            'entity_uid' => $entity->entityUid,
            'parent_uid' => $entity->parentUid,
            'body' => $entity->body,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $now,
            'created_by' => $entity->createdBy,
            'updated_by' => $entity->updatedBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_comments SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_comments SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Comment[] oldest first (thread reading order)
     */
    public function forEntity(string $entityType, string $entityUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_comments
             WHERE entity_type = :entity_type AND entity_uid = :entity_uid AND archived_at IS NULL
             ORDER BY created_at ASC'
        );
        $statement->execute(['entity_type' => $entityType, 'entity_uid' => $entityUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Comment[]
     */
    public function findRecent(string $organizationUid, int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_comments
             WHERE organization_id = :organization_id AND archived_at IS NULL
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(
            ':organization_id',
            $this->organizations->internalIdOf($organizationUid),
            \PDO::PARAM_INT
        );
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * The "unread states" milestone's actual count query: comments on this
     * entity created after $since, excluding ones authored by the viewer
     * themselves (your own comments never count as unread for you).
     */
    public function countSince(string $entityType, string $entityUid, \DateTimeImmutable $since, ?int $excludingAuthorUserId): int
    {
        $sql = 'SELECT COUNT(*) FROM kontor_comments
                WHERE entity_type = :entity_type AND entity_uid = :entity_uid AND archived_at IS NULL
                   AND created_at > :since';
        $params = [
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'since' => $since->format('Y-m-d H:i:s.u'),
        ];

        if ($excludingAuthorUserId !== null) {
            $sql .= ' AND (created_by IS NULL OR created_by != :excluding_author)';
            $params['excluding_author'] = $excludingAuthorUserId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function hydrate(array $row): Comment
    {
        return new Comment(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            parentUid: $row['parent_uid'],
            body: $row['body'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: $row['updated_at'] !== null ? new \DateTimeImmutable($row['updated_at']) : null,
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
            updatedBy: $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
