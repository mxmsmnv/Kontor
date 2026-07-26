<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Invoices\Migrations\Migration0001CreateInvoicesTable;
use Kontor\Portal\Infrastructure\Persistence\CustomerInvoiceRepository;

final class CustomerInvoiceRepositoryTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateInvoicesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_invoices', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_for_contact_only_returns_that_contacts_own_invoices(): void
    {
        $invoicesRepository = new InvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $invoicesRepository->save(Invoice::create($this->organizationUid, 'contact', 'contact_1', 'EUR'));
        $invoicesRepository->save(Invoice::create($this->organizationUid, 'contact', 'contact_2', 'EUR'));

        $portalRepository = new CustomerInvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $invoices = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertCount(1, $invoices);
        $this->assertSame('contact_1', $invoices[0]->customerUid);
    }

    public function test_find_owned_returns_null_for_someone_elses_invoice(): void
    {
        $invoicesRepository = new InvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $invoice = Invoice::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $invoicesRepository->save($invoice);

        $portalRepository = new CustomerInvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));

        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_2', $invoice->uid->toString()));
        $this->assertNotNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $invoice->uid->toString()));
    }
}
