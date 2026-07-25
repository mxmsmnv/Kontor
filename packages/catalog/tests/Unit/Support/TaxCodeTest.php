<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Unit\Support;

use Kontor\Catalog\Support\TaxCode;
use PHPUnit\Framework\TestCase;

final class TaxCodeTest extends TestCase
{
    public function test_known_defaults(): void
    {
        $taxCodes = new TaxCode();

        $this->assertTrue($taxCodes->isKnown('standard'));
        $this->assertTrue($taxCodes->isKnown('exempt'));
    }

    public function test_unknown_code(): void
    {
        $this->assertFalse((new TaxCode())->isKnown('luxury'));
    }

    public function test_additional_codes_extend_without_mutating_other_instances(): void
    {
        $extended = new TaxCode(['luxury' => 'Luxury goods rate']);

        $this->assertTrue($extended->isKnown('luxury'));
        $this->assertFalse((new TaxCode())->isKnown('luxury'));
    }
}
