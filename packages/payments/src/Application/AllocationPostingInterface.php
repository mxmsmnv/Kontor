<?php

declare(strict_types=1);

namespace Kontor\Payments\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Domain\PaymentAllocation;

/**
 * Optional bridge from a payment allocation into an accounting system.
 *
 * Payments remains usable without Ledger; the ProcessWire module supplies
 * the Ledger implementation when that component is installed.
 */
interface AllocationPostingInterface
{
    public function assertCanPost(Payment $payment): void;

    public function postAllocation(
        Payment $payment,
        Invoice $invoice,
        PaymentAllocation $allocation,
        ?int $createdBy = null,
    ): void;

    public function reverseAllocation(
        Payment $payment,
        Invoice $invoice,
        PaymentAllocation $allocation,
        ?int $createdBy = null,
    ): void;
}
