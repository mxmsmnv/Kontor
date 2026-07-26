<?php

declare(strict_types=1);

namespace Kontor\Germany\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Germany\Application\StandardChartOfAccountsSeeder;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;

/**
 * The eighth real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core` — the last one in the entire kontor.md#36 build
 * plan.
 */
final class StandardChartOfAccountsSeederTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateAccountsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_ledger_accounts', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_seed_creates_every_standard_account(): void
    {
        $accounts = new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
        $seeder = new StandardChartOfAccountsSeeder(new ChartOfAccountsService($accounts));

        $seeded = $seeder->seed($this->organizationUid);

        $this->assertNotEmpty($seeded);
        $this->assertCount(count($seeded), $accounts->forOrganization($this->organizationUid));

        $cash = $accounts->findByCode($this->organizationUid, '1000');
        $this->assertNotNull($cash);
        $this->assertSame('asset', $cash->type);
    }

    public function test_seeding_twice_for_the_same_organization_is_idempotent(): void
    {
        $accounts = new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
        $seeder = new StandardChartOfAccountsSeeder(new ChartOfAccountsService($accounts));

        $first = $seeder->seed($this->organizationUid);
        $second = $seeder->seed($this->organizationUid);

        $this->assertSame(
            array_map(static fn ($account): string => $account->uid->toString(), $first),
            array_map(static fn ($account): string => $account->uid->toString(), $second),
        );
        $this->assertCount(count($first), $accounts->forOrganization($this->organizationUid));
    }

    public function test_conflicts_are_detected_before_any_german_accounts_are_created(): void
    {
        $accounts = new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
        $chart = new ChartOfAccountsService($accounts);
        $chart->createAccount($this->organizationUid, '1700', 'Conflicting tax account', 'asset', 'EUR');
        $seeder = new StandardChartOfAccountsSeeder($chart);

        try {
            $seeder->seed($this->organizationUid);
            $this->fail('Expected an incompatible account conflict.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertCount(1, $accounts->forOrganization($this->organizationUid));
        $this->assertNull($accounts->findByCode($this->organizationUid, '1000'));
    }

    public function test_seed_restores_a_compatible_archived_account(): void
    {
        $accounts = new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
        $chart = new ChartOfAccountsService($accounts);
        $cash = $chart->createAccount($this->organizationUid, '1000', 'Kasse', 'asset', 'EUR');
        $chart->archive($cash->uid->toString());

        $seeded = (new StandardChartOfAccountsSeeder($chart))->seed($this->organizationUid);

        $this->assertTrue($seeded[0]->isActive());
        $this->assertFalse($seeded[0]->isArchived());
    }
}
