<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Application;

use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\UnreadStateRepository;

/**
 * The "unread states" milestone's per-thread flavor (see
 * kontor_unread_states' migration comment for the other, per-mention
 * flavor). unreadCount() is computed on read, not maintained as a
 * denormalized counter — a user who has never read a thread has no
 * kontor_unread_states row at all, which this treats as "everything is
 * unread" (last_read_at effectively the epoch) rather than a special case.
 */
final class UnreadStateService
{
    private const EPOCH = '1970-01-01 00:00:00.000000';

    public function __construct(
        private readonly CommentRepository $comments,
        private readonly UnreadStateRepository $unreadStates,
    ) {
    }

    public function markRead(string $organizationUid, int $userId, string $entityType, string $entityUid, ?\DateTimeImmutable $at = null): void
    {
        $this->unreadStates->markRead($organizationUid, $userId, $entityType, $entityUid, $at ?? new \DateTimeImmutable());
    }

    public function unreadCount(string $organizationUid, int $userId, string $entityType, string $entityUid): int
    {
        $state = $this->unreadStates->find($organizationUid, $userId, $entityType, $entityUid);
        $since = $state?->lastReadAt ?? new \DateTimeImmutable(self::EPOCH);

        return $this->comments->countSince($entityType, $entityUid, $since, $userId);
    }
}
