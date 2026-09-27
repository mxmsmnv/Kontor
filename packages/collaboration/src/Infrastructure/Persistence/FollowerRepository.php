<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Infrastructure\Persistence;

use Kontor\Collaboration\Domain\Follower;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

/**
 * `follow()` is idempotent both here (INSERT ... ON DUPLICATE KEY UPDATE
 * against uniq_follow) and at the schema level.
 */
final class FollowerRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function follow(string $organizationUid, string $entityType, string $entityUid, int $userId): void
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_followers (uid, organization_id, entity_type, entity_uid, user_id, created_at)
             VALUES (:uid, :organization_id, :entity_type, :entity_uid, :user_id, :created_at)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );
        $statement->execute([
            'uid' => Uid::generate()->toString(),
            'organization_id' => $organizationId,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'user_id' => $userId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function unfollow(string $organizationUid, string $entityType, string $entityUid, int $userId): void
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'DELETE FROM kontor_followers
             WHERE organization_id = :organization_id AND entity_type = :entity_type AND entity_uid = :entity_uid AND user_id = :user_id'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'user_id' => $userId,
        ]);
    }

    public function isFollowing(string $organizationUid, string $entityType, string $entityUid, int $userId): bool
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT 1 FROM kontor_followers
             WHERE organization_id = :organization_id AND entity_type = :entity_type AND entity_uid = :entity_uid AND user_id = :user_id'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return Follower[]
     */
    public function followersOf(string $entityType, string $entityUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_followers WHERE entity_type = :entity_type AND entity_uid = :entity_uid ORDER BY created_at ASC'
        );
        $statement->execute(['entity_type' => $entityType, 'entity_uid' => $entityUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Follower
    {
        return new Follower(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            userId: (int) $row['user_id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
