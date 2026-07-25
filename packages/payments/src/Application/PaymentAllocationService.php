<?php

declare(strict_types=1);

namespace Kontor\Payments\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Payments\Domain\PaymentAllocation;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\SDK\ValueObjects\Money;
use InvalidArgumentException;
use RuntimeException;

/**
 * The "allocations" and "partial payments" milestones, and the half of
 * "reversals" that isn't PaymentWorkflowService::reversePayment(). This is
 * also this monorepo's first cross-package *write*: kontor/invoices'
 * README explicitly left Invoice::paid/due/status "not actively driven",
 * naming Payments (this substage) as the component that would drive them
 * — this service is that. It reuses Kontor\Invoices\Infrastructure\
 * Persistence\InvoiceRepository directly rather than re-deriving invoice
 * state some other way, same reuse pattern kontor/invoices itself used for
 * kontor/sales' document lines.
 *
 * Only `document_type = 'invoice'` is implemented — kontor_payment_
 * allocations' document_type/document_uid pair is generic (like
 * kontor_document_lines'), but invoices are the only documents this
 * monorepo can allocate payments against today.
 */
final class PaymentAllocationService
{
    private const SUPPORTED_DOCUMENT_TYPE = 'invoice';

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentAllocationRepository $allocations,
        private readonly InvoiceRepository $invoices,
    ) {
    }

    public function allocate(string $paymentUid, string $documentType, string $documentUid, Money $amount): PaymentAllocation
    {
        if ($documentType !== self::SUPPORTED_DOCUMENT_TYPE) {
            throw new InvalidArgumentException("Allocating against document type \"{$documentType}\" is not supported yet.");
        }

        if ($amount->isZero() || $amount->isNegative()) {
            throw new InvalidArgumentException('Allocation amount must be greater than zero.');
        }

        $payment = $this->payments->require($paymentUid);

        if (!$payment->isConfirmed()) {
            throw new RuntimeException("Payment \"{$paymentUid}\" must be confirmed before it can be allocated.");
        }

        if ($amount->currencyCode() !== $payment->amount->currencyCode()) {
            throw new InvalidArgumentException('Allocation currency must match the payment currency.');
        }

        $remainingOnPayment = $payment->amount->subtract(
            $this->allocations->totalAllocatedForPayment($paymentUid, $payment->amount->currencyCode())
        );

        if ($amount->amountMinor() > $remainingOnPayment->amountMinor()) {
            throw new RuntimeException("Allocation of {$amount} exceeds payment \"{$paymentUid}\"'s remaining balance of {$remainingOnPayment}.");
        }

        $invoice = $this->invoices->require($documentUid);

        if (!in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true)) {
            throw new RuntimeException("Invoice \"{$documentUid}\" cannot be allocated against from status \"{$invoice->status}\".");
        }

        if ($amount->amountMinor() > $invoice->due->amountMinor()) {
            throw new RuntimeException("Allocation of {$amount} exceeds invoice \"{$documentUid}\"'s remaining due amount of {$invoice->due}.");
        }

        $allocation = PaymentAllocation::create($payment->organizationId, $paymentUid, $documentType, $documentUid, $amount);
        $this->allocations->save($allocation);

        $this->syncInvoiceFromAllocations($invoice);

        return $allocation;
    }

    public function reverseAllocation(string $allocationUid): PaymentAllocation
    {
        $allocation = $this->allocations->require($allocationUid);

        if ($allocation->isReversed()) {
            throw new RuntimeException("Payment allocation \"{$allocationUid}\" is already reversed.");
        }

        $allocation->reversedAt = new \DateTimeImmutable();
        $this->allocations->save($allocation);

        if ($allocation->documentType === self::SUPPORTED_DOCUMENT_TYPE) {
            $this->syncInvoiceFromAllocations($this->invoices->require($allocation->documentUid));
        }

        return $allocation;
    }

    /**
     * Recomputes paid/due/status from every non-reversed allocation for
     * this invoice, from scratch rather than incrementally, so nothing can
     * ever drift regardless of how many allocate()/reverseAllocation()
     * calls came before (kontor.md diagram 17.2: Sent/Issued/Overdue <->
     * PartiallyPaid <-> Paid, driven by payment amount alone).
     */
    private function syncInvoiceFromAllocations(Invoice $invoice): void
    {
        $paid = $this->allocations->totalAllocatedForDocument(self::SUPPORTED_DOCUMENT_TYPE, $invoice->uid->toString(), $invoice->currencyCode);

        $invoice->paid = $paid;
        $invoice->due = $invoice->total->subtract($paid);

        if ($paid->isZero()) {
            $invoice->status = ($invoice->dueDate !== null && $invoice->dueDate < new \DateTimeImmutable('today'))
                ? 'overdue'
                : 'sent';
            $invoice->paidAt = null;
        } elseif ($invoice->due->amountMinor() <= 0) {
            $invoice->status = 'paid';
            $invoice->paidAt ??= new \DateTimeImmutable();
        } else {
            $invoice->status = 'partially_paid';
            $invoice->paidAt = null;
        }

        $this->invoices->save($invoice);
    }
}
