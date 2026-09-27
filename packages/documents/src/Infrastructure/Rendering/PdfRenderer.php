<?php

declare(strict_types=1);

namespace Kontor\Documents\Infrastructure\Rendering;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Wraps dompdf (kontor.md Substage 4.2 "PDF" milestone). dompdf was chosen
 * over openspout/phpoffice-style libraries because its Composer constraint
 * (`^7.1 || ^8.0`) doesn't narrow Kontor's `php >=8.2` floor, and it needs
 * no external binary (unlike wkhtmltopdf-based approaches) — a plain
 * Composer dependency is enough. Remote resource loading stays disabled
 * (the default): template HTML is either our own trusted output or
 * organization-authored template content, never third-party HTML, so there
 * is nothing legitimate for isRemoteEnabled to fetch.
 */
final class PdfRenderer
{
    public function render(string $html, ?string $css = null): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultPaperSize('a4');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->wrap($html, $css), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    private function wrap(string $html, ?string $css): string
    {
        $style = $css !== null && $css !== '' ? "<style>{$css}</style>" : '';

        return "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">{$style}</head><body>{$html}</body></html>";
    }
}
