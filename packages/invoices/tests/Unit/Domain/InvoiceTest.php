<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Unit\Domain;

use Kontor\Invoices\Domain\Invoice;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function test_apply_totals_from_lines_keeps_due_in_sync_with_total_minus_paid(): void
    {
        $invoice = Invoice::create('org_01', 'contact', 'ct_01', 'EUR');
        $line = DocumentLine::create('org_01', 'invoice', $invoice->uid->toString(), 'Widget', 2.0, Money::ofMinor(1000, 'EUR'), taxRate: 20.0);

        $invoice->applyTotalsFromLines([$line]);

        $this->assertSame(2000, $invoice->subtotal->amountMinor());
        $this->assertSame(400, $invoice->tax->amountMinor());
        $this->assertSame(2400, $invoice->total->amountMinor());
        $this->assertSame(2400, $invoice->due->amountMinor());
    }

    public function test_is_cancellable_only_before_it_is_sent_or_later_terminal_state(): void
    {
        $invoice = Invoice::create('org_01', 'contact', 'ct_01', 'EUR');
        $this->assertTrue($invoice->isCancellable());

        $invoice->status = 'issued';
        $this->assertTrue($invoice->isCancellable());

        $invoice->status = 'overdue';
        $this->assertFalse($invoice->isCancellable());

        $invoice->status = 'paid';
        $this->assertFalse($invoice->isCancellable());
    }

    public function test_is_creditable_only_for_issued_or_later_invoices_not_credit_notes(): void
    {
        $invoice = Invoice::create('org_01', 'contact', 'ct_01', 'EUR');
        $this->assertFalse($invoice->isCreditable(), 'a draft cannot be credited');

        $invoice->status = 'sent';
        $this->assertTrue($invoice->isCreditable());

        $invoice->status = 'credited';
        $this->assertFalse($invoice->isCreditable(), 'an already-credited invoice cannot be credited again');

        $creditNote = Invoice::create('org_01', 'contact', 'ct_01', 'EUR', kind: 'credit_note', creditedInvoiceUid: $invoice->uid->toString());
        $creditNote->status = 'sent';
        $this->assertFalse($creditNote->isCreditable(), 'a credit note itself cannot be credited');
    }

    public function test_issued_document_snapshot_is_attached_once(): void
    {
        $invoice = Invoice::create('org_01', 'contact', 'ct_01', 'EUR');
        $invoice->status = 'issued';
        $invoice->attachIssuedDocument('01ARZ3NDEKTSV4RRFFQ69G5FAV', ['html' => '<p>Issued</p>']);

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $invoice->templateUid);
        $this->assertSame(['html' => '<p>Issued</p>'], $invoice->snapshot);

        $this->expectException(\RuntimeException::class);
        $invoice->attachIssuedDocument('01ARZ3NDEKTSV4RRFFQ69G5FAV', ['html' => '<p>Changed</p>']);
    }

    public function test_draft_cannot_receive_an_issued_document_snapshot(): void
    {
        $invoice = Invoice::create('org_01', 'contact', 'ct_01', 'EUR');

        $this->expectException(\RuntimeException::class);
        $invoice->attachIssuedDocument('01ARZ3NDEKTSV4RRFFQ69G5FAV', ['html' => '<p>Draft</p>']);
    }
}
