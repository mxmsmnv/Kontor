<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Domain\PaymentAllocation;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\Payments\Migrations\Migration0001CreatePaymentsTable;
use Kontor\Payments\Migrations\Migration0002CreatePaymentAllocationsTable;
use Kontor\Portal\Application\CustomerPaymentService;
use Kontor\SDK\ValueObjects\Money;

final class CustomerPaymentServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreatePaymentsTable(),
            new Migration0002CreatePaymentAllocationsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_payment_allocations', 'kontor_payments', 'kontor_organizations', 'kontor_migrations'];
    }

    private function service(): CustomerPaymentService
    {
        $organizations = new OrganizationRepository($this->pdo);

        return new CustomerPaymentService(
            new PaymentAllocationRepository($this->pdo, $organizations),
            new PaymentRepository($this->pdo, $organizations),
        );
    }

    public function test_payments_for_invoice_resolves_the_full_payment_through_its_allocation(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);

        $payment = Payment::create($this->organizationUid, 'contact', 'contact_1', Money::ofMinor(10000, 'EUR'));
        $payments->save($payment);
        $allocations->save(PaymentAllocation::create($this->organizationUid, $payment->uid->toString(), 'invoice', 'invoice_1', Money::ofMinor(7500, 'EUR')));

        $result = $this->service()->paymentsForInvoice('invoice_1');

        $this->assertCount(1, $result);
        $this->assertSame($payment->uid->toString(), $result[0]['payment']->uid->toString());
        $this->assertSame(7500, $result[0]['allocatedAmount']->amountMinor());
    }

    public function test_a_reversed_allocation_is_excluded(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);

        $payment = Payment::create($this->organizationUid, 'contact', 'contact_1', Money::ofMinor(10000, 'EUR'));
        $payments->save($payment);
        $allocation = PaymentAllocation::create($this->organizationUid, $payment->uid->toString(), 'invoice', 'invoice_1', Money::ofMinor(7500, 'EUR'));
        $allocation->reversedAt = new \DateTimeImmutable();
        $allocations->save($allocation);

        $this->assertSame([], $this->service()->paymentsForInvoice('invoice_1'));
    }

    public function test_an_invoice_with_no_payments_returns_an_empty_list(): void
    {
        $this->assertSame([], $this->service()->paymentsForInvoice('invoice_with_no_payments'));
    }
}
