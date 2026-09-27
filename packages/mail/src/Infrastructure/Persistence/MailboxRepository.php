<?php

declare(strict_types=1);

namespace Kontor\Mail\Infrastructure\Persistence;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Mail\Domain\Mailbox;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class MailboxRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Mailbox
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_mail_mailboxes WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Mailbox
    {
        return $this->find($id) ?? throw new RuntimeException("Mailbox \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Mailbox) {
            throw new InvalidArgumentException('MailboxRepository::save() expects a Mailbox.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_mail_mailboxes
                (uid, organization_id, name, email_address, status, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :name, :email_address, :status, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), email_address = VALUES(email_address), status = VALUES(status),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $entity->name,
            'email_address' => $entity->emailAddress,
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_mail_mailboxes SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_mail_mailboxes SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Mailbox[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_mail_mailboxes WHERE organization_id = :organization_id ORDER BY name ASC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Mailbox
    {
        return new Mailbox(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            emailAddress: $row['email_address'],
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
