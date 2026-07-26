<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Integration;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;

final class ChartOfAccountsServiceTest extends DatabaseTestCase
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

    private function accounts(): AccountRepository
    {
        return new AccountRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_create_account_persists_it(): void
    {
        $service = new ChartOfAccountsService($this->accounts());

        $account = $service->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');

        $this->assertSame('Cash', $this->accounts()->require($account->uid->toString())->name);
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        $service = new ChartOfAccountsService($this->accounts());
        $service->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');

        $this->expectException(InvalidArgumentException::class);

        $service->createAccount($this->organizationUid, '1000', 'Duplicate', 'asset', 'EUR');
    }

    public function test_archive_and_restore(): void
    {
        $service = new ChartOfAccountsService($this->accounts());
        $account = $service->createAccount($this->organizationUid, '1000', 'Cash', 'asset', 'EUR');

        $service->archive($account->uid->toString());
        $row = $this->pdo->query("SELECT archived_at FROM kontor_ledger_accounts WHERE uid = '{$account->uid->toString()}'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);
        $this->assertTrue($this->accounts()->require($account->uid->toString())->isArchived());
        $this->assertFalse($this->accounts()->require($account->uid->toString())->isActive());

        $service->restore($account->uid->toString());
        $row = $this->pdo->query("SELECT archived_at FROM kontor_ledger_accounts WHERE uid = '{$account->uid->toString()}'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
        $this->assertFalse($this->accounts()->require($account->uid->toString())->isArchived());
    }
}
