<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Portal\Domain\PortalAccount;
use Kontor\Portal\Health\PortalHealthCheck;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use Kontor\Portal\Migrations\Migration0001CreatePortalAccountsTable;

final class PortalHealthCheckTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateContactsTable(),
            new Migration0001CreatePortalAccountsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_portal_accounts', 'kontor_contacts', 'kontor_organizations', 'kontor_migrations'];
    }

    private function accounts(): PortalAccountRepository
    {
        return new PortalAccountRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    private function contacts(): ContactRepository
    {
        return new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_ok_when_every_account_has_a_valid_contact(): void
    {
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $this->contacts()->save($contact);
        $this->accounts()->save(PortalAccount::create($this->organizationUid, $contact->uid->toString(), 'ada@example.com', 'hash'));

        $result = (new PortalHealthCheck($this->accounts(), $this->contacts()))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['activeAccounts']);
    }

    public function test_warns_when_an_account_references_a_missing_contact(): void
    {
        $this->accounts()->save(PortalAccount::create($this->organizationUid, 'missing_contact_uid', 'ghost@example.com', 'hash'));

        $result = (new PortalHealthCheck($this->accounts(), $this->contacts()))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['orphanedAccounts']);
    }

    public function test_no_accounts_is_still_ok(): void
    {
        $result = (new PortalHealthCheck($this->accounts(), $this->contacts()))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(0, $result->details['activeAccounts']);
    }
}
