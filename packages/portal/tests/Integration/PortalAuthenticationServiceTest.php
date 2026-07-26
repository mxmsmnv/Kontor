<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
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
            new Migration0001CreatePortalAccountsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_portal_accounts', 'kontor_organizations', 'kontor_migrations'];
    }

    private function service(): PortalAuthenticationService
    {
        return new PortalAuthenticationService(new PortalAccountRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    public function test_a_registered_account_can_authenticate_with_the_right_password(): void
    {
        $service = $this->service();
        $service->register($this->organizationUid, 'contact_1', 'customer@example.com', 'correct-horse');

        $account = $service->authenticate($this->organizationUid, 'customer@example.com', 'correct-horse');

        $this->assertSame('customer@example.com', $account->email);
        $this->assertNotNull($account->lastLoginAt);
    }

    public function test_the_plaintext_password_is_never_stored(): void
    {
        $service = $this->service();
        $account = $service->register($this->organizationUid, 'contact_1', 'customer@example.com', 'correct-horse');

        $this->assertNotSame('correct-horse', $account->passwordHash);
        $this->assertTrue(password_verify('correct-horse', $account->passwordHash));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $service = $this->service();
        $service->register($this->organizationUid, 'contact_1', 'customer@example.com', 'correct-horse');

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
        $account = $service->register($this->organizationUid, 'contact_1', 'customer@example.com', 'correct-horse');
        $account->disable();
        (new PortalAccountRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($account);

        $this->expectException(PortalAuthenticationFailedException::class);

        $service->authenticate($this->organizationUid, 'customer@example.com', 'correct-horse');
    }
}
