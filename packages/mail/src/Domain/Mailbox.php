<?php

declare(strict_types=1);

namespace Kontor\Mail\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "shared mailboxes" milestone: a shared inbox (e.g. "support@")
 * multiple staff can send from and receive into, rather than every
 * message being tied to one individual's own mailbox.
 */
final class Mailbox
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public string $emailAddress,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function open(
        string $organizationId,
        string $name,
        string $emailAddress,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            emailAddress: $emailAddress,
            status: 'active',
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
