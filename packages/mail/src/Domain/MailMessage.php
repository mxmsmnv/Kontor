<?php

declare(strict_types=1);

namespace Kontor\Mail\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * One row per message, outbound or inbound (`$direction`). "Entity
 * linking" (kontor.md Substage 9.1) is deliberately not a property here —
 * see `EntityLinkingService`, which reuses `kontor/core`'s own
 * `kontor_relations` table instead.
 */
final class MailMessage
{
    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly ?string $mailboxUid,
        public readonly string $direction,
        public readonly string $fromAddress,
        public readonly array $toAddresses,
        public readonly array $ccAddresses,
        public readonly string $subject,
        public readonly string $bodyText,
        public string $status,
        public ?string $error,
        public ?int $assignedTo,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     */
    public static function outbound(
        string $organizationId,
        ?string $mailboxUid,
        string $fromAddress,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyText,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            mailboxUid: $mailboxUid,
            direction: 'outbound',
            fromAddress: $fromAddress,
            toAddresses: $toAddresses,
            ccAddresses: $ccAddresses,
            subject: $subject,
            bodyText: $bodyText,
            status: 'queued',
            error: null,
            assignedTo: null,
            occurredAt: $now,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     */
    public static function inbound(
        string $organizationId,
        ?string $mailboxUid,
        string $fromAddress,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyText,
        \DateTimeImmutable $receivedAt,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            mailboxUid: $mailboxUid,
            direction: 'inbound',
            fromAddress: $fromAddress,
            toAddresses: $toAddresses,
            ccAddresses: $ccAddresses,
            subject: $subject,
            bodyText: $bodyText,
            status: 'received',
            error: null,
            assignedTo: null,
            occurredAt: $receivedAt,
            createdAt: $now,
            updatedAt: $now,
            createdBy: null,
        );
    }

    public function markSent(): void
    {
        $this->status = 'sent';
        $this->error = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $error): void
    {
        $this->status = 'failed';
        $this->error = $error;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function assignTo(?int $userId): void
    {
        $this->assignedTo = $userId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
}
