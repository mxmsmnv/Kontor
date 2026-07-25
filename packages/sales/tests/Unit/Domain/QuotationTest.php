<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Unit\Domain;

use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class QuotationTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $quotation = Quotation::create('org_01', 'contact', 'ct_01', 'EUR');

        $this->assertTrue($quotation->isDraft());
        $this->assertFalse($quotation->isOpen());
        $this->assertFalse($quotation->isClosed());
        $this->assertNull($quotation->number);
    }

    public function test_status_predicates(): void
    {
        $quotation = Quotation::create('org_01', 'contact', 'ct_01', 'EUR');

        $quotation->status = 'issued';
        $this->assertTrue($quotation->isOpen());

        $quotation->status = 'accepted';
        $this->assertTrue($quotation->isAccepted());
        $this->assertTrue($quotation->isClosed());

        $quotation->status = 'cancelled';
        $this->assertTrue($quotation->isClosed());
    }

    public function test_apply_totals_from_lines_aggregates_correctly(): void
    {
        $quotation = Quotation::create('org_01', 'contact', 'ct_01', 'EUR');
        $lines = [
            DocumentLine::create('org_01', 'quotation', $quotation->uid->toString(), 'Widget', 2.0, Money::ofMinor(1000, 'EUR'), taxRate: 20.0),
            DocumentLine::create('org_01', 'quotation', $quotation->uid->toString(), 'Gadget', 1.0, Money::ofMinor(500, 'EUR'), taxRate: 20.0),
        ];

        $quotation->applyTotalsFromLines($lines);

        // subtotal = 2000 + 500 = 2500; tax = 400 + 100 = 500; total = 3000
        $this->assertSame(2500, $quotation->subtotal->amountMinor());
        $this->assertSame(500, $quotation->tax->amountMinor());
        $this->assertSame(3000, $quotation->total->amountMinor());
    }
}
