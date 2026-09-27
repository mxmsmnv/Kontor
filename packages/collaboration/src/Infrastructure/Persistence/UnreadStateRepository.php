<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Infrastructure\Persistence;

use Kontor\Collaboration\Domain\UnreadState;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class UnreadStateRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function markRead(string $organizationUid, int $userId, string $entityType, string $entityUid, \DateTimeImmutable $at): void
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_unread_states
                (uid, organization_id, user_id, entity_type, entity_uid, last_read_at, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :user_id, :entity_type, :entity_uid, :last_read_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE last_read_at = VALUES(last_read_at), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'uid' => Uid::generate()->toString(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'last_read_at' => $at->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function find(string $organizationUid, int $userId, string $entityType, string $entityUid): ?UnreadState
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_unread_states
             WHERE organization_id = :organization_id AND user_id = :user_id AND entity_type = :entity_type AND entity_uid = :entity_uid'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new UnreadState(
            uid: Uid::fromString($row['uid']),
            organizationId: $organizationUid,
            userId: (int) $row['user_id'],
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            lastReadAt: new \DateTimeImmutable($row['last_read_at']),
        );
    }
}
