<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Health\InvoicesHealthCheck;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;

final class InvoicesHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        $repository = new InvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));

        $invoice = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $invoice->status = 'overdue';
        $repository->save($invoice);

        $result = (new InvoicesHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['invoices']);
        $this->assertSame(1, $result->details['overdue']);
    }
}
