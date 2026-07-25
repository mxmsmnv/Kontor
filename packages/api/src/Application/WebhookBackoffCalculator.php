<?php

declare(strict_types=1);

namespace Kontor\API\Application;

/**
 * The pure decision logic behind the "webhooks" milestone's retries and
 * exponential backoff (kontor.md#20.11) — extracted out of
 * `WebhookDeliveryService` so it runs as a real unit test, the same
 * "pure vs DB-touching split" already used for
 * `Kontor\Entities\Application\EntityViewService`.
 */
final class WebhookBackoffCalculator
{
    public function __construct(
        private readonly int $maxAttempts = 8,
        private readonly int $baseBackoffSeconds = 30,
        private readonly int $disableAfterConsecutiveFailures = 10,
    ) {
    }

    /**
     * @param int $attemptCountBeforeThisFailure the delivery's attempt count *before* this failed attempt
     * @return \DateTimeImmutable|null null means retries are exhausted
     */
    public function nextAttemptAt(int $attemptCountBeforeThisFailure, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if ($attemptCountBeforeThisFailure + 1 >= $this->maxAttempts) {
            return null;
        }

        $now ??= new \DateTimeImmutable();
        $delaySeconds = $this->baseBackoffSeconds * 2 ** $attemptCountBeforeThisFailure;

        return $now->modify("+{$delaySeconds} seconds");
    }

    public function shouldDisable(int $consecutiveFailures): bool
    {
        return $consecutiveFailures >= $this->disableAfterConsecutiveFailures;
    }
}
