<?php

declare(strict_types=1);

namespace Kontor\Portal\Application;

use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;

/**
 * The "payments" milestone: read-only payment history for one of the
 * customer's own invoices, reusing `kontor/payments`'s own
 * `PaymentAllocationRepository::forDocument()` + `PaymentRepository::find()`
 * directly rather than a parallel query. A `Payment` itself has no
 * `invoiceUid` — the link only exists through its allocations (kontor.md
 * Substage 4.4 "allocations"/"partial payments"), so an invoice's payment
 * history is always resolved this same two-step way, in `kontor/payments`
 * itself and here alike.
 *
 * Initiating a new payment (e.g. via a card/bank gateway) is out of scope
 * for this substage — no payment gateway integration exists anywhere in
 * this monorepo yet; see README "Not in scope".
 */
final class CustomerPaymentService
{
    public function __construct(
        private readonly PaymentAllocationRepository $allocations,
        private readonly PaymentRepository $payments,
    ) {
    }

    /**
     * @return list<array{payment: \Kontor\Payments\Domain\Payment, allocatedAmount: \Kontor\SDK\ValueObjects\Money}>
     */
    public function paymentsForInvoice(string $invoiceUid): array
    {
        $result = [];

        foreach ($this->allocations->forDocument('invoice', $invoiceUid) as $allocation) {
            if ($allocation->isReversed()) {
                continue;
            }

            $payment = $this->payments->find($allocation->paymentUid);

            if ($payment === null) {
                continue;
            }

            $result[] = ['payment' => $payment, 'allocatedAmount' => $allocation->amount];
        }

        return $result;
    }
}
