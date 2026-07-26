<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Integration;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\Application\UnbalancedLedgerEntryException;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;
use Kontor\Ledger\Migrations\Migration0002CreateEntriesTable;
use Kontor\Ledger\Migrations\Migration0003CreateLinesTable;
use Kontor\SDK\ValueObjects\Money;
use RuntimeException;

/**
 * The seventh real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core`.
 */
final class LedgerEntryServiceTest extends DatabaseTestCase
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

    private function accounts(): AccountRepository
    {
        return new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    private function service(): LedgerEntryService
    {
        $organizations = new OrganizationRepository($this->pdo);

        return new LedgerEntryService(
            $this->accounts(),
            new LedgerEntryRepository($this->pdo, $organizations),
            new LedgerLineRepository($this->pdo, $organizations),
        );
    }

    public function test_recording_a_balanced_entry_persists_the_entry_and_its_lines(): void
    {
        $chartOfAccounts = new ChartOfAccountsService($this->accounts());
        $cash = $chartOfAccounts->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');

        $entry = $this->service()->record(
            $this->organizationUid,
            'Cash sale',
            new \DateTimeImmutable('2026-01-01'),
            [
                LedgerLineInput::debit($cash->uid->toString(), Money::ofMinor(10000, 'EUR')),
                LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(10000, 'EUR')),
            ],
        );

        $lines = new LedgerLineRepository($this->pdo, new OrganizationRepository($this->pdo));
        $this->assertCount(1, $lines->forAccount($cash->uid->toString()));
        $this->assertCount(1, $lines->forAccount($revenue->uid->toString()));

        $entries = new LedgerEntryRepository($this->pdo, new OrganizationRepository($this->pdo));
        $this->assertSame('Cash sale', $entries->require($entry->uid->toString())->description);
    }

    public function test_an_unbalanced_entry_is_rejected_before_anything_is_written(): void
    {
        $chartOfAccounts = new ChartOfAccountsService($this->accounts());
        $cash = $chartOfAccounts->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');

        try {
            $this->service()->record(
                $this->organizationUid,
                'Bad entry',
                new \DateTimeImmutable('2026-01-01'),
                [
                    LedgerLineInput::debit($cash->uid->toString(), Money::ofMinor(10000, 'EUR')),
                    LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(9000, 'EUR')),
                ],
            );
            $this->fail('Expected an UnbalancedLedgerEntryException.');
        } catch (UnbalancedLedgerEntryException) {
            // expected
        }

        $entries = new LedgerEntryRepository($this->pdo, new OrganizationRepository($this->pdo));
        $this->assertSame([], $entries->forOrganization($this->organizationUid));
    }

    public function test_an_account_from_another_organization_is_rejected(): void
    {
        $otherOrganization = Organization::createDefault('US', 'en', 'USD');
        (new OrganizationRepository($this->pdo))->save($otherOrganization);

        $chartOfAccounts = new ChartOfAccountsService($this->accounts());
        $foreignCash = $chartOfAccounts->createAccount($otherOrganization->uid->toString(), '1000', 'Cash', 'asset', 'USD');
        $revenue = $chartOfAccounts->createAccount($this->organizationUid, '4000', 'Sales Revenue', 'revenue', 'EUR');

        $this->expectException(RuntimeException::class);

        $this->service()->record(
            $this->organizationUid,
            'Cross-org attempt',
            new \DateTimeImmutable('2026-01-01'),
            [
                LedgerLineInput::debit($foreignCash->uid->toString(), Money::ofMinor(100, 'USD')),
                LedgerLineInput::credit($revenue->uid->toString(), Money::ofMinor(100, 'USD')),
            ],
        );
    }
}
