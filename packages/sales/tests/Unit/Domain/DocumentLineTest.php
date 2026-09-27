<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Unit\Domain;

use Kontor\Sales\Domain\DocumentLine;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class DocumentLineTest extends TestCase
{
    public function test_gross_amount_is_quantity_times_unit_price(): void
    {
        $line = DocumentLine::create('org_01', 'quotation', 'quo_01', 'Widget', 3.0, Money::ofMinor(1000, 'EUR'));

        $this->assertSame(3000, $line->grossAmount()->amountMinor());
    }

    public function test_no_discount_by_default(): void
    {
        $line = DocumentLine::create('org_01', 'quotation', 'quo_01', 'Widget', 1.0, Money::ofMinor(1000, 'EUR'));

        $this->assertSame(0, $line->discountAmount()->amountMinor());
        $this->assertSame(1000, $line->subtotal()->amountMinor());
    }

    public function test_percentage_discount(): void
    {
        $line = DocumentLine::create(
            'org_01', 'quotation', 'quo_01', 'Widget', 1.0, Money::ofMinor(1000, 'EUR'),
            discountType: 'percentage', discountValue: 10.0,
        );

        $this->assertSame(100, $line->discountAmount()->amountMinor());
        $this->assertSame(900, $line->subtotal()->amountMinor());
    }

    public function test_fixed_discount(): void
    {
        $line = DocumentLine::create(
            'org_01', 'quotation', 'quo_01', 'Widget', 1.0, Money::ofMinor(1000, 'EUR'),
            discountType: 'fixed', discountValue: 2.50,
        );

        $this->assertSame(250, $line->discountAmount()->amountMinor());
        $this->assertSame(750, $line->subtotal()->amountMinor());
    }

    public function test_tax_is_computed_on_the_discounted_subtotal(): void
    {
        $line = DocumentLine::create(
            'org_01', 'quotation', 'quo_01', 'Widget', 1.0, Money::ofMinor(1000, 'EUR'),
            discountType: 'percentage', discountValue: 10.0, taxRate: 20.0,
        );

        // subtotal after discount = 900; tax = 20% of 900 = 180
        $this->assertSame(180, $line->taxAmount()->amountMinor());
        $this->assertSame(1080, $line->total()->amountMinor());
    }

    public function test_quantity_multiplies_before_discount_and_tax(): void
    {
        $line = DocumentLine::create(
            'org_01', 'quotation', 'quo_01', 'Widget', 5.0, Money::ofMinor(1000, 'EUR'),
            discountType: 'percentage', discountValue: 10.0, taxRate: 20.0,
        );

        // gross = 5000, discount = 500, subtotal = 4500, tax = 900, total = 5400
        $this->assertSame(5000, $line->grossAmount()->amountMinor());
        $this->assertSame(4500, $line->subtotal()->amountMinor());
        $this->assertSame(900, $line->taxAmount()->amountMinor());
        $this->assertSame(5400, $line->total()->amountMinor());
    }
}
