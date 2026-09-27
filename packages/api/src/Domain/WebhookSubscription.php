<?php

declare(strict_types=1);

namespace Kontor\API\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#20.11. `eventPattern` is an exact event name — see the
 * migration's own doc comment for why. `secret` signs every delivery
 * (HMAC-SHA256, see `WebhookDeliveryService`); a caller only ever sees it
 * once, at creation, the same one-time-reveal principle `ApiToken` uses
 * for its own plaintext.
 */
final class WebhookSubscription
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $url,
        public string $eventPattern,
        public readonly string $secret,
        public string $status,
        public int $consecutiveFailures,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $url,
        string $eventPattern,
        string $secret,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            url: $url,
            eventPattern: $eventPattern,
            secret: $secret,
            status: 'active',
            consecutiveFailures: 0,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function recordFailure(): void
    {
        $this->consecutiveFailures++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordSuccess(): void
    {
        $this->consecutiveFailures = 0;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function disable(): void
    {
        $this->status = 'disabled';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
