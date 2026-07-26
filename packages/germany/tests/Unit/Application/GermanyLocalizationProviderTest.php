<?php

declare(strict_types=1);

namespace Kontor\Germany\Tests\Unit\Application;

use Kontor\Germany\Application\GermanyLocalizationProvider;
use PHPUnit\Framework\TestCase;

final class GermanyLocalizationProviderTest extends TestCase
{
    public function test_country_code(): void
    {
        $this->assertSame('DE', (new GermanyLocalizationProvider())->countryCode());
    }

    public function test_supported_document_formats(): void
    {
        $this->assertSame(['xrechnung', 'zugferd'], (new GermanyLocalizationProvider())->supportedDocumentFormats());
    }

    public function test_validate_tax_id_delegates_to_the_checksum_validator(): void
    {
        $provider = new GermanyLocalizationProvider();

        $this->assertTrue($provider->validateTaxId('DE811569869'));
        $this->assertFalse($provider->validateTaxId('DE811569868'));
    }
}
