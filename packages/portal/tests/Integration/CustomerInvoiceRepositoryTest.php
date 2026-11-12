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
        $own = Invoice::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $own->status = 'issued';
        $own->number = 'E2E-001';
        $invoicesRepository->save($own);
        $other = Invoice::create($this->organizationUid, 'contact', 'contact_2', 'EUR');
        $other->status = 'issued';
        $other->number = 'E2E-002';
        $invoicesRepository->save($other);

        $portalRepository = new CustomerInvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $invoices = $portalRepository->forContact($this->organizationUid, 'contact_1');

        $this->assertCount(1, $invoices);
        $this->assertSame('contact_1', $invoices[0]->customerUid);
    }

    public function test_only_issued_and_unarchived_own_documents_are_visible_for_invoice(): void
    {
        $invoicesRepository = new InvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $invoice = Invoice::create($this->organizationUid, 'contact', 'contact_1', 'EUR');
        $invoicesRepository->save($invoice);

        $portalRepository = new CustomerInvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
        $uid = $invoice->uid->toString();
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));

        $invoice->status = 'cancelled';
        $invoicesRepository->save($invoice);
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));

        $invoice->status = 'issued';
        $invoice->number = 'E2E-001';
        $invoicesRepository->save($invoice);

        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_2', $uid));
        $this->assertNotNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));

        $invoicesRepository->archive($uid);
        $this->assertNull($portalRepository->findOwned($this->organizationUid, 'contact_1', $uid));
        $this->assertSame([], $portalRepository->forContact($this->organizationUid, 'contact_1'));
    }
}
