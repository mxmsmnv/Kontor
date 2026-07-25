<?php

declare(strict_types=1);

namespace Kontor\Documents\Application;

use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Documents\Infrastructure\Rendering\TemplateEngine;

/**
 * Turns a DocumentTemplate + data array into HTML (preview) or PDF (the
 * "PDF" milestone) output. Deliberately has no persistence dependency —
 * resolving *which* template version to render is TemplateRepository's job
 * (::findCurrentVersion(), including the English-fallback rule); this class
 * only renders a template object it's handed. Never mutates anything —
 * persisting the result as an immutable issued snapshot is
 * DocumentSnapshotBuilder's job, layered on top of this one.
 */
final class DocumentRenderService
{
    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly PdfRenderer $pdf,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderBody(DocumentTemplate $template, array $data): string
    {
        return $this->engine->render($template->bodyHtml, $data);
    }

    /**
     * Standalone HTML document (body + custom_css inlined) — the "HTML
     * preview" capability implied by kontor.md#26.
     *
     * @param array<string, mixed> $data
     */
    public function renderPreviewHtml(DocumentTemplate $template, array $data): string
    {
        $body = $this->renderBody($template, $data);
        $style = $template->customCss !== null && $template->customCss !== '' ? "<style>{$template->customCss}</style>" : '';

        return "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">{$style}</head><body>{$body}</body></html>";
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPdf(DocumentTemplate $template, array $data): string
    {
        return $this->pdf->render($this->renderBody($template, $data), $template->customCss);
    }
}
