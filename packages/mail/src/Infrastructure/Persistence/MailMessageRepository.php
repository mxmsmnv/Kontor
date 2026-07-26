<?php

declare(strict_types=1);

namespace Kontor\Mail\Infrastructure\Persistence;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Mail\Domain\MailMessage;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class MailMessageRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?MailMessage
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_mail_messages WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): MailMessage
    {
        return $this->find($id) ?? throw new RuntimeException("Mail message \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof MailMessage) {
            throw new InvalidArgumentException('MailMessageRepository::save() expects a MailMessage.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_mail_messages
                (uid, organization_id, mailbox_uid, direction, from_address, to_addresses_json, cc_addresses_json,
                 subject, body_text, status, error, assigned_to, occurred_at, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :mailbox_uid, :direction, :from_address, :to_addresses_json, :cc_addresses_json,
                 :subject, :body_text, :status, :error, :assigned_to, :occurred_at, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), error = VALUES(error), assigned_to = VALUES(assigned_to),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'mailbox_uid' => $entity->mailboxUid,
            'direction' => $entity->direction,
            'from_address' => $entity->fromAddress,
            'to_addresses_json' => json_encode($entity->toAddresses, JSON_THROW_ON_ERROR),
            'cc_addresses_json' => $entity->ccAddresses !== [] ? json_encode($entity->ccAddresses, JSON_THROW_ON_ERROR) : null,
            'subject' => $entity->subject,
            'body_text' => $entity->bodyText,
            'status' => $entity->status,
            'error' => $entity->error,
            'assigned_to' => $entity->assignedTo,
            'occurred_at' => $entity->occurredAt->format('Y-m-d H:i:s.u'),
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_mail_messages SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_mail_messages SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return MailMessage[]
     */
    public function forMailbox(string $mailboxUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_mail_messages WHERE mailbox_uid = :mailbox_uid ORDER BY occurred_at DESC');
        $statement->execute(['mailbox_uid' => $mailboxUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return MailMessage[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_mail_messages WHERE organization_id = :organization_id ORDER BY occurred_at DESC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countByStatus(string $organizationUid, string $status): int
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM kontor_mail_messages WHERE organization_id = :organization_id AND status = :status'
        );
        $statement->execute(['organization_id' => $organizationId, 'status' => $status]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Inbound messages nobody has claimed yet — used by the health
     * check to flag a shared mailbox nothing is watching.
     *
     * @return MailMessage[]
     */
    public function unassignedInbound(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_mail_messages
             WHERE organization_id = :organization_id AND direction = 'inbound' AND assigned_to IS NULL
             ORDER BY occurred_at ASC"
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): MailMessage
    {
        return new MailMessage(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            mailboxUid: $row['mailbox_uid'],
            direction: $row['direction'],
            fromAddress: $row['from_address'],
            toAddresses: json_decode($row['to_addresses_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            ccAddresses: $row['cc_addresses_json'] !== null ? json_decode($row['cc_addresses_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            subject: $row['subject'],
            bodyText: $row['body_text'],
            status: $row['status'],
            error: $row['error'],
            assignedTo: $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
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
