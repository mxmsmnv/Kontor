<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Unit\Domain;

use Kontor\Payments\Domain\Payment;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function test_create_starts_as_a_draft_with_no_number(): void
    {
        $payment = Payment::create('org_01', 'contact', 'ct_01', Money::ofMinor(5000, 'EUR'));

        $this->assertTrue($payment->isDraft());
        $this->assertFalse($payment->isConfirmed());
        $this->assertFalse($payment->isReversed());
        $this->assertNull($payment->number);
        $this->assertSame('other', $payment->method);
    }

    public function test_status_helpers_reflect_the_current_status(): void
    {
        $payment = Payment::create('org_01', 'contact', 'ct_01', Money::ofMinor(5000, 'EUR'));

        $payment->status = 'confirmed';
        $this->assertTrue($payment->isConfirmed());

        $payment->status = 'reversed';
        $this->assertTrue($payment->isReversed());
    }
}
