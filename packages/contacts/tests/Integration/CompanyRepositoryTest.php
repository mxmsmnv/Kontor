<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CompanyRepositoryTest extends DatabaseTestCase
{
    private function repository(): CompanyRepository
    {
        return new CompanyRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $company = Company::create($this->organizationUid, 'Acme GmbH', vatNumber: 'DE123456789');

        $repository->save($company);
        $found = $repository->find($company->uid->toString());

        $this->assertSame('Acme GmbH', $found->legalName);
        $this->assertSame('DE123456789', $found->vatNumber);
    }

    public function test_find_by_vat_number_and_email(): void
    {
        $repository = $this->repository();
        $company = Company::create($this->organizationUid, 'Acme GmbH', vatNumber: 'DE123456789', email: 'billing@acme.test');
        $repository->save($company);

        $this->assertSame($company->uid->toString(), $repository->findByVatNumber($this->organizationUid, 'DE123456789')->uid->toString());
        $this->assertSame($company->uid->toString(), $repository->findByEmail($this->organizationUid, 'billing@acme.test')->uid->toString());
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $company = Company::create($this->organizationUid, 'Acme GmbH');
        $repository->save($company);

        $repository->archive($company->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_companies')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $repository->restore($company->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_companies')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }
}
