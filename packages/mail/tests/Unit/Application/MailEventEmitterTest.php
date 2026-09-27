<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Application;

use Kontor\Mail\Application\MailEventEmitter;
use Kontor\Mail\Domain\MailMessage;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;
use PHPUnit\Framework\TestCase;

final class MailEventEmitterTest extends TestCase
{
    public function test_emits_a_kontor_event_with_the_message_details(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var KontorEvent[] */
            public array $events = [];

            public function dispatch(KontorEvent $event): void
            {
                $this->events[] = $event;
            }

            public function subscribe(string $eventName, callable|string $listener, int $priority = 0): void
            {
            }
        };

        $message = MailMessage::outbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body', createdBy: 7);
        $message->markSent();

        (new MailEventEmitter($dispatcher))->emit('mail.sent', $message);

        $this->assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        $this->assertSame('mail.sent', $event->event);
        $this->assertSame('mail_message', $event->entityType);
        $this->assertSame($message->uid->toString(), $event->entityId);
        $this->assertSame('7', $event->actorId);
        $this->assertSame('Hi', $event->data['subject']);
        $this->assertSame('sent', $event->data['status']);
    }

    public function test_without_a_dispatcher_emit_is_a_silent_no_op(): void
    {
        $message = MailMessage::outbound('org_1', null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');

        (new MailEventEmitter(null))->emit('mail.sent', $message);

        $this->addToAssertionCount(1);
    }
}
