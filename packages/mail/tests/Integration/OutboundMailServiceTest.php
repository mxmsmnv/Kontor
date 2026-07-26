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
        $this->assertSame('Connection refused', $message->error);
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
}
