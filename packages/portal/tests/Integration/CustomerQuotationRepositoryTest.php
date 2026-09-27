<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Portal\Infrastructure\Persistence\CustomerQuotationRepository;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;
use Kontor\Sales\Migrations\Migration0001CreateQuotationsTable;

final class CustomerQuotationRepositoryTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateQuotationsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_sales_quotations', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_for_contact_only_returns_that_contacts_own_quotations(): void
    {
        $salesRepository = new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $salesRepository->save(Quotation::create($this->organizationUid, 'contact', 'contact_1', 'EUR'));
        $salesRepository->save(Quotation::create($this->organizationUid, 'contact', 'contact_2', 'EUR'));

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotations = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertCount(1, $quotations);
        $this->assertSame('contact_1', $quotations[0]->customerUid);
    }

    public function test_a_company_customer_quotation_is_not_visible_to_a_contact(): void
    {
        $salesRepository = new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $salesRepository->save(Quotation::create($this->organizationUid, 'company', 'contact_1', 'EUR'));

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotations = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertSame([], $quotations);
    }

    public function test_find_owned_returns_null_for_someone_elses_quotation(): void
    {
        $salesRepository = new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotation = Quotation::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $salesRepository->save($quotation);

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));

        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_2', $quotation->uid->toString()));
        $this->assertNotNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $quotation->uid->toString()));
    }
}
