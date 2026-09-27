<?php

declare(strict_types=1);

namespace Kontor\Payments\Contracts;

use Kontor\Payments\Domain\Payment;

interface PaymentProviderInterface
{
    public function key(): string;

    /**
     * Capture the payment and return the provider's stable external identifier.
     * Implementations must treat the idempotency key as replay-safe.
     */
    public function capture(Payment $payment, string $idempotencyKey): string;
}
