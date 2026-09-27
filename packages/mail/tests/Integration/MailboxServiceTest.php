<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Mail\Application\MailboxService;
use Kontor\Mail\Infrastructure\Persistence\MailboxRepository;
use Kontor\Mail\Migrations\Migration0001CreateMailboxesTable;

final class MailboxServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateMailboxesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_mail_mailboxes', 'kontor_organizations', 'kontor_migrations'];
    }

    private function repository(): MailboxRepository
    {
        return new MailboxRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_open_creates_an_active_mailbox(): void
    {
        $service = new MailboxService($this->repository());

        $mailbox = $service->open($this->organizationUid, 'Support', 'support@example.com');

        $this->assertTrue($mailbox->isActive());
        $this->assertSame('Support', $this->repository()->require($mailbox->uid->toString())->name);
    }

    public function test_archive_and_restore(): void
    {
        $service = new MailboxService($this->repository());
        $mailbox = $service->open($this->organizationUid, 'Sales', 'sales@example.com');

        $service->archive($mailbox->uid->toString());
        $row = $this->pdo->query("SELECT archived_at FROM kontor_mail_mailboxes WHERE uid = '{$mailbox->uid->toString()}'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $service->restore($mailbox->uid->toString());
        $row = $this->pdo->query("SELECT archived_at FROM kontor_mail_mailboxes WHERE uid = '{$mailbox->uid->toString()}'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }
}
