<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Mail\Application\MailEventEmitter;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\Mail\Contracts\MailSenderInterface;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\Mail\Migrations\Migration0002CreateMessagesTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;

/**
 * The fourth real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core`, after `kontor/api`, `kontor/graphql` and
 * `kontor/marketplace`. Uses a fake `MailSenderInterface` since this
 * sandbox has no outbound mail transport — see that interface's own doc
 * comment.
 */
final class OutboundMailServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0002CreateMessagesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_mail_messages', 'kontor_organizations', 'kontor_migrations'];
    }

    private function repository(): MailMessageRepository
    {
        return new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_a_successful_send_is_recorded_as_sent(): void
    {
        $sender = new class implements MailSenderInterface {
            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
            }
        };

        $service = new OutboundMailService($sender, $this->repository());
        $message = $service->send($this->organizationUid, null, 'sender@example.com', ['recipient@example.com'], [], 'Hi', 'Body');

        $this->assertSame('sent', $message->status);
        $this->assertSame('sent', $this->repository()->require($message->uid->toString())->status);
    }

    public function test_a_failed_send_is_recorded_as_failed_with_the_error(): void
    {
        $sender = new class implements MailSenderInterface {
            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
                throw new \RuntimeException('Connection refused');
            }
        };

        $service = new OutboundMailService($sender, $this->repository());
        $message = $service->send($this->organizationUid, null, 'sender@example.com', ['recipient@example.com'], [], 'Hi', 'Body');

        $this->assertSame('failed', $message->status);
        $this->assertSame('Mail transport failed.', $message->error);
    }

    public function test_a_history_row_exists_even_though_the_send_failed(): void
    {
        $sender = new class implements MailSenderInterface {
            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $service = new OutboundMailService($sender, $this->repository());
        $message = $service->send($this->organizationUid, null, 'sender@example.com', ['recipient@example.com'], [], 'Hi', 'Body');

        $stored = $this->repository()->find($message->uid->toString());
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isOutbound());
    }

    public function test_send_emits_mail_sent_event_on_success(): void
    {
        $sender = new class implements MailSenderInterface {
            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
            }
        };

        $dispatcher = new CapturingDispatcher();
        $service = new OutboundMailService($sender, $this->repository(), new MailEventEmitter($dispatcher));
        $service->send($this->organizationUid, null, 'sender@example.com', ['recipient@example.com'], [], 'Hi', 'Body');

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame('mail.sent', $dispatcher->events[0]->event);
    }

    public function test_send_emits_mail_delivery_failed_event_on_failure(): void
    {
        $sender = new class implements MailSenderInterface {
            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $dispatcher = new CapturingDispatcher();
        $service = new OutboundMailService($sender, $this->repository(), new MailEventEmitter($dispatcher));
        $service->send($this->organizationUid, null, 'sender@example.com', ['recipient@example.com'], [], 'Hi', 'Body');

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame('mail.delivery_failed', $dispatcher->events[0]->event);
    }

    public function test_timeout_can_be_retried_without_duplicating_or_leaking_sensitive_data(): void
    {
        $recipient = 'private-recipient@example.com';
        $body = 'Confidential acquisition details';
        $credential = 'smtp-password-super-secret';

        $sender = new class($credential) implements MailSenderInterface {
            public int $calls = 0;

            public function __construct(private readonly string $credential)
            {
            }

            public function send(string $fromAddress, array $toAddresses, array $ccAddresses, string $subject, string $bodyText): void
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new \RuntimeException(
                        "Timeout for {$toAddresses[0]} while sending {$bodyText} using {$this->credential}",
                    );
                }
            }
        };

        $dispatcher = new CapturingDispatcher();
        $repository = $this->repository();
        $service = new OutboundMailService($sender, $repository, new MailEventEmitter($dispatcher));

        $failed = $service->send(
            $this->organizationUid,
            null,
            'sender@example.com',
            [$recipient],
            [],
            'Delivery test',
            $body,
        );

        $storedFailure = $repository->require($failed->uid->toString());
        $this->assertSame('failed', $storedFailure->status);
        $this->assertSame('Mail transport failed.', $storedFailure->error);
        $this->assertStringNotContainsString($recipient, (string) $storedFailure->error);
        $this->assertStringNotContainsString($body, (string) $storedFailure->error);
        $this->assertStringNotContainsString($credential, (string) $storedFailure->error);

        $failureEvent = json_encode($dispatcher->events[0]->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($recipient, $failureEvent);
        $this->assertStringNotContainsString($body, $failureEvent);
        $this->assertStringNotContainsString($credential, $failureEvent);

        $retried = $service->retry($failed->uid->toString());

        $this->assertSame($failed->uid->toString(), $retried->uid->toString());
        $this->assertSame('sent', $retried->status);
        $this->assertNull($retried->error);
        $this->assertSame(2, $sender->calls);
        $this->assertCount(1, $repository->forOrganization($this->organizationUid));
        $this->assertSame(['mail.delivery_failed', 'mail.sent'], array_map(
            static fn ($event): string => $event->event,
            $dispatcher->events,
        ));

        $service->retry($failed->uid->toString());

        $this->assertSame(2, $sender->calls);
        $this->assertCount(1, $repository->forOrganization($this->organizationUid));
        $this->assertCount(2, $dispatcher->events);
    }
}
