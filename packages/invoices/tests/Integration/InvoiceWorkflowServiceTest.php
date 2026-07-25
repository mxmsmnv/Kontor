<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Invoices\Application\InvoiceWorkflowService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

final class InvoiceWorkflowServiceTest extends DatabaseTestCase
{
    private InvoiceRepository $invoices;
    private DocumentLineRepository $lines;
    private InvoiceWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $sequences = new SequenceService($this->pdo, $organizations);

        $this->invoices = new InvoiceRepository($this->pdo, $organizations);
        $this->lines = new DocumentLineRepository($this->pdo, $organizations);
        $this->workflow = new InvoiceWorkflowService($this->invoices, $this->lines, $sequences);
    }

    private function draftInvoiceWithLines(): Invoice
    {
        $invoice = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR', dueDate: new \DateTimeImmutable('-1 day'));
        $this->invoices->save($invoice);

        $this->lines->save(DocumentLine::create(
            $this->organizationUid, 'invoice', $invoice->uid->toString(), 'Widget', 3.0, Money::ofMinor(1000, 'EUR'), taxRate: 20.0,
        ));

        return $invoice;
    }

    public function test_issue_assigns_a_number_and_computes_totals_from_lines(): void
    {
        $invoice = $this->draftInvoiceWithLines();

        $issued = $this->workflow->issue($invoice->uid->toString());

        $this->assertStringStartsWith('INV-', $issued->number);
        $this->assertSame('issued', $issued->status);
        $this->assertSame(3000, $issued->subtotal->amountMinor());
        $this->assertSame(600, $issued->tax->amountMinor());
        $this->assertSame(3600, $issued->total->amountMinor());
        $this->assertSame(3600, $issued->due->amountMinor());
    }

    public function test_cannot_issue_a_non_draft_invoice_twice(): void
    {
        $invoice = $this->draftInvoiceWithLines();
        $this->workflow->issue($invoice->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->workflow->issue($invoice->uid->toString());
    }

    public function test_send_requires_the_invoice_to_be_issued_first(): void
    {
        $invoice = $this->draftInvoiceWithLines();

        $this->expectException(\RuntimeException::class);
        $this->workflow->send($invoice->uid->toString());
    }

    public function test_cancel_is_allowed_from_draft_issued_and_sent_but_not_after(): void
    {
        $invoice = $this->draftInvoiceWithLines();
        $this->workflow->issue($invoice->uid->toString());
        $this->workflow->send($invoice->uid->toString());

        $cancelled = $this->workflow->cancel($invoice->uid->toString());

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelledAt);
    }

    public function test_sweep_overdue_marks_sent_past_due_invoices_and_leaves_others_alone(): void
    {
        $overdue = $this->draftInvoiceWithLines();
        $this->workflow->issue($overdue->uid->toString());
        $this->workflow->send($overdue->uid->toString());

        $notYetDue = Invoice::create($this->organizationUid, 'contact', 'ct_02', 'EUR', dueDate: new \DateTimeImmutable('+30 days'));
        $this->invoices->save($notYetDue);
        $this->lines->save(DocumentLine::create($this->organizationUid, 'invoice', $notYetDue->uid->toString(), 'Widget', 1.0, Money::ofMinor(500, 'EUR')));
        $this->workflow->issue($notYetDue->uid->toString());
        $this->workflow->send($notYetDue->uid->toString());

        $marked = $this->workflow->sweepOverdue($this->organizationUid);

        $this->assertSame([$overdue->uid->toString()], $marked);
        $this->assertSame('overdue', $this->invoices->require($overdue->uid->toString())->status);
        $this->assertSame('sent', $this->invoices->require($notYetDue->uid->toString())->status);
    }

    public function test_issue_credit_note_copies_negated_lines_and_credits_the_original(): void
    {
        $invoice = $this->draftInvoiceWithLines();
        $this->workflow->issue($invoice->uid->toString());
        $this->workflow->send($invoice->uid->toString());

        $creditNote = $this->workflow->issueCreditNote($invoice->uid->toString());

        $this->assertSame('credit_note', $creditNote->kind);
        $this->assertSame($invoice->uid->toString(), $creditNote->creditedInvoiceUid);
        $this->assertStringStartsWith('CN-', $creditNote->number);
        $this->assertSame('issued', $creditNote->status);
        $this->assertSame(-3600, $creditNote->total->amountMinor());

        $creditLines = $this->lines->forDocument('credit_note', $creditNote->uid->toString());
        $this->assertCount(1, $creditLines);
        $this->assertSame(-3.0, $creditLines[0]->quantity);

        $this->assertSame('credited', $this->invoices->require($invoice->uid->toString())->status);
    }

    public function test_cannot_credit_a_draft_invoice(): void
    {
        $invoice = $this->draftInvoiceWithLines();

        $this->expectException(\RuntimeException::class);
        $this->workflow->issueCreditNote($invoice->uid->toString());
    }

    public function test_cannot_credit_an_invoice_twice(): void
    {
        $invoice = $this->draftInvoiceWithLines();
        $this->workflow->issue($invoice->uid->toString());
        $this->workflow->issueCreditNote($invoice->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->workflow->issueCreditNote($invoice->uid->toString());
    }
}
