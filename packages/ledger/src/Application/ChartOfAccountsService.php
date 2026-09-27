<?php

declare(strict_types=1);

namespace Kontor\Ledger\Application;

use InvalidArgumentException;
use Kontor\Ledger\Domain\Account;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;

/**
 * The "chart of accounts" milestone.
 */
final class ChartOfAccountsService
{
    public function __construct(
        private readonly AccountRepository $accounts,
    ) {
    }

    public function createAccount(
        string $organizationId,
        string $code,
        string $name,
        string $type,
        string $currencyCode,
        ?string $parentUid = null,
        ?int $createdBy = null,
    ): Account {
        if ($this->accounts->findByCode($organizationId, $code) !== null) {
            throw new InvalidArgumentException("Account code \"{$code}\" is already in use.");
        }

        $account = Account::create($organizationId, $code, $name, $type, $currencyCode, $parentUid, $createdBy);
        $this->accounts->save($account);

        return $account;
    }

    public function findByCode(string $organizationId, string $code): ?Account
    {
        return $this->accounts->findByCode($organizationId, $code);
    }

    public function archive(string $accountUid): void
    {
        $this->accounts->archive($accountUid);
    }

    public function restore(string $accountUid): void
    {
        $this->accounts->restore($accountUid);
    }
}
