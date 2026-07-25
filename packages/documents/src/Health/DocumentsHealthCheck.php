<?php

declare(strict_types=1);

namespace Kontor\Documents\Health;

use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Documents\Infrastructure\Rendering\TemplateEngine;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * A real round trip through the rendering pipeline (placeholder
 * substitution, then dompdf), not just "is the class loadable" — same
 * standard as FilesHealthCheck's storage round trip. This one needs no
 * database, so it runs for real in every environment, unlike most other
 * packages' DB-gated checks.
 */
final class DocumentsHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly TemplateEngine $engine = new TemplateEngine(),
        private readonly PdfRenderer $pdf = new PdfRenderer(),
    ) {
    }

    public function key(): string
    {
        return 'documents';
    }

    public function run(): HealthCheckResult
    {
        try {
            $html = $this->engine->render('<p>{{greeting}}</p>', ['greeting' => 'health-check']);

            if (!str_contains($html, 'health-check')) {
                return new HealthCheckResult('critical', 'Template rendering did not substitute the probe placeholder.');
            }

            $pdf = $this->pdf->render($html);

            if (!str_starts_with($pdf, '%PDF-')) {
                return new HealthCheckResult('critical', 'PDF renderer did not produce a valid PDF document.');
            }

            return new HealthCheckResult('ok', 'Template rendering and PDF generation are both working.');
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Document rendering failed: {$e->getMessage()}");
        }
    }
}
