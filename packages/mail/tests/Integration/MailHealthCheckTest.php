<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Mail\Domain\MailMessage;
use Kontor\Mail\Health\MailHealthCheck;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\Mail\Migrations\Migration0002CreateMessagesTable;

final class MailHealthCheckTest extends DatabaseTestCase
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

    public function test_ok_when_nothing_failed_or_unassigned(): void
    {
        $result = (new MailHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_warns_on_a_failed_outbound_message(): void
    {
        $repository = new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
        $message = MailMessage::outbound($this->organizationUid, null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');
        $message->markFailed('boom');
        $repository->save($message);

        $result = (new MailHealthCheck($this->pdo))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['failedOutbound']);
    }

    public function test_warns_on_an_unassigned_inbound_message(): void
    {
        $repository = new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
        $message = MailMessage::inbound($this->organizationUid, null, 'a@example.com', [], [], 'Hi', 'Body', new \DateTimeImmutable());
        $repository->save($message);

        $result = (new MailHealthCheck($this->pdo))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['unassignedInbound']);
    }

    public function test_no_warning_once_an_inbound_message_is_assigned(): void
    {
        $repository = new MailMessageRepository($this->pdo, new OrganizationRepository($this->pdo));
        $message = MailMessage::inbound($this->organizationUid, null, 'a@example.com', [], [], 'Hi', 'Body', new \DateTimeImmutable());
        $message->assignTo(1);
        $repository->save($message);

        $result = (new MailHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
    }
}
