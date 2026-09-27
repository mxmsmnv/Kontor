<?php

declare(strict_types=1);

namespace Kontor\Tasks\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class TaskReminder
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $taskUid,
        public readonly \DateTimeImmutable $remindAt,
        public readonly string $channel,
        public ?\DateTimeImmutable $sentAt = null,
    ) {
    }

    public static function create(
        string $organizationId,
        string $taskUid,
        \DateTimeImmutable $remindAt,
        string $channel = 'app',
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            taskUid: $taskUid,
            remindAt: $remindAt,
            channel: $channel,
        );
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }
}
