<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Infrastructure\Persistence;

use Kontor\Collaboration\Domain\Mention;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class MentionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(Mention $mention): void
    {
        $organizationId = $this->organizations->internalIdOf($mention->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_mentions
                (uid, organization_id, comment_uid, mentioned_user_id, created_at, read_at)
             VALUES
                (:uid, :organization_id, :comment_uid, :mentioned_user_id, :created_at, :read_at)
             ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
        );

        $statement->execute([
            'uid' => $mention->uid->toString(),
            'organization_id' => $organizationId,
            'comment_uid' => $mention->commentUid,
            'mentioned_user_id' => $mention->mentionedUserId,
            'created_at' => $mention->createdAt->format('Y-m-d H:i:s.u'),
            'read_at' => $mention->readAt?->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?Mention
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_mentions WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): Mention
    {
        return $this->find($uid) ?? throw new RuntimeException("Mention \"{$uid}\" was not found.");
    }

    /**
     * @return Mention[] newest first
     */
    public function forComment(string $commentUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_mentions WHERE comment_uid = :comment_uid ORDER BY created_at DESC');
        $statement->execute(['comment_uid' => $commentUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Mention[] oldest first
     */
    public function unreadFor(string $organizationUid, int $userId): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_mentions
             WHERE organization_id = :organization_id AND mentioned_user_id = :user_id AND read_at IS NULL
             ORDER BY created_at ASC'
        );
        $statement->execute(['organization_id' => $organizationId, 'user_id' => $userId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Mention
    {
        return new Mention(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            commentUid: $row['comment_uid'],
            mentionedUserId: (int) $row['mentioned_user_id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            readAt: $row['read_at'] !== null ? new \DateTimeImmutable($row['read_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
