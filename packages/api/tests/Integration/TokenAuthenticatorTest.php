<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Integration;

use Kontor\API\Application\AuthenticationFailedException;
use Kontor\API\Application\TokenAuthenticator;
use Kontor\API\Infrastructure\Persistence\ApiTokenRepository;
use Kontor\API\Migrations\Migration0001CreateApiTokensTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;

final class TokenAuthenticatorTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateApiTokensTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_api_tokens', 'kontor_organizations', 'kontor_migrations'];
    }

    private function authenticator(): TokenAuthenticator
    {
        return new TokenAuthenticator(new ApiTokenRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    public function test_a_freshly_issued_token_authenticates(): void
    {
        $authenticator = $this->authenticator();
        $issued = $authenticator->issue($this->organizationUid, 'CI token');

        $token = $authenticator->authenticate($issued->plaintext);

        $this->assertSame($this->organizationUid, $token->organizationId);
        $this->assertNotNull($token->lastUsedAt);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $authenticator = $this->authenticator();

        $this->expectException(AuthenticationFailedException::class);

        $authenticator->authenticate('kontor_not-a-real-token');
    }

    public function test_a_revoked_token_is_rejected(): void
    {
        $authenticator = $this->authenticator();
        $issued = $authenticator->issue($this->organizationUid, 'CI token');

        $authenticator->revoke($issued->token->uid->toString());

        $this->expectException(AuthenticationFailedException::class);

        $authenticator->authenticate($issued->plaintext);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $authenticator = $this->authenticator();
        $issued = $authenticator->issue($this->organizationUid, 'CI token', expiresAt: new \DateTimeImmutable('-1 minute'));

        $this->expectException(AuthenticationFailedException::class);

        $authenticator->authenticate($issued->plaintext);
    }

    public function test_a_scoped_token_rejects_a_missing_scope(): void
    {
        $authenticator = $this->authenticator();
        $issued = $authenticator->issue($this->organizationUid, 'CI token', scopes: ['invoices:read']);

        $this->expectException(AuthenticationFailedException::class);

        $authenticator->authenticate($issued->plaintext, requiredScope: 'invoices:write');
    }

    public function test_a_scoped_token_accepts_a_matching_scope(): void
    {
        $authenticator = $this->authenticator();
        $issued = $authenticator->issue($this->organizationUid, 'CI token', scopes: ['invoices:read']);

        $token = $authenticator->authenticate($issued->plaintext, requiredScope: 'invoices:read');

        $this->assertTrue($token->hasScope('invoices:read'));
    }
}
