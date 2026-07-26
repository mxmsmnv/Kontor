<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Mail\Contracts\MailSenderInterface;
use Kontor\Mail\Domain\MailMessage;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;

/**
 * The "outbound history" milestone: every send attempt is recorded, not
 * just successful ones. The message is persisted as soon as it's built
 * (status `queued`), before the transport is even attempted, so history
 * exists even if the process dies mid-send; the final `sent`/`failed`
 * status is then saved as a second write to the same row.
 */
final class OutboundMailService
{
    public function __construct(
        private readonly MailSenderInterface $sender,
        private readonly MailMessageRepository $messages,
        private readonly MailEventEmitter $events = new MailEventEmitter(),
    ) {
    }

    /**
     * @param string[] $toAddresses
     * @param string[] $ccAddresses
     */
    public function send(
        string $organizationId,
        ?string $mailboxUid,
        string $fromAddress,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyText,
        ?int $createdBy = null,
    ): MailMessage {
        $message = MailMessage::outbound($organizationId, $mailboxUid, $fromAddress, $toAddresses, $ccAddresses, $subject, $bodyText, $createdBy);
        $this->messages->save($message);

        try {
            $this->sender->send($fromAddress, $toAddresses, $ccAddresses, $subject, $bodyText);
            $message->markSent();
            $this->events->emit('mail.sent', $message);
        } catch (\Throwable $e) {
            $message->markFailed($e->getMessage());
            $this->events->emit('mail.delivery_failed', $message);
        }

        $this->messages->save($message);

        return $message;
    }
}
