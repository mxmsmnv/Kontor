<?php

declare(strict_types=1);

namespace Kontor\Mail\DTO;

/**
 * A message an `InboundMailAdapterInterface` has fetched but not yet
 * persisted — `InboundMailService` turns this into a `MailMessage`.
 */
final class InboundMessage
{
    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     */
    public function __construct(
        public readonly string $fromAddress,
        public readonly array $toAddresses,
        public readonly array $ccAddresses,
        public readonly string $subject,
        public readonly string $bodyText,
        public readonly \DateTimeImmutable $receivedAt,
    ) {
    }
}
