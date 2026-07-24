<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use PHPUnit\Framework\TestCase;

final class TranslationRegistryTest extends TestCase
{
    public function test_falls_back_to_english_for_missing_keys(): void
    {
        $translations = new TranslationRegistry();
        $translations->register('KontorCRM', 'en', ['lead.title' => 'Lead', 'lead.status' => 'Status']);
        $translations->register('KontorCRM', 'fr', ['lead.title' => 'Prospect']);

        $french = $translations->forLanguage('fr');

        $this->assertSame('Prospect', $french['lead.title']);
        $this->assertSame('Status', $french['lead.status']);
    }

    public function test_merges_strings_from_multiple_components(): void
    {
        $translations = new TranslationRegistry();
        $translations->register('KontorCRM', 'en', ['lead.title' => 'Lead']);
        $translations->register('KontorSales', 'en', ['quotation.title' => 'Quotation']);

        $merged = $translations->forLanguage('en');

        $this->assertSame('Lead', $merged['lead.title']);
        $this->assertSame('Quotation', $merged['quotation.title']);
    }

    public function test_to_json_encodes_the_merged_strings(): void
    {
        $translations = new TranslationRegistry();
        $translations->register('KontorCRM', 'en', ['lead.title' => 'Lead']);

        $this->assertJsonStringEqualsJsonString(
            '{"lead.title":"Lead"}',
            $translations->toJson('en')
        );
    }
}
