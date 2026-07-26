<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Mail\Application\InboundMailService;
use Kontor\Mail\Application\MailEventEmitter;
use Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\Mail\Migrations\Migration0002CreateMessagesTable;

final class InboundMailServiceTest extends DatabaseTestCase
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

    public function test_polling_an_adapter_persists_every_fetched_message(): void
    {
        $adapter = new RawEmailForwardAdapter();
        $adapter->pushRaw("From: customer@example.com\nTo: support@example.com\nSubject: Help\n\nI need help.");
        $adapter->pushRaw("From: another@example.com\nSubject: Question\n\nAnother message.");

        $service = new InboundMailService($this->repository());
        $received = $service->poll($adapter, $this->organizationUid, mailboxUid: null);

        $this->assertCount(2, $received);
        $this->assertSame('received', $received[0]->status);
        $this->assertFalse($received[0]->isOutbound());

        $stored = $this->repository()->forOrganization($this->organizationUid);
        $this->assertCount(2, $stored);
    }

    public function test_polling_an_empty_adapter_persists_nothing(): void
    {
        $service = new InboundMailService($this->repository());
        $received = $service->poll(new RawEmailForwardAdapter(), $this->organizationUid);

        $this->assertSame([], $received);
    }

    public function test_receive_emits_mail_received_event(): void
    {
        $adapter = new RawEmailForwardAdapter();
        $adapter->pushRaw("From: customer@example.com\nSubject: Help\n\nBody");

        $dispatcher = new CapturingDispatcher();
        $service = new InboundMailService($this->repository(), new MailEventEmitter($dispatcher));
        $service->poll($adapter, $this->organizationUid);

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame('mail.received', $dispatcher->events[0]->event);
    }
}
