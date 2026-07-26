<?php

declare(strict_types=1);

namespace Kontor\Mail\Infrastructure\Mail;

use Kontor\Mail\Contracts\MailSenderInterface;
use RuntimeException;

/**
 * The real `MailSenderInterface` implementation, used in production —
 * PHP's own built-in `mail()` rather than a third-party SMTP library, the
 * same "avoid a heavy dependency" call made throughout this monorepo;
 * swappable for a real SMTP client later since callers only ever depend
 * on the interface. Tests use a fake instead (see the interface's own
 * doc comment) since this sandbox has no outbound mail transport to
 * verify against.
 */
final class NativeMailSender implements MailSenderInterface
{
    public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
    {
        $headers = "From: {$fromAddress}\r\n";

        if ($ccAddresses !== []) {
            $headers .= 'Cc: '.implode(', ', $ccAddresses)."\r\n";
        }

        $sent = mail(implode(', ', $toAddresses), $subject, $bodyText, $headers);

        if (!$sent) {
            throw new RuntimeException('Failed to send mail to '.implode(', ', $toAddresses).'.');
        }
    }
}
