<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Application\PaymentAllocationService;
use Kontor\Payments\Application\PaymentWorkflowService;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\SDK\ValueObjects\Money;

final class LedgerAllocationPostingServiceTest extends DatabaseTestCase
{
    public function test_allocation_and_reversal_create_balanced_append_only_entries(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $entries = new LedgerEntryRepository($this->pdo, $organizations);
        $lines = new LedgerLineRepository($this->pdo, $organizations);
        $chart = new ChartOfAccountsService($accounts);
        $bank = $chart->createAccount($this->organizationUid, '1200', 'Bank', 'asset', 'EUR');
        $receivables = $chart->createAccount($this->organizationUid, '1400', 'Receivables', 'asset', 'EUR');

        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $invoices = new InvoiceRepository($this->pdo, $organizations);
        $posting = new LedgerAllocationPostingService(
            $accounts,
            $entries,
            new LedgerEntryService($accounts, $entries, $lines),
        );
        $allocationService = new PaymentAllocationService($payments, $allocations, $invoices, $posting);
        $workflow = new PaymentWorkflowService(
            $payments,
            $allocations,
            new SequenceService($this->pdo, $organizations),
            $allocationService,
        );

        $invoice = $this->sentInvoice();
        $payment = Payment::create(
            $this->organizationUid,
            'contact',
            'ct_01',
            Money::ofMinor(10000, 'EUR'),
            paymentDate: new \DateTimeImmutable('2026-07-26'),
        );
        $payments->save($payment);
        $payment = $workflow->confirm($payment->uid->toString());
        $allocation = $allocationService->allocate(
            $payment->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            Money::ofMinor(10000, 'EUR'),
            42,
        );

        $posted = $entries->findByReference(
            LedgerAllocationPostingService::POSTING_REFERENCE,
            $allocation->uid->toString(),
        );
        $this->assertNotNull($posted);
        $this->assertSame(42, $posted->createdBy);
        $postedLines = $lines->forEntry($posted->uid->toString());
        $this->assertSame($bank->uid->toString(), $postedLines[0]->accountUid);
        $this->assertSame(10000, $postedLines[0]->debit->amountMinor());
        $this->assertSame($receivables->uid->toString(), $postedLines[1]->accountUid);
        $this->assertSame(10000, $postedLines[1]->credit->amountMinor());

        $workflow->reversePayment($payment->uid->toString(), 43);

        $reversal = $entries->findByReference(
            LedgerAllocationPostingService::REVERSAL_REFERENCE,
            $allocation->uid->toString(),
        );
        $this->assertNotNull($reversal);
        $this->assertSame(43, $reversal->createdBy);
        $reversalLines = $lines->forEntry($reversal->uid->toString());
        $this->assertSame($receivables->uid->toString(), $reversalLines[0]->accountUid);
        $this->assertSame(10000, $reversalLines[0]->debit->amountMinor());
        $this->assertSame($bank->uid->toString(), $reversalLines[1]->accountUid);
        $this->assertSame(10000, $reversalLines[1]->credit->amountMinor());
        $this->assertCount(2, $entries->forOrganization($this->organizationUid));
    }

    public function test_posting_is_idempotent_for_the_same_allocation(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $entries = new LedgerEntryRepository($this->pdo, $organizations);
        $lines = new LedgerLineRepository($this->pdo, $organizations);
        $chart = new ChartOfAccountsService($accounts);
        $chart->createAccount($this->organizationUid, '1200', 'Bank', 'asset', 'EUR');
        $chart->createAccount($this->organizationUid, '1400', 'Receivables', 'asset', 'EUR');

        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $invoices = new InvoiceRepository($this->pdo, $organizations);
        $posting = new LedgerAllocationPostingService(
            $accounts,
            $entries,
            new LedgerEntryService($accounts, $entries, $lines),
        );
        $allocationService = new PaymentAllocationService($payments, $allocations, $invoices, $posting);
        $workflow = new PaymentWorkflowService(
            $payments,
            $allocations,
            new SequenceService($this->pdo, $organizations),
            $allocationService,
        );
        $invoice = $this->sentInvoice();
        $payment = Payment::create($this->organizationUid, 'contact', 'ct_01', Money::ofMinor(10000, 'EUR'));
        $payments->save($payment);
        $payment = $workflow->confirm($payment->uid->toString());
        $allocation = $allocationService->allocate(
            $payment->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            Money::ofMinor(10000, 'EUR'),
        );

        $posting->postAllocation($payment, $invoice, $allocation);

        $this->assertCount(1, $entries->forOrganization($this->organizationUid));
    }

    public function test_missing_posting_accounts_are_rejected_before_allocation_state_changes(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $accounts = new AccountRepository($this->pdo, $organizations);
        $entries = new LedgerEntryRepository($this->pdo, $organizations);
        $lines = new LedgerLineRepository($this->pdo, $organizations);
        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $invoices = new InvoiceRepository($this->pdo, $organizations);
        $allocationService = new PaymentAllocationService(
            $payments,
            $allocations,
            $invoices,
            new LedgerAllocationPostingService(
                $accounts,
                $entries,
                new LedgerEntryService($accounts, $entries, $lines),
            ),
        );
        $workflow = new PaymentWorkflowService(
            $payments,
            $allocations,
            new SequenceService($this->pdo, $organizations),
            $allocationService,
        );
        $invoice = $this->sentInvoice();
        $payment = Payment::create($this->organizationUid, 'contact', 'ct_01', Money::ofMinor(10000, 'EUR'));
        $payments->save($payment);
        $payment = $workflow->confirm($payment->uid->toString());

        try {
            $allocationService->allocate(
                $payment->uid->toString(),
                'invoice',
                $invoice->uid->toString(),
                Money::ofMinor(10000, 'EUR'),
            );
            $this->fail('Missing posting accounts should reject the allocation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('requires active ledger accounts', $exception->getMessage());
        }

        $this->assertSame([], $allocations->forPayment($payment->uid->toString()));
        $unchanged = $invoices->require($invoice->uid->toString());
        $this->assertSame('sent', $unchanged->status);
        $this->assertSame(0, $unchanged->paid->amountMinor());
        $this->assertSame(10000, $unchanged->due->amountMinor());
        $this->assertSame([], $entries->forOrganization($this->organizationUid));
    }
}
