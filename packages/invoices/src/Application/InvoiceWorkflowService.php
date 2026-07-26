<?php

declare(strict_types=1);

namespace Kontor\Invoices\Application;

use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use RuntimeException;

/**
 * Substage 4.3 "issue workflow", "numbering", "overdue state" and "credit
 * notes" milestones. Payment-driven transitions (PartiallyPaid/Paid,
 * kontor.md diagram 17.2) are deliberately not implemented here — same
 * "not actively driven" treatment Sales gave `payment_status` — those
 * belong to Payments (Substage 4.4).
 */
final class InvoiceWorkflowService
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly DocumentLineRepository $lines,
        private readonly SequenceService $sequences,
        private readonly ?InvoicePostingInterface $posting = null,
    ) {
    }

    public function issue(string $invoiceUid, ?int $createdBy = null): Invoice
    {
        $invoice = $this->invoices->require($invoiceUid);

        if (!$invoice->isDraft()) {
            throw new RuntimeException("Invoice \"{$invoiceUid}\" is not a draft and cannot be issued.");
        }

        $invoice->applyTotalsFromLines($this->lines->forDocument('invoice', $invoiceUid));
        $invoice->number = $this->sequences->next(
            $invoice->organizationId, 'invoices', 'invoice', prefix: 'INV-', padding: 5, resetPolicy: 'yearly'
        );
        $invoice->issueDate ??= new \DateTimeImmutable();
        $invoice->issuedAt = new \DateTimeImmutable();
        $invoice->status = 'issued';

        $this->posting?->assertCanPost($invoice);
        $this->invoices->save($invoice);
        $this->posting?->postIssue($invoice, $createdBy);

        return $invoice;
    }

    public function send(string $invoiceUid): Invoice
    {
        $invoice = $this->invoices->require($invoiceUid);

        if ($invoice->status !== 'issued' && $invoice->status !== 'sent') {
            throw new RuntimeException("Invoice \"{$invoiceUid}\" must be issued before it can be sent.");
        }

        $invoice->status = 'sent';
        $invoice->sentAt ??= new \DateTimeImmutable();
        $this->invoices->save($invoice);

        return $invoice;
    }

    public function cancel(string $invoiceUid, ?int $createdBy = null): Invoice
    {
        $invoice = $this->invoices->require($invoiceUid);

        if (!$invoice->isCancellable()) {
            throw new RuntimeException("Invoice \"{$invoiceUid}\" cannot be cancelled from status \"{$invoice->status}\".");
        }

        if (!$invoice->isDraft()) {
            $this->posting?->postCancellation($invoice, $createdBy);
        }
        $invoice->status = 'cancelled';
        $invoice->cancelledAt = new \DateTimeImmutable();
        $this->invoices->save($invoice);

        return $invoice;
    }

    /**
     * The "overdue state" milestone: only a Sent invoice past its due date
     * moves to Overdue (kontor.md diagram 17.2 — Issued has no direct
     * Overdue transition). There's no scheduler component yet (Stage 7),
     * so this is a plain method a cron/CLI entry point can call — see the
     * README.
     */
    public function markOverdue(string $invoiceUid): Invoice
    {
        $invoice = $this->invoices->require($invoiceUid);

        if ($invoice->status !== 'sent') {
            throw new RuntimeException("Invoice \"{$invoiceUid}\" must be sent before it can become overdue.");
        }

        if ($invoice->dueDate === null || $invoice->dueDate >= new \DateTimeImmutable('today')) {
            throw new RuntimeException("Invoice \"{$invoiceUid}\" is not past its due date.");
        }

        $invoice->status = 'overdue';
        $this->invoices->save($invoice);

        return $invoice;
    }

    /**
     * Marks every sent, past-due invoice in an organization as overdue.
     *
     * @return string[] uids that were marked overdue
     */
    public function sweepOverdue(string $organizationUid, ?\DateTimeImmutable $asOf = null): array
    {
        $asOf ??= new \DateTimeImmutable('today');
        $marked = [];

        foreach ($this->invoices->findSentPastDue($organizationUid, $asOf) as $invoice) {
            $invoice->status = 'overdue';
            $this->invoices->save($invoice);
            $marked[] = $invoice->uid->toString();
        }

        return $marked;
    }

    /**
     * The "credit notes" milestone: a full credit against an already
     * issued invoice — copies every line with quantity negated, computes
     * negative totals from them, and moves the original invoice straight
     * to its 'credited' terminal status (kontor.md diagram 17.2's "Paid
     * --> Credited: credit note", relaxed to any issued/sent/overdue/paid
     * source since Payments doesn't exist yet to ever produce a Paid
     * invoice in this monorepo today). Partial credit notes aren't a
     * listed milestone and aren't built here — see the README.
     */
    public function issueCreditNote(string $originalInvoiceUid, ?int $createdBy = null): Invoice
    {
        $original = $this->invoices->require($originalInvoiceUid);

        if (!$original->isCreditable()) {
            throw new RuntimeException("Invoice \"{$originalInvoiceUid}\" cannot be credited from status \"{$original->status}\".");
        }

        $creditNote = Invoice::create(
            organizationId: $original->organizationId,
            customerType: $original->customerType,
            customerUid: $original->customerUid,
            currencyCode: $original->currencyCode,
            contactUid: $original->contactUid,
            orderUid: $original->orderUid,
            documentLanguage: $original->documentLanguage,
            kind: 'credit_note',
            creditedInvoiceUid: $original->uid->toString(),
        );

        $originalLines = $this->lines->forDocument('invoice', $originalInvoiceUid);
        $creditLines = [];

        foreach ($originalLines as $line) {
            $creditLine = DocumentLine::create(
                organizationId: $line->organizationId,
                documentType: 'credit_note',
                documentUid: $creditNote->uid->toString(),
                title: $line->title,
                quantity: -$line->quantity,
                unitPrice: $line->unitPrice,
                itemUid: $line->itemUid,
                itemType: $line->itemType,
                sku: $line->sku,
                description: $line->description,
                unitCode: $line->unitCode,
                discountType: $line->discountType,
                discountValue: $line->discountValue,
                taxCode: $line->taxCode,
                taxRate: $line->taxRate,
                sortOrder: $line->sortOrder,
            );
            $this->lines->save($creditLine);
            $creditLines[] = $creditLine;
        }

        $creditNote->applyTotalsFromLines($creditLines);
        $creditNote->number = $this->sequences->next(
            $creditNote->organizationId, 'invoices', 'credit_note', prefix: 'CN-', padding: 5, resetPolicy: 'yearly'
        );
        $creditNote->issueDate = new \DateTimeImmutable();
        $creditNote->issuedAt = new \DateTimeImmutable();
        $creditNote->status = 'issued';
        $this->posting?->assertCanPost($creditNote);
        $this->invoices->save($creditNote);
        $this->posting?->postIssue($creditNote, $createdBy);

        $original->status = 'credited';
        $this->invoices->save($original);

        return $creditNote;
    }
}
