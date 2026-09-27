<?php

declare(strict_types=1);

namespace Kontor\Mail\Contracts;

/**
 * The outbound-transport boundary `OutboundMailService` depends on, so
 * it's unit-testable with a fake sender instead of a live mail transport
 * — the same injectable-I/O-boundary idea `kontor/api`'s
 * `HttpClientInterface` and `kontor/marketplace`'s
 * `RegistryClientInterface` already use for their own external
 * dependencies.
 */
interface MailSenderInterface
{
    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     *
     * @throws \RuntimeException if the message could not be sent
     */
    public function send(
        string $fromAddress,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyText,
    ): void;
}
