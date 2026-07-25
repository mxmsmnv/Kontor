<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Payments\Application\PaymentAllocationService;
use Kontor\Payments\Application\PaymentWorkflowService;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Health\PaymentsHealthCheck;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\SDK\ValueObjects\Money;

final class PaymentsHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $sequences = new SequenceService($this->pdo, $organizations);
        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $invoices = new InvoiceRepository($this->pdo, $organizations);
        $allocationService = new PaymentAllocationService($payments, $allocations, $invoices);
        $workflow = new PaymentWorkflowService($payments, $allocations, $sequences, $allocationService);

        $invoice = $this->sentInvoice();
        $payment = Payment::create($this->organizationUid, 'contact', 'ct_01', Money::ofMinor(10000, 'EUR'));
        $payments->save($payment);
        $workflow->confirm($payment->uid->toString());
        $allocationService->allocate($payment->uid->toString(), 'invoice', $invoice->uid->toString(), Money::ofMinor(10000, 'EUR'));

        $result = (new PaymentsHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['confirmedPayments']);
        $this->assertSame(1, $result->details['activeAllocations']);
    }
}
