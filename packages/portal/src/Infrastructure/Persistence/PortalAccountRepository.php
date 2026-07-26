<?php

declare(strict_types=1);

namespace Kontor\Portal\Infrastructure\Persistence;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Portal\Domain\PortalAccount;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class PortalAccountRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?PortalAccount
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_portal_accounts WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): PortalAccount
    {
        return $this->find($id) ?? throw new RuntimeException("Portal account \"{$id}\" was not found.");
    }

    public function findByEmail(string $organizationUid, string $email): ?PortalAccount
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_portal_accounts WHERE organization_id = :organization_id AND email = :email');
        $statement->execute(['organization_id' => $organizationId, 'email' => $email]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof PortalAccount) {
            throw new InvalidArgumentException('PortalAccountRepository::save() expects a PortalAccount.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_portal_accounts
                (uid, organization_id, contact_uid, email, password_hash, status, last_login_at,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :contact_uid, :email, :password_hash, :status, :last_login_at,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                email = VALUES(email), password_hash = VALUES(password_hash), status = VALUES(status),
                last_login_at = VALUES(last_login_at), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'contact_uid' => $entity->contactUid,
            'email' => $entity->email,
            'password_hash' => $entity->passwordHash,
            'status' => $entity->status,
            'last_login_at' => $entity->lastLoginAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_portal_accounts SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_portal_accounts SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, array{uid: string, contactUid: string}> every active account, for the health check
     */
    public function activeAccountsSummary(): array
    {
        $statement = $this->pdo->query("SELECT uid, contact_uid FROM kontor_portal_accounts WHERE status = 'active' AND archived_at IS NULL");

        return array_map(
            static fn (array $row) => ['uid' => $row['uid'], 'contactUid' => $row['contact_uid']],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    private function hydrate(array $row): PortalAccount
    {
        return new PortalAccount(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            contactUid: $row['contact_uid'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            status: $row['status'],
            lastLoginAt: $row['last_login_at'] !== null ? new \DateTimeImmutable($row['last_login_at']) : null,
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
