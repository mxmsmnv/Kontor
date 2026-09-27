<?php

declare(strict_types=1);

namespace Kontor\Payments\Application;

use InvalidArgumentException;
use Kontor\Payments\Contracts\PaymentProviderInterface;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use RuntimeException;

final class PaymentProviderService
{
    private const FAILURE_MESSAGE = 'Payment provider request failed; retry is safe with the same idempotency key.';

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentWorkflowService $workflow,
        private readonly PaymentProviderInterface $provider,
    ) {
    }

    public function capture(string $paymentUid, string $idempotencyKey): Payment
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            throw new InvalidArgumentException('A payment provider idempotency key between 1 and 191 characters is required.');
        }

        $payment = $this->payments->require($paymentUid);
        $providerKey = $this->provider->key();
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $providerKey) !== 1) {
            throw new RuntimeException("Payment provider key \"{$providerKey}\" is invalid.");
        }

        $attempt = is_array($payment->metadata['providerCapture'] ?? null)
            ? $payment->metadata['providerCapture']
            : [];
        $keyHash = hash('sha256', $idempotencyKey);
        $recordedHash = (string) ($attempt['idempotencyKeyHash'] ?? '');
        if ($recordedHash !== '' && !hash_equals($recordedHash, $keyHash)) {
            throw new InvalidArgumentException('This payment was already attempted with a different idempotency key.');
        }
        $recordedProvider = (string) ($attempt['provider'] ?? '');
        if ($recordedProvider !== '' && $recordedProvider !== $providerKey) {
            throw new InvalidArgumentException('This payment was already attempted with a different provider.');
        }

        if ($payment->externalId !== null && ($attempt['status'] ?? null) === 'succeeded') {
            return $payment->isDraft()
                ? $this->workflow->confirm($paymentUid)
                : $payment;
        }
        if (!$payment->isDraft()) {
            throw new RuntimeException("Payment \"{$paymentUid}\" is not a draft and cannot be captured by a provider.");
        }

        $attempts = (int) ($attempt['attempts'] ?? 0) + 1;
        try {
            $externalId = trim($this->provider->capture($payment, $idempotencyKey));
            if ($externalId === '' || strlen($externalId) > 191) {
                throw new RuntimeException('The payment provider returned an invalid external identifier.');
            }
        } catch (\Throwable) {
            $payment->metadata['providerCapture'] = [
                'provider' => $providerKey,
                'idempotencyKeyHash' => $keyHash,
                'status' => 'failed',
                'attempts' => $attempts,
                'lastError' => self::FAILURE_MESSAGE,
            ];
            $this->payments->save($payment);

            throw new PaymentProviderException(self::FAILURE_MESSAGE);
        }

        $payment->externalId = $externalId;
        $payment->metadata['providerCapture'] = [
            'provider' => $providerKey,
            'idempotencyKeyHash' => $keyHash,
            'status' => 'succeeded',
            'attempts' => $attempts,
        ];
        $this->payments->save($payment);

        return $this->workflow->confirm($paymentUid);
    }
}
