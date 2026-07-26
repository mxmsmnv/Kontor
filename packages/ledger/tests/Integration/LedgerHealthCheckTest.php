<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Health\LedgerHealthCheck;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;
use Kontor\Ledger\Migrations\Migration0002CreateEntriesTable;
use Kontor\Ledger\Migrations\Migration0003CreateLinesTable;
use Kontor\SDK\ValueObjects\Money;

final class LedgerHealthCheckTest extends DatabaseTestCase
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

    public function test_ok_with_no_entries(): void
    {
        $result = (new LedgerHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_ok_after_a_balanced_entry(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $chartOfAccounts = new ChartOfAccountsService($accounts);
        $entryService = new LedgerEntryService($accounts, new LedgerEntryRepository($this->pdo, $organizations), new LedgerLineRepository($this->pdo, $organizations));

        $cash = $chartOfAccounts->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');

        $entryService->record($this->organizationUid, 'Sale', new \DateTimeImmutable('2026-01-01'), [
            LedgerLineInput::debit($cash->uid->toString(), Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(10000, 'EUR')),
        ]);

        $result = (new LedgerHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_critical_when_lines_are_corrupted_directly_in_the_database(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $chartOfAccounts = new ChartOfAccountsService($accounts);
        $entryService = new LedgerEntryService($accounts, new LedgerEntryRepository($this->pdo, $organizations), new LedgerLineRepository($this->pdo, $organizations));

        $cash = $chartOfAccounts->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');

        $entry = $entryService->record($this->organizationUid, 'Sale', new \DateTimeImmutable('2026-01-01'), [
            LedgerLineInput::debit($cash->uid->toString(), Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(10000, 'EUR')),
        ]);

        // Simulate corruption bypassing the service layer entirely.
        $this->pdo->exec("UPDATE kontor_ledger_lines SET credit_minor = 9000 WHERE entry_uid = '{$entry->uid->toString()}' AND credit_minor > 0");

        $result = (new LedgerHealthCheck($this->pdo))->run();

        $this->assertSame('critical', $result->status);
        $this->assertSame(1, $result->details['unbalancedEntries']);
    }
}
