<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;

final class InvoiceRepositoryTest extends DatabaseTestCase
{
    private function repository(): InvoiceRepository
    {
        return new InvoiceRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $invoice = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR', dueDate: new \DateTimeImmutable('+14 days'));

        $repository->save($invoice);
        $found = $repository->find($invoice->uid->toString());

        $this->assertNotNull($found);
        $this->assertSame('draft', $found->status);
        $this->assertSame('invoice', $found->kind);
        $this->assertNull($found->creditedInvoiceUid);
    }

    public function test_find_returns_null_for_an_unknown_uid(): void
    {
        $this->assertNull($this->repository()->find('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
    }

    public function test_find_sent_past_due_only_returns_sent_invoices_with_a_past_due_date(): void
    {
        $repository = $this->repository();

        $sentPastDue = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR', dueDate: new \DateTimeImmutable('-2 days'));
        $sentPastDue->status = 'sent';
        $repository->save($sentPastDue);

        $sentNotDue = Invoice::create($this->organizationUid, 'contact', 'ct_02', 'EUR', dueDate: new \DateTimeImmutable('+2 days'));
        $sentNotDue->status = 'sent';
        $repository->save($sentNotDue);

        $issuedPastDue = Invoice::create($this->organizationUid, 'contact', 'ct_03', 'EUR', dueDate: new \DateTimeImmutable('-2 days'));
        $issuedPastDue->status = 'issued';
        $repository->save($issuedPastDue);

        $candidates = $repository->findSentPastDue($this->organizationUid, new \DateTimeImmutable('today'));

        $this->assertCount(1, $candidates);
        $this->assertSame($sentPastDue->uid->toString(), $candidates[0]->uid->toString());
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $invoice = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $repository->save($invoice);

        $repository->archive($invoice->uid->toString());
        $this->assertNotNull($repository->require($invoice->uid->toString()));

        $repository->restore($invoice->uid->toString());
        $this->assertNotNull($repository->find($invoice->uid->toString()));
    }

    public function test_find_by_order_and_matching_filters(): void
    {
        $repository = $this->repository();
        $invoice = Invoice::create(
            $this->organizationUid,
            'contact',
            'ct_northwind',
            'EUR',
            orderUid: 'so_northwind',
        );
        $invoice->number = 'INV-NORTHWIND';
        $invoice->status = 'issued';
        $repository->save($invoice);
        $repository->save(Invoice::create($this->organizationUid, 'contact', 'ct_other', 'EUR'));

        $this->assertSame(
            $invoice->uid->toString(),
            $repository->findByOrder('so_northwind')?->uid->toString()
        );
        $this->assertSame(
            [$invoice->uid->toString()],
            array_map(
                static fn (Invoice $item): string => $item->uid->toString(),
                $repository->findMatching($this->organizationUid, 'NORTHWIND', 'issued')
            )
        );
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'issued'));

        $repository->archive($invoice->uid->toString());

        $this->assertSame(0, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'issued'));
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            'NORTHWIND',
            'issued',
            true,
        ));
    }
}
