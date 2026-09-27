<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Unit\Infrastructure;

use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Unlike most of this monorepo's tests, this one needs no database or
 * external service — dompdf is a pure-PHP Composer dependency — so it runs
 * for real in every environment, including this sandbox.
 */
final class PdfRendererTest extends TestCase
{
    public function test_renders_html_to_a_valid_pdf_document(): void
    {
        $pdf = (new PdfRenderer())->render('<h1>Invoice</h1><p>Total: 100 EUR</p>');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
    }

    public function test_applies_custom_css(): void
    {
        $pdf = (new PdfRenderer())->render('<h1>Invoice</h1>', 'h1 { color: red; }');

        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
