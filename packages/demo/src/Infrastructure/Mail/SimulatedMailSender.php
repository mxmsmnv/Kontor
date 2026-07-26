<?php

declare(strict_types=1);

namespace Kontor\Demo\Infrastructure\Mail;

use Kontor\Mail\Contracts\MailSenderInterface;

final class SimulatedMailSender implements MailSenderInterface
{
    public function send(
        string $fromAddress,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyText,
    ): void {
        // The demo exercises the complete outbound-mail pipeline and its
        // persistent history without delivering messages outside the site.
    }
}
