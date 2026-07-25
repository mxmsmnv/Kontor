<?php

declare(strict_types=1);

namespace Kontor\Payments\Application;

use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use RuntimeException;

/**
 * The "payments" milestone's own lifecycle: draft -> confirmed -> reversed.
 * Confirming needs a real number (kontor.md#15.5's `number` column), same
 * as every other numbered document in this monorepo, so it isn't a pure
 * domain method.
 */
final class PaymentWorkflowService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentAllocationRepository $allocations,
        private readonly SequenceService $sequences,
        private readonly PaymentAllocationService $allocationService,
    ) {
    }

    public function confirm(string $paymentUid): Payment
    {
        $payment = $this->payments->require($paymentUid);

        if (!$payment->isDraft()) {
            throw new RuntimeException("Payment \"{$paymentUid}\" is not a draft and cannot be confirmed.");
        }

        $payment->number = $this->sequences->next(
            $payment->organizationId, 'payments', 'payment', prefix: 'PMT-', padding: 5, resetPolicy: 'yearly'
        );
        $payment->status = 'confirmed';
        $this->payments->save($payment);

        return $payment;
    }

    /**
     * The "reversals" milestone at the payment level: reverses every
     * still-active allocation this payment funded (so every document it
     * touched gets its paid/due/status recomputed), then marks the
     * payment itself reversed.
     */
    public function reversePayment(string $paymentUid): Payment
    {
        $payment = $this->payments->require($paymentUid);

        if (!$payment->isConfirmed()) {
            throw new RuntimeException("Payment \"{$paymentUid}\" must be confirmed before it can be reversed.");
        }

        foreach ($this->allocations->forPayment($paymentUid) as $allocation) {
            if (!$allocation->isReversed()) {
                $this->allocationService->reverseAllocation($allocation->uid->toString());
            }
        }

        $payment->status = 'reversed';
        $this->payments->save($payment);

        return $payment;
    }
}
