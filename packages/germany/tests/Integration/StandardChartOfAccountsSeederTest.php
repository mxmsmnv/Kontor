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

    public function test_seeding_twice_for_the_same_organization_fails_on_duplicate_codes(): void
    {
        $accounts = new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
        $seeder = new StandardChartOfAccountsSeeder(new ChartOfAccountsService($accounts));

        $seeder->seed($this->organizationUid);

        $this->expectException(\InvalidArgumentException::class);

        $seeder->seed($this->organizationUid);
    }
}
