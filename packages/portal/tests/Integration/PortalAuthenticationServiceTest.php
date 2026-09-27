<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Portal\Application\PortalAuthenticationFailedException;
use Kontor\Portal\Application\PortalAuthenticationService;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use Kontor\Portal\Migrations\Migration0001CreatePortalAccountsTable;

/**
 * The first real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core`, `kontor/api`, `kontor/graphql`,
 * `kontor/marketplace` and `kontor/mail` combined into this one package.
 */
final class PortalAuthenticationServiceTest extends DatabaseTestCase
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

    private function service(): PortalAuthenticationService
    {
        return new PortalAuthenticationService(new PortalAccountRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    private function contactUid(string $email): string
    {
        $contact = Contact::create($this->organizationUid, 'Portal', null, 'Customer', email: $email);
        (new ContactRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($contact);

        return $contact->uid->toString();
    }

    public function test_a_registered_account_can_authenticate_with_the_right_password(): void
    {
        $service = $this->service();
        $service->register($this->organizationUid, $this->contactUid('customer@example.com'), 'customer@example.com', 'correct-horse');

        $account = $service->authenticate($this->organizationUid, 'customer@example.com', 'correct-horse');

        $this->assertSame('customer@example.com', $account->email);
        $this->assertNotNull($account->lastLoginAt);
    }

    public function test_the_plaintext_password_is_never_stored(): void
    {
        $service = $this->service();
        $account = $service->register($this->organizationUid, $this->contactUid('customer@example.com'), 'customer@example.com', 'correct-horse');

        $this->assertNotSame('correct-horse', $account->passwordHash);
        $this->assertTrue(password_verify('correct-horse', $account->passwordHash));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $service = $this->service();
        $service->register($this->organizationUid, $this->contactUid('customer@example.com'), 'customer@example.com', 'correct-horse');

        $this->expectException(PortalAuthenticationFailedException::class);

        $service->authenticate($this->organizationUid, 'customer@example.com', 'wrong-password');
    }

    public function test_unknown_email_is_rejected(): void
    {
        $service = $this->service();

        $this->expectException(PortalAuthenticationFailedException::class);

        $service->authenticate($this->organizationUid, 'nobody@example.com', 'whatever');
    }

    public function test_a_disabled_account_cannot_authenticate(): void
    {
        $service = $this->service();
        $account = $service->register($this->organizationUid, $this->contactUid('customer@example.com'), 'customer@example.com', 'correct-horse');
        $account->disable();
        (new PortalAccountRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($account);

        $this->expectException(PortalAuthenticationFailedException::class);

        $service->authenticate($this->organizationUid, 'customer@example.com', 'correct-horse');
    }

    public function test_accounts_can_be_listed_for_their_organization(): void
    {
        $service = $this->service();
        $service->register($this->organizationUid, $this->contactUid('first@example.com'), 'first@example.com', 'correct-horse');
        $service->register($this->organizationUid, $this->contactUid('second@example.com'), 'second@example.com', 'correct-horse');

        $accounts = (new PortalAccountRepository(
            $this->pdo,
            new OrganizationRepository($this->pdo),
        ))->forOrganization($this->organizationUid);

        $this->assertCount(2, $accounts);
        $this->assertSame(
            ['second@example.com', 'first@example.com'],
            array_column($accounts, 'email'),
        );
    }

    public function test_registration_rejects_a_contact_outside_the_organization(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $other = \Kontor\Core\Domain\Organization::createDefault('DE', 'de', 'EUR');
        $organizations->save($other);
        $contact = Contact::create($other->uid->toString(), 'Other', null, 'Customer', email: 'other@example.com');
        (new ContactRepository($this->pdo, $organizations))->save($contact);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->register(
            $this->organizationUid,
            $contact->uid->toString(),
            'other@example.com',
            'correct-horse',
        );
    }
}
