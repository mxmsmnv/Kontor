<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Unit\Application;

use Kontor\Documents\Application\DocumentRenderService;
use Kontor\Documents\Application\DocumentSnapshotBuilder;
use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Documents\Infrastructure\Rendering\TemplateEngine;
use PHPUnit\Framework\TestCase;

final class DocumentSnapshotBuilderTest extends TestCase
{
    public function test_build_captures_the_rendered_html_and_exact_template_identity(): void
    {
        $template = DocumentTemplate::create(
            organizationId: 'org_01',
            templateKey: 'quotation',
            documentType: 'quotation',
            language: 'en',
            name: 'Standard quotation',
            bodyHtml: '<p>{{number}} — {{total}}</p>',
            versionNumber: 3,
        );

        $builder = new DocumentSnapshotBuilder(new DocumentRenderService(new TemplateEngine(), new PdfRenderer()));

        $snapshot = $builder->build($template, ['number' => 'Q-0001', 'total' => '120.00 EUR']);

        $this->assertSame($template->uid->toString(), $snapshot['templateUid']);
        $this->assertSame(3, $snapshot['templateVersion']);
        $this->assertSame('en', $snapshot['language']);
        $this->assertSame('<p>Q-0001 — 120.00 EUR</p>', $snapshot['html']);
        $this->assertSame(['number' => 'Q-0001', 'total' => '120.00 EUR'], $snapshot['data']);
        $this->assertNotEmpty($snapshot['renderedAt']);
    }
}
