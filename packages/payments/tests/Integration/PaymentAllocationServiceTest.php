<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Domain\Organization;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Payments\Application\PaymentAllocationService;
use Kontor\Payments\Application\PaymentWorkflowService;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;

final class PaymentAllocationServiceTest extends DatabaseTestCase
{
    private PaymentRepository $payments;
    private PaymentAllocationRepository $allocations;
    private InvoiceRepository $invoices;
    private OrderRepository $orders;
    private PaymentWorkflowService $paymentWorkflow;
    private PaymentAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $sequences = new SequenceService($this->pdo, $organizations);

        $this->payments = new PaymentRepository($this->pdo, $organizations);
        $this->allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $this->invoices = new InvoiceRepository($this->pdo, $organizations);
        $this->orders = new OrderRepository($this->pdo, $organizations);
        $this->allocationService = new PaymentAllocationService(
            $this->payments,
            $this->allocations,
            $this->invoices,
            orders: $this->orders,
        );
        $this->paymentWorkflow = new PaymentWorkflowService($this->payments, $this->allocations, $sequences, $this->allocationService);
    }

    private function confirmedPayment(int $amountMinor): Payment
    {
        $payment = Payment::create($this->organizationUid, 'contact', 'ct_01', Money::ofMinor($amountMinor, 'EUR'));
        $this->payments->save($payment);

        return $this->paymentWorkflow->confirm($payment->uid->toString());
    }

    public function test_full_allocation_marks_the_invoice_paid(): void
    {
        $invoice = $this->sentInvoice();
        $payment = $this->confirmedPayment(10000);

        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(10000, 'EUR'));

        $updated = $this->invoices->require($invoice->uid->toString());
        $this->assertSame('paid', $updated->status);
        $this->assertSame(10000, $updated->paid->amountMinor());
        $this->assertSame(0, $updated->due->amountMinor());
        $this->assertNotNull($updated->paidAt);
    }

    public function test_partial_allocation_marks_the_invoice_partially_paid(): void
    {
        $invoice = $this->sentInvoice();
        $payment = $this->confirmedPayment(4000);

        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(4000, 'EUR'));

        $updated = $this->invoices->require($invoice->uid->toString());
        $this->assertSame('partially_paid', $updated->status);
        $this->assertSame(4000, $updated->paid->amountMinor());
        $this->assertSame(6000, $updated->due->amountMinor());
    }

    public function test_two_partial_payments_from_different_payments_reach_paid(): void
    {
        $invoice = $this->sentInvoice();
        $first = $this->confirmedPayment(4000);
        $second = $this->confirmedPayment(6000);

        $this->allocationService->allocate($first->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(4000, 'EUR'));
        $this->allocationService->allocate($second->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(6000, 'EUR'));

        $this->assertSame('paid', $this->invoices->require($invoice->uid->toString())->status);
    }

    public function test_allocations_and_reversal_sync_the_linked_sales_order(): void
    {
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->orders->save($order);
        $invoice = $this->sentInvoice($order->uid->toString());
        $first = $this->confirmedPayment(4000);
        $second = $this->confirmedPayment(6000);

        $firstAllocation = $this->allocationService->allocate(
            $first->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            Money::ofMinor(4000, 'EUR'),
        );
        $this->assertSame(
            'partially_paid',
            $this->orders->require($order->uid->toString())->paymentStatus,
        );

        $secondAllocation = $this->allocationService->allocate(
            $second->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            Money::ofMinor(6000, 'EUR'),
        );
        $this->assertSame('paid', $this->orders->require($order->uid->toString())->paymentStatus);

        $this->allocationService->reverseAllocation($firstAllocation->uid->toString());
        $this->assertSame(
            'partially_paid',
            $this->orders->require($order->uid->toString())->paymentStatus,
        );

        $this->allocationService->reverseAllocation($secondAllocation->uid->toString());
        $this->assertSame('unpaid', $this->orders->require($order->uid->toString())->paymentStatus);
    }

    public function test_cannot_allocate_more_than_the_invoice_still_owes(): void
    {
        $invoice = $this->sentInvoice();
        $payment = $this->confirmedPayment(20000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("exceeds invoice");
        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(20000, 'EUR'));
    }

    public function test_cannot_allocate_more_than_the_payment_still_has_left(): void
    {
        $invoiceA = $this->sentInvoice();
        $invoiceB = $this->sentInvoice();
        $payment = $this->confirmedPayment(10000);

        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoiceA->uid->toString(), Money::ofMinor(10000, 'EUR'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("exceeds payment");
        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoiceB->uid->toString(), Money::ofMinor(1, 'EUR'));
    }

    public function test_cannot_allocate_a_payment_in_a_different_currency_from_the_invoice(): void
    {
        $invoice = $this->sentInvoice();
        $payment = Payment::create(
            $this->organizationUid,
            'contact',
            'ct_01',
            Money::ofMinor(10000, 'USD'),
        );
        $this->payments->save($payment);
        $this->paymentWorkflow->confirm($payment->uid->toString());

        try {
            $this->allocationService->allocate(
                $payment->uid->toString(),
                'invoice',
                $invoice->uid->toString(),
                Money::ofMinor(10000, 'USD'),
            );
            $this->fail('Expected a cross-currency allocation to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('invoice currency', $exception->getMessage());
            $this->assertSame([], $this->allocations->forPayment($payment->uid->toString()));
            $this->assertSame('sent', $this->invoices->require($invoice->uid->toString())->status);
        }
    }

    public function test_cannot_allocate_a_draft_payment(): void
    {
        $invoice = $this->sentInvoice();
        $payment = Payment::create($this->organizationUid, 'contact', 'ct_01', Money::ofMinor(10000, 'EUR'));
        $this->payments->save($payment);

        $this->expectException(\RuntimeException::class);
        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(1000, 'EUR'));
    }

    public function test_reversing_an_allocation_reverts_the_invoice_to_sent(): void
    {
        $invoice = $this->sentInvoice();
        $payment = $this->confirmedPayment(10000);

        $allocation = $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(10000, 'EUR'));
        $this->assertSame('paid', $this->invoices->require($invoice->uid->toString())->status);

        $this->allocationService->reverseAllocation($allocation->uid->toString());

        $updated = $this->invoices->require($invoice->uid->toString());
        $this->assertSame('sent', $updated->status);
        $this->assertSame(0, $updated->paid->amountMinor());
        $this->assertSame(10000, $updated->due->amountMinor());
        $this->assertNull($updated->paidAt);
    }

    public function test_reverse_payment_cascades_to_all_of_its_allocations(): void
    {
        $invoice = $this->sentInvoice();
        $payment = $this->confirmedPayment(10000);
        $this->allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(10000, 'EUR'));

        $reversed = $this->paymentWorkflow->reversePayment($payment->uid->toString());

        $this->assertSame('reversed', $reversed->status);
        $this->assertSame('sent', $this->invoices->require($invoice->uid->toString())->status);

        $allocations = $this->allocations->forPayment($payment->uid->toString());
        $this->assertCount(1, $allocations);
        $this->assertTrue($allocations[0]->isReversed());
    }

    public function test_cannot_allocate_across_organizations(): void
    {
        $invoice = $this->sentInvoice();
        $organizations = new OrganizationRepository($this->pdo);
        $other = new Organization(
            uid: Uid::generate(),
            name: 'Other organization',
            legalName: null,
            countryCode: 'US',
            defaultLanguage: 'en',
            defaultCurrency: 'EUR',
            timezone: 'UTC',
            status: 'active',
        );
        $organizations->save($other);
        $payment = Payment::create(
            $other->uid->toString(),
            'contact',
            'ct_other',
            Money::ofMinor(10000, 'EUR')
        );
        $this->payments->save($payment);
        $this->paymentWorkflow->confirm($payment->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('same organization');
        $this->allocationService->allocate(
            $payment->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            Money::ofMinor(10000, 'EUR')
        );
    }
}
