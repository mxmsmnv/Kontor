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
        $own = Quotation::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $own->status = 'issued';
        $own->number = 'E2E-001';
        $salesRepository->save($own);
        $other = Quotation::create($this->organizationUid, 'contact', 'contact_2', 'EUR');
        $other->status = 'issued';
        $other->number = 'E2E-002';
        $salesRepository->save($other);

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotations = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertCount(1, $quotations);
        $this->assertSame('contact_1', $quotations[0]->customerUid);
    }

    public function test_a_company_customer_quotation_is_not_visible_to_a_contact(): void
    {
        $salesRepository = new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $own = Quotation::create($this->organizationUid, 'company', 'contact_1', 'EUR');
        $own->status = 'issued';
        $own->number = 'E2E-001';
        $salesRepository->save($own);

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotations = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertSame([], $quotations);
    }

    public function test_only_issued_and_unarchived_own_documents_are_visible_for_quotation(): void
    {
        $salesRepository = new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $quotation = Quotation::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $salesRepository->save($quotation);

        $portalRepository = new CustomerQuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $uid = $quotation->uid->toString();
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));

        $quotation->status = 'cancelled';
        $salesRepository->save($quotation);
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));

        $quotation->status = 'issued';
        $quotation->number = 'E2E-001';
        $salesRepository->save($quotation);

        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_2', $uid));
        $this->assertNotNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));

        $salesRepository->archive($uid);
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));
    }
}
