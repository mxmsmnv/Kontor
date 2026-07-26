<?php

declare(strict_types=1);

namespace Kontor\Documents\Application;

use Kontor\Documents\Domain\DocumentTemplate;

/**
 * The "snapshots" / "immutable issued output" milestone (kontor.md#26):
 * builds the array a consuming component (kontor/sales' Quotation/Order,
 * kontor/invoices' Invoice — all of which already carry an unused
 * snapshot_json column, kontor.md#15.1-15.3) should store verbatim into its
 * own snapshot_json at the moment a document is issued. Capturing the
 * rendered HTML plus the exact template identity and the data it was
 * rendered from means the snapshot stays reproducible even if the template
 * is edited or archived afterward — issuing never re-renders from a
 * "current" template again.
 *
 * Kontor\Documents does not own another component's repository. Consumers
 * call this service at their issue boundary; the shared admin now does so
 * for Sales quotations while invoice and order integrations remain separate.
 */
final class DocumentSnapshotBuilder
{
    public function __construct(private readonly DocumentRenderService $renderer)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{templateUid: string, templateVersion: int, language: string, renderedAt: string, html: string, data: array<string, mixed>}
     */
    public function build(DocumentTemplate $template, array $data): array
    {
        return [
            'templateUid' => $template->uid->toString(),
            'templateVersion' => $template->versionNumber,
            'language' => $template->language,
            'renderedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'html' => $this->renderer->renderBody($template, $data),
            'data' => $data,
        ];
    }
}
