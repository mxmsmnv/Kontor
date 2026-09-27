<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\ValueObjects;

use InvalidArgumentException;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_add_same_currency(): void
    {
        $a = Money::ofMinor(1000, 'EUR');
        $b = Money::ofMinor(250, 'EUR');

        $this->assertSame(1250, $a->add($b)->amountMinor());
    }

    public function test_add_rejects_mismatched_currency(): void
    {
        $eur = Money::ofMinor(1000, 'EUR');
        $usd = Money::ofMinor(1000, 'USD');

        $this->expectException(InvalidArgumentException::class);

        $eur->add($usd);
    }

    public function test_multiply_rounds_to_nearest_minor_unit(): void
    {
        $price = Money::ofMinor(999, 'EUR');

        $this->assertSame(3330, $price->multiply(3.333)->amountMinor());
    }

    public function test_rejects_invalid_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::ofMinor(100, 'EU');
    }

    public function test_zero_and_negative(): void
    {
        $zero = Money::zero('EUR');

        $this->assertTrue($zero->isZero());
        $this->assertTrue($zero->subtract(Money::ofMinor(100, 'EUR'))->isNegative());
    }
}
