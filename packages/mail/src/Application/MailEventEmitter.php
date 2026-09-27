<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Mail\Domain\MailMessage;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

/**
 * Shared by `OutboundMailService`/`InboundMailService` — publishes
 * `mail.sent`/`mail.delivery_failed`/`mail.received` onto Core's real
 * event bus (kontor.md#21's canonical envelope), a genuine integration
 * (not deferred) that gives `kontor/automation` real new triggers to
 * react to, e.g. creating a follow-up task when a send fails. The
 * dispatcher is optional — a caller that doesn't wire one just gets no
 * events, useful for tests that don't care about them.
 */
final class MailEventEmitter
{
    public function __construct(
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function emit(string $eventName, MailMessage $message): void
    {
        if ($this->events === null) {
            return;
        }

        $this->events->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: $message->organizationId,
            entityType: 'mail_message',
            entityId: $message->uid->toString(),
            actorType: 'system',
            actorId: $message->createdBy !== null ? (string) $message->createdBy : null,
            data: ['subject' => $message->subject, 'status' => $message->status],
        ));
    }
}
