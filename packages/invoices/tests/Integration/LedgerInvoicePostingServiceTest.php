<?php

declare(strict_types=1);

namespace Kontor\Invoices\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Application\LedgerInvoicePostingService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\SDK\ValueObjects\Money;

final class LedgerInvoicePostingServiceTest extends DatabaseTestCase
{
    private AccountRepository $accounts;
    private LedgerEntryRepository $entries;
    private LedgerLineRepository $lines;
    private LedgerInvoicePostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->accounts = new AccountRepository($this->pdo, $organizations);
        $this->entries = new LedgerEntryRepository($this->pdo, $organizations);
        $this->lines = new LedgerLineRepository($this->pdo, $organizations);
        $chart = new ChartOfAccountsService($this->accounts);
        $chart->createAccount($this->organizationUid, '1400', 'Receivables', 'asset', 'EUR');
        $chart->createAccount($this->organizationUid, '1700', 'Sales tax', 'liability', 'EUR');
        $chart->createAccount($this->organizationUid, '8000', 'Revenue', 'revenue', 'EUR');
        $this->posting = new LedgerInvoicePostingService(
            $this->accounts,
            $this->entries,
            new LedgerEntryService($this->accounts, $this->entries, $this->lines),
        );
    }

    public function test_invoice_issue_posts_receivables_revenue_and_tax(): void
    {
        $invoice = $this->issuedInvoice('invoice', 10000, 1900, 11900);

        $this->posting->postIssue($invoice, 42);
        $this->posting->postIssue($invoice, 99);

        $entry = $this->entries->findByReference(
            LedgerInvoicePostingService::ISSUE_REFERENCE,
            $invoice->uid->toString(),
        );
        $this->assertNotNull($entry);
        $this->assertSame(42, $entry->createdBy);
        $lines = $this->lines->forEntry($entry->uid->toString());
        $this->assertCount(3, $lines);
        $this->assertSame(11900, $lines[0]->debit->amountMinor());
        $this->assertSame(1900, $lines[1]->credit->amountMinor());
        $this->assertSame(10000, $lines[2]->credit->amountMinor());
        $this->assertCount(1, $this->entries->forOrganization($this->organizationUid));
    }

    public function test_credit_note_and_cancellation_reverse_the_posting_sides(): void
    {
        $credit = $this->issuedInvoice('credit_note', -10000, -1900, -11900);

        $this->posting->postIssue($credit, 42);
        $this->posting->postCancellation($credit, 43);

        $issue = $this->entries->findByReference(
            LedgerInvoicePostingService::ISSUE_REFERENCE,
            $credit->uid->toString(),
        );
        $this->assertNotNull($issue);
        $issueLines = $this->lines->forEntry($issue->uid->toString());
        $this->assertSame(11900, $issueLines[0]->credit->amountMinor());
        $this->assertSame(1900, $issueLines[1]->debit->amountMinor());
        $this->assertSame(10000, $issueLines[2]->debit->amountMinor());

        $cancellation = $this->entries->findByReference(
            LedgerInvoicePostingService::CANCELLATION_REFERENCE,
            $credit->uid->toString(),
        );
        $this->assertNotNull($cancellation);
        $cancellationLines = $this->lines->forEntry($cancellation->uid->toString());
        $this->assertSame(11900, $cancellationLines[0]->debit->amountMinor());
        $this->assertSame(1900, $cancellationLines[1]->credit->amountMinor());
        $this->assertSame(10000, $cancellationLines[2]->credit->amountMinor());
    }

    public function test_posting_rejects_missing_or_incompatible_accounts(): void
    {
        $invoice = $this->issuedInvoice('invoice', 10000, 1900, 11900, 'USD');

        $this->expectException(\RuntimeException::class);
        $this->posting->postIssue($invoice);
    }

    private function issuedInvoice(
        string $kind,
        int $subtotalMinor,
        int $taxMinor,
        int $totalMinor,
        string $currency = 'EUR',
    ): Invoice {
        $invoice = Invoice::create(
            $this->organizationUid,
            'contact',
            'contact-1',
            $currency,
            kind: $kind,
            creditedInvoiceUid: $kind === 'credit_note' ? 'invoice-1' : null,
        );
        $invoice->number = $kind === 'credit_note' ? 'CN-00001' : 'INV-00001';
        $invoice->subtotal = Money::ofMinor($subtotalMinor, $currency);
        $invoice->tax = Money::ofMinor($taxMinor, $currency);
        $invoice->total = Money::ofMinor($totalMinor, $currency);
        $invoice->due = $invoice->total;
        $invoice->status = 'issued';
        $invoice->issueDate = new \DateTimeImmutable('2026-07-26');
        $invoice->issuedAt = new \DateTimeImmutable('2026-07-26');

        return $invoice;
    }
}
