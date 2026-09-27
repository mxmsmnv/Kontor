<?php

declare(strict_types=1);

namespace Kontor\API\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#20.11 "delivery log". Mutated across retry attempts rather
 * than appended — see the migration's doc comment.
 */
final class WebhookDelivery
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $subscriptionUid,
        public readonly string $eventUid,
        public readonly string $eventName,
        public readonly array $payload,
        public string $status,
        public int $attemptCount,
        public ?int $responseCode,
        public ?string $lastError,
        public ?\DateTimeImmutable $nextAttemptAt,
        public ?\DateTimeImmutable $deliveredAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(
        string $organizationId,
        string $subscriptionUid,
        string $eventUid,
        string $eventName,
        array $payload,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            subscriptionUid: $subscriptionUid,
            eventUid: $eventUid,
            eventName: $eventName,
            payload: $payload,
            status: 'pending',
            attemptCount: 0,
            responseCode: null,
            lastError: null,
            nextAttemptAt: null,
            deliveredAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function markDelivered(int $responseCode): void
    {
        $this->status = 'delivered';
        $this->responseCode = $responseCode;
        $this->lastError = null;
        $this->nextAttemptAt = null;
        $this->deliveredAt = new \DateTimeImmutable();
        $this->updatedAt = $this->deliveredAt;
    }

    public function markAttemptFailed(?int $responseCode, string $error, ?\DateTimeImmutable $nextAttemptAt): void
    {
        $this->attemptCount++;
        $this->responseCode = $responseCode;
        $this->lastError = $error;
        $this->status = $nextAttemptAt !== null ? 'pending' : 'exhausted';
        $this->nextAttemptAt = $nextAttemptAt;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function resetForReplay(): void
    {
        $this->status = 'pending';
        $this->nextAttemptAt = new \DateTimeImmutable();
        $this->updatedAt = $this->nextAttemptAt;
    }
}
