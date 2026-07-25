<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Projects\Application\ProjectInvoicingService;
use Kontor\Projects\Application\TimeTrackingService;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Infrastructure\Persistence\BillableItemRepository;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

final class ProjectInvoicingServiceTest extends DatabaseTestCase
{
    private ProjectRepository $projects;
    private TimeEntryRepository $timeEntries;
    private BillableItemRepository $billableItems;
    private TimeTrackingService $timeTracking;
    private ProjectInvoicingService $invoicing;
    private InvoiceRepository $invoices;
    private DocumentLineRepository $documentLines;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->projects = new ProjectRepository($this->pdo, $organizations);
        $this->timeEntries = new TimeEntryRepository($this->pdo, $organizations);
        $this->billableItems = new BillableItemRepository($this->pdo, $organizations);
        $this->timeTracking = new TimeTrackingService($this->timeEntries);
        $this->invoices = new InvoiceRepository($this->pdo, $organizations);
        $this->documentLines = new DocumentLineRepository($this->pdo, $organizations);

        $this->invoicing = new ProjectInvoicingService(
            $this->pdo, $this->projects, $this->timeEntries, $this->billableItems, $this->invoices, $this->documentLines,
        );
    }

    private function billableProject(?int $defaultRateMinor = 10000): Project
    {
        $project = Project::create(
            $this->organizationUid, 'PRJ-1', 'Website Redesign', customerType: 'contact', customerUid: 'ct_01',
            defaultHourlyRateMinor: $defaultRateMinor, currencyCode: 'EUR',
        );
        $this->projects->save($project);

        return $project;
    }

    public function test_generate_invoice_from_time_entries_and_billable_items(): void
    {
        $project = $this->billableProject();

        $entry = $this->timeTracking->logManual(
            $this->organizationUid, $project->uid->toString(), 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 11:00:00'),
            description: 'Design work',
        );

        $item = BillableItem::create($this->organizationUid, $project->uid->toString(), 'Stock photo license', 1.0, Money::ofMinor(5000, 'EUR'));
        $this->billableItems->save($item);

        $invoice = $this->invoicing->generateInvoice($project->uid->toString());

        $this->assertSame('draft', $invoice->status);
        $this->assertSame('contact', $invoice->customerType);
        $this->assertSame('ct_01', $invoice->customerUid);
        // 2 hours * 100.00 EUR = 200.00 EUR, plus 50.00 EUR item = 250.00 EUR
        $this->assertSame(25000, $invoice->total->amountMinor());

        $lines = $this->documentLines->forDocument('invoice', $invoice->uid->toString());
        $this->assertCount(2, $lines);

        $this->assertTrue($this->timeEntries->require($entry->uid->toString())->isInvoiced());
        $this->assertTrue($this->billableItems->require($item->uid->toString())->isInvoiced());
    }

    public function test_generating_again_does_not_double_bill_already_invoiced_items(): void
    {
        $project = $this->billableProject();
        $this->timeTracking->logManual(
            $this->organizationUid, $project->uid->toString(), 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $this->invoicing->generateInvoice($project->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->invoicing->generateInvoice($project->uid->toString());
    }

    public function test_refuses_a_project_with_no_customer(): void
    {
        $project = Project::create($this->organizationUid, 'PRJ-1', 'Internal tooling', currencyCode: 'EUR');
        $this->projects->save($project);
        $this->timeTracking->logManual(
            $this->organizationUid, $project->uid->toString(), 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $this->expectException(\RuntimeException::class);
        $this->invoicing->generateInvoice($project->uid->toString());
    }

    public function test_refuses_a_time_entry_with_no_rate_and_no_project_default(): void
    {
        $project = $this->billableProject(defaultRateMinor: null);
        $this->timeTracking->logManual(
            $this->organizationUid, $project->uid->toString(), 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $this->expectException(\RuntimeException::class);
        $this->invoicing->generateInvoice($project->uid->toString());
    }

    public function test_non_billable_time_is_excluded(): void
    {
        $project = $this->billableProject();
        $this->timeTracking->logManual(
            $this->organizationUid, $project->uid->toString(), 5,
            new \DateTimeImmutable('2026-01-01 09:00:00'), new \DateTimeImmutable('2026-01-01 10:00:00'),
            billable: false,
        );

        $this->expectException(\RuntimeException::class);
        $this->invoicing->generateInvoice($project->uid->toString());
    }
}
