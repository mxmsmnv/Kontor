<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Mail\Contracts\InboundMailAdapterInterface;
use Kontor\Mail\Domain\MailMessage;
use Kontor\Mail\DTO\InboundMessage;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;

/**
 * The "inbound adapters" milestone's consumer side: `poll()` drains a
 * registered `InboundMailAdapterInterface` and persists every message it
 * hands back as a real `MailMessage` row.
 */
final class InboundMailService
{
    public function __construct(
        private readonly MailMessageRepository $messages,
        private readonly MailEventEmitter $events = new MailEventEmitter(),
    ) {
    }

    public function receive(string $organizationId, ?string $mailboxUid, InboundMessage $inbound): MailMessage
    {
        $message = MailMessage::inbound(
            $organizationId,
            $mailboxUid,
            $inbound->fromAddress,
            $inbound->toAddresses,
            $inbound->ccAddresses,
            $inbound->subject,
            $inbound->bodyText,
            $inbound->receivedAt,
        );

        $this->messages->save($message);
        $this->events->emit('mail.received', $message);

        return $message;
    }

    /**
     * @return MailMessage[]
     */
    public function poll(InboundMailAdapterInterface $adapter, string $organizationId, ?string $mailboxUid = null): array
    {
        return array_map(
            fn (InboundMessage $inbound) => $this->receive($organizationId, $mailboxUid, $inbound),
            $adapter->fetch(),
        );
    }
}
