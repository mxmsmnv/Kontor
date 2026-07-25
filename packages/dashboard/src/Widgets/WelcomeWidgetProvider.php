<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Widgets;

use Kontor\Dashboard\Contracts\WidgetProviderInterface;

/**
 * A trivial, dependency-free built-in widget proving the registry/render
 * pipeline end-to-end. Real per-component widgets (a Sales revenue
 * widget, a CRM pipeline widget, a Tasks "my open tasks" widget, …) are
 * future work for those packages to build once kontor/dashboard exists to
 * depend on — not the other way around, so this package stays
 * dependency-free itself.
 */
final class WelcomeWidgetProvider implements WidgetProviderInterface
{
    public function key(): string
    {
        return 'welcome';
    }

    public function title(): string
    {
        return 'Welcome';
    }

    public function render(string $organizationUid, ?int $userId): array
    {
        return [
            'organizationUid' => $organizationUid,
            'userId' => $userId,
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
