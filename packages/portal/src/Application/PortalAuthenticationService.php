<?php

declare(strict_types=1);

namespace Kontor\Portal\Application;

use Kontor\Portal\Domain\PortalAccount;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;

/**
 * The "customer login" milestone. Uses PHP's own `password_hash()`/
 * `password_verify()` — no third-party auth library, and never a
 * ProcessWire staff user account, which is a distinct concept from a
 * customer's own portal login.
 */
final class PortalAuthenticationService
{
    public function __construct(
        private readonly PortalAccountRepository $accounts,
    ) {
    }

    public function register(
        string $organizationId,
        string $contactUid,
        string $email,
        string $plaintextPassword,
        ?int $createdBy = null,
    ): PortalAccount {
        $account = PortalAccount::create(
            $organizationId,
            $contactUid,
            $email,
            password_hash($plaintextPassword, PASSWORD_DEFAULT),
            $createdBy,
        );

        $this->accounts->save($account);

        return $account;
    }

    /**
     * @throws PortalAuthenticationFailedException
     */
    public function authenticate(string $organizationId, string $email, string $plaintextPassword): PortalAccount
    {
        $account = $this->accounts->findByEmail($organizationId, $email);

        if ($account === null || !$account->isActive()) {
            throw new PortalAuthenticationFailedException('Invalid email or password.');
        }

        if (!password_verify($plaintextPassword, $account->passwordHash)) {
            throw new PortalAuthenticationFailedException('Invalid email or password.');
        }

        $account->recordLogin();
        $this->accounts->save($account);

        return $account;
    }
}
