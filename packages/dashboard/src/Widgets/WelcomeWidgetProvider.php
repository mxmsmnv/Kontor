<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Widgets;

use Kontor\Dashboard\Contracts\WidgetProviderInterface;

/**
 * A trivial, dependency-free built-in widget proving the registry/render
 * pipeline end-to-end. Real per-component widgets stay in their owning
 * packages, so this package remains dependency-free.
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
