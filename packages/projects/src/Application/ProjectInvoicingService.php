<?php

declare(strict_types=1);

namespace Kontor\Projects\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Projects\Infrastructure\Persistence\BillableItemRepository;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;
use Kontor\Projects\Infrastructure\Persistence\TimeEntryRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use RuntimeException;

/**
 * The "invoicing integration" milestone — not deferred here, the same
 * choice kontor/purchasing made for its own "inventory integration"
 * milestone: generateInvoice() actually creates a real draft
 * kontor/invoices Invoice from a project's uninvoiced billable time
 * entries and billable items, reusing kontor/sales' DocumentLine for the
 * lines (the same shared table kontor/invoices itself already reuses).
 * The invoice is left in 'draft' — issuing it is a deliberate separate
 * step through kontor/invoices' own InvoiceWorkflowService, so whoever
 * generates it can review the lines first.
 */
final class ProjectInvoicingService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ProjectRepository $projects,
        private readonly TimeEntryRepository $timeEntries,
        private readonly BillableItemRepository $billableItems,
        private readonly InvoiceRepository $invoices,
        private readonly DocumentLineRepository $documentLines,
    ) {
    }

    public function generateInvoice(string $projectUid, ?string $contactUid = null, ?\DateTimeImmutable $dueDate = null): Invoice
    {
        $project = $this->projects->require($projectUid);

        if ($project->customerType === null || $project->customerUid === null) {
            throw new RuntimeException("Project \"{$projectUid}\" has no customer to invoice.");
        }

        if ($project->currencyCode === null) {
            throw new RuntimeException("Project \"{$projectUid}\" has no currency configured.");
        }

        $timeEntries = $this->timeEntries->uninvoicedBillableFor($projectUid);
        $billableItems = $this->billableItems->uninvoicedFor($projectUid);

        if ($timeEntries === [] && $billableItems === []) {
            throw new RuntimeException("Project \"{$projectUid}\" has nothing uninvoiced to bill.");
        }

        foreach ($timeEntries as $entry) {
            if ($entry->hourlyRateMinor === null && $project->defaultHourlyRateMinor === null) {
                throw new RuntimeException("Time entry \"{$entry->uid->toString()}\" has no hourly rate and project \"{$projectUid}\" has no default rate.");
            }
        }

        $invoice = Invoice::create($project->organizationId, $project->customerType, $project->customerUid, $project->currencyCode, contactUid: $contactUid, dueDate: $dueDate);

        $this->transact(function () use ($project, $invoice, $timeEntries, $billableItems): void {
            $this->invoices->save($invoice);
            $lines = [];

            foreach ($timeEntries as $entry) {
                $rateMinor = $entry->hourlyRateMinor ?? $project->defaultHourlyRateMinor;

                $line = DocumentLine::create(
                    $project->organizationId, 'invoice', $invoice->uid->toString(),
                    $entry->description ?? 'Time', $entry->durationHours(), Money::ofMinor($rateMinor, $project->currencyCode),
                    unitCode: 'hours',
                );
                $this->documentLines->save($line);
                $lines[] = $line;

                $entry->invoiceLineUid = $line->uid->toString();
                $this->timeEntries->save($entry);
            }

            foreach ($billableItems as $item) {
                $line = DocumentLine::create(
                    $project->organizationId, 'invoice', $invoice->uid->toString(),
                    $item->description, $item->quantity, $item->unitPrice,
                );
                $this->documentLines->save($line);
                $lines[] = $line;

                $item->invoiceLineUid = $line->uid->toString();
                $this->billableItems->save($item);
            }

            $invoice->applyTotalsFromLines($lines);
            $this->invoices->save($invoice);
        });

        return $invoice;
    }

    private function transact(callable $fn): void
    {
        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $fn();

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
