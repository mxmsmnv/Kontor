<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Contracts;

/**
 * Not a canonical SDK contract (kontor.md section 9 has no widget entry) —
 * invented for this substage, same status as kontor/search's own
 * SearchIndexerInterface or kontor/crm's CRMServiceInterface. Lives local
 * to kontor/dashboard rather than in the SDK for the same reason
 * SearchProviderRegistry lives in kontor/search rather than kontor/core:
 * this is this package's own extension point, not a cross-cutting
 * capability every component is expected to know about.
 *
 * render() returns data, not HTML/markup — there's no admin UI in this
 * monorepo yet (kontor.md#8.4's "widgets" are an admin-layer concern);
 * a future admin front-end renders whatever shape a widget returns.
 */
interface WidgetProviderInterface
{
    public function key(): string;

    public function title(): string;

    /**
     * @return array<string, mixed>
     */
    public function render(string $organizationUid, ?int $userId): array;
}
