<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Domain\Organization;
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
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $this->pdo->prepare(
            'INSERT INTO kontor_portal_accounts
                (uid, organization_id, contact_uid, email, password_hash, status, created_at, updated_at, version)
             VALUES (:uid, :organization_id, :contact_uid, :email, :password_hash, :status, NOW(6), NOW(6), 1)'
        )->execute([
            'uid' => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            'organization_id' => $organizationId,
            'contact_uid' => 'missing_contact_uid',
            'email' => 'ghost@example.com',
            'password_hash' => 'hash',
            'status' => 'active',
        ]);

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

    public function test_warns_about_a_legacy_cross_organization_contact_link(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $other = Organization::createDefault('DE', 'de', 'EUR');
        $organizations->save($other);
        $contact = Contact::create($other->uid->toString(), 'Other', null, 'Customer', email: 'other@example.com');
        $this->contacts()->save($contact);

        $organizationId = $organizations->internalIdOf($this->organizationUid);
        $this->pdo->prepare(
            'INSERT INTO kontor_portal_accounts
                (uid, organization_id, contact_uid, email, password_hash, status, created_at, updated_at, version)
             VALUES (:uid, :organization_id, :contact_uid, :email, :password_hash, :status, NOW(6), NOW(6), 1)'
        )->execute([
            'uid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'organization_id' => $organizationId,
            'contact_uid' => $contact->uid->toString(),
            'email' => 'legacy@example.com',
            'password_hash' => 'hash',
            'status' => 'active',
        ]);

        $result = (new PortalHealthCheck($this->accounts(), $this->contacts()))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['orphanedAccounts']);
    }
}
