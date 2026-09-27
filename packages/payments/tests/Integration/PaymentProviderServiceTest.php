<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Payments\Application\PaymentAllocationService;
use Kontor\Payments\Application\PaymentProviderException;
use Kontor\Payments\Application\PaymentProviderService;
use Kontor\Payments\Application\PaymentWorkflowService;
use Kontor\Payments\Contracts\PaymentProviderInterface;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\SDK\ValueObjects\Money;

final class PaymentProviderServiceTest extends DatabaseTestCase
{
    public function test_failure_retry_and_successful_replay_are_redacted_and_idempotent(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $payments = new PaymentRepository($this->pdo, $organizations);
        $allocations = new PaymentAllocationRepository($this->pdo, $organizations);
        $allocationService = new PaymentAllocationService(
            $payments,
            $allocations,
            new InvoiceRepository($this->pdo, $organizations),
        );
        $workflow = new PaymentWorkflowService(
            $payments,
            $allocations,
            new SequenceService($this->pdo, $organizations),
            $allocationService,
        );
        $provider = new FailingThenSuccessfulPaymentProvider();
        $service = new PaymentProviderService($payments, $workflow, $provider);
        $payment = Payment::create(
            $this->organizationUid,
            'contact',
            '01HCUSTOMER000000000000000',
            Money::ofMinor(10000, 'EUR'),
            method: 'card',
        );
        $payments->save($payment);

        try {
            $service->capture($payment->uid->toString(), 'checkout-attempt-01');
            $this->fail('The deterministic first provider attempt must fail.');
        } catch (PaymentProviderException $exception) {
            $this->assertSame(
                'Payment provider request failed; retry is safe with the same idempotency key.',
                $exception->getMessage(),
            );
            $this->assertNull($exception->getPrevious());
        }

        $failed = $payments->require($payment->uid->toString());
        $failureJson = json_encode($failed->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->assertSame('draft', $failed->status);
        $this->assertNull($failed->externalId);
        $this->assertSame('failed', $failed->metadata['providerCapture']['status']);
        $this->assertSame(1, $failed->metadata['providerCapture']['attempts']);
        $this->assertStringNotContainsString('provider-secret', $failureJson);
        $this->assertStringNotContainsString('01HCUSTOMER000000000000000', $failureJson);
        $this->assertStringNotContainsString('checkout-attempt-01', $failureJson);

        try {
            $service->capture($payment->uid->toString(), 'different-key');
            $this->fail('Changing the idempotency key on retry must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('different idempotency key', $exception->getMessage());
            $this->assertSame(1, $provider->calls);
        }

        $captured = $service->capture($payment->uid->toString(), 'checkout-attempt-01');

        $this->assertSame('confirmed', $captured->status);
        $this->assertSame('provider-payment-001', $captured->externalId);
        $this->assertNotNull($captured->number);
        $this->assertSame('succeeded', $captured->metadata['providerCapture']['status']);
        $this->assertSame(2, $captured->metadata['providerCapture']['attempts']);
        $this->assertArrayNotHasKey('lastError', $captured->metadata['providerCapture']);
        $this->assertSame(2, $provider->calls);
        $number = $captured->number;

        $replayed = $service->capture($payment->uid->toString(), 'checkout-attempt-01');

        $this->assertSame('provider-payment-001', $replayed->externalId);
        $this->assertSame($number, $replayed->number);
        $this->assertSame(2, $provider->calls);
        $this->assertSame(
            ['checkout-attempt-01', 'checkout-attempt-01'],
            $provider->idempotencyKeys,
        );
    }
}

final class FailingThenSuccessfulPaymentProvider implements PaymentProviderInterface
{
    public int $calls = 0;

    /** @var string[] */
    public array $idempotencyKeys = [];

    public function key(): string
    {
        return 'local_fake';
    }

    public function capture(Payment $payment, string $idempotencyKey): string
    {
        $this->calls++;
        $this->idempotencyKeys[] = $idempotencyKey;

        if ($this->calls === 1) {
            throw new \RuntimeException(
                'timeout provider-secret=sk_test_123 payer=' . $payment->payerUid,
            );
        }

        return 'provider-payment-001';
    }
}
