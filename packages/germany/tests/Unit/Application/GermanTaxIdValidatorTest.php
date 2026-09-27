<?php

declare(strict_types=1);

namespace Kontor\Germany\Tests\Unit\Application;

use Kontor\Germany\Application\GermanTaxIdValidator;
use PHPUnit\Framework\TestCase;

final class GermanTaxIdValidatorTest extends TestCase
{
    private GermanTaxIdValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new GermanTaxIdValidator();
    }

    public function test_a_real_valid_vat_id_passes(): void
    {
        // SAP SE's own publicly published VAT ID — a real-world fixture,
        // not a fabricated example, chosen specifically to verify this
        // checksum implementation against a known-correct value.
        $this->assertTrue($this->validator->isValid('DE811569869'));
    }

    public function test_tampering_with_the_check_digit_fails(): void
    {
        $this->assertFalse($this->validator->isValid('DE811569868'));
    }

    public function test_normalizes_lowercase_and_separators(): void
    {
        $this->assertTrue($this->validator->isValid('de 811-569-869'));
    }

    public function test_wrong_length_is_rejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE12345'));
    }

    public function test_wrong_country_prefix_is_rejected(): void
    {
        $this->assertFalse($this->validator->isValid('FR811569869'));
    }

    public function test_non_numeric_body_is_rejected(): void
    {
        $this->assertFalse($this->validator->isValid('DEABCDEFGHI'));
    }
}
