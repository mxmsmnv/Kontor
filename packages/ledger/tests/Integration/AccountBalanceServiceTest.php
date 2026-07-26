<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Ledger\Application\AccountBalanceService;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;
use Kontor\Ledger\Migrations\Migration0002CreateEntriesTable;
use Kontor\Ledger\Migrations\Migration0003CreateLinesTable;
use Kontor\SDK\ValueObjects\Money;

final class AccountBalanceServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateAccountsTable(),
            new Migration0002CreateEntriesTable(),
            new Migration0003CreateLinesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return [
            'kontor_ledger_lines',
            'kontor_ledger_entries',
            'kontor_ledger_accounts',
            'kontor_organizations',
            'kontor_migrations',
        ];
    }

    public function test_balance_reflects_every_posted_entry(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $chartOfAccounts = new ChartOfAccountsService($accounts);
        $entryService = new LedgerEntryService($accounts, new LedgerEntryRepository($this->pdo, $organizations), new LedgerLineRepository($this->pdo, $organizations));

        $cash = $chartOfAccounts->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');
        $expense = $chartOfAccounts->createAccount($this->organizationUid, '5000', 'Bank Fees', 'expense', 'EUR');

        $entryService->record($this->organizationUid, 'Sale', new \DateTimeImmutable('2026-01-01'), [
            LedgerLineInput::debit($cash->uid->toString(), Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(10000, 'EUR')),
        ]);

        $entryService->record($this->organizationUid, 'Bank fee', new \DateTimeImmutable('2026-01-02'), [
            LedgerLineInput::debit($expense->uid->toString(), Money::ofMinor(500, 'EUR')),
            LedgerLineInput::credit($cash->uid->toString(), Money::ofMinor(500, 'EUR')),
        ]);

        $balances = new AccountBalanceService($accounts, new LedgerLineRepository($this->pdo, $organizations));

        $this->assertSame(9500, $balances->balance($cash->uid->toString())->amountMinor());
        $this->assertSame(10000, $balances->balance($revenue->uid->toString())->amountMinor());
        $this->assertSame(500, $balances->balance($expense->uid->toString())->amountMinor());
    }

    public function test_an_account_with_no_activity_has_a_zero_balance(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $account = (new ChartOfAccountsService($accounts))->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');

        $balance = (new AccountBalanceService($accounts, new LedgerLineRepository($this->pdo, $organizations)))->balance($account->uid->toString());

        $this->assertTrue($balance->isZero());
    }
}
