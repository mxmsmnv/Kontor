<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Unit\Application;

use Kontor\Documents\Application\DocumentRenderService;
use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Documents\Infrastructure\Rendering\TemplateEngine;
use PHPUnit\Framework\TestCase;

final class DocumentRenderServiceTest extends TestCase
{
    private function template(string $bodyHtml, ?string $customCss = null): DocumentTemplate
    {
        return DocumentTemplate::create(
            organizationId: 'org_01',
            templateKey: 'quotation',
            documentType: 'quotation',
            language: 'en',
            name: 'Standard quotation',
            bodyHtml: $bodyHtml,
            customCss: $customCss,
        );
    }

    public function test_render_body_substitutes_data_into_the_template(): void
    {
        $service = new DocumentRenderService(new TemplateEngine(), new PdfRenderer());

        $html = $service->renderBody($this->template('<p>{{customerName}}</p>'), ['customerName' => 'Acme']);

        $this->assertSame('<p>Acme</p>', $html);
    }

    public function test_render_preview_html_inlines_custom_css(): void
    {
        $service = new DocumentRenderService(new TemplateEngine(), new PdfRenderer());

        $html = $service->renderPreviewHtml($this->template('<p>{{name}}</p>', 'p { color: red; }'), ['name' => 'Acme']);

        $this->assertStringContainsString('<style>p { color: red; }</style>', $html);
        $this->assertStringContainsString('<p>Acme</p>', $html);
    }

    public function test_render_pdf_produces_a_valid_pdf(): void
    {
        $service = new DocumentRenderService(new TemplateEngine(), new PdfRenderer());

        $pdf = $service->renderPdf($this->template('<h1>{{title}}</h1>'), ['title' => 'Quotation Q-0001']);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
