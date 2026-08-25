<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

/**
 * Resolves the admin navigation from declarative route metadata.
 *
 * Keeping module and permission checks here ensures menus, quick access and
 * contextual actions all degrade in the same way when Kontor is installed in
 * a smaller configuration.
 */
final class NavigationAvailability
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param callable(string): bool $moduleInstalled
     * @param callable(string): bool $permissionGranted
     * @return array<string, array<string, mixed>>
     */
    public function resolve(
        array $items,
        callable $moduleInstalled,
        callable $permissionGranted,
    ): array {
        $available = [];

        foreach ($items as $item) {
            $module = trim((string) ($item['module'] ?? ''));
            $permission = trim((string) ($item['permission'] ?? ''));
            $url = (string) ($item['url'] ?? '');

            if ($module !== '' && !$moduleInstalled($module)) {
                continue;
            }
            if ($permission !== '' && !$permissionGranted($permission)) {
                continue;
            }

            $key = $url === '' ? 'dashboard' : trim($url, '/');
            if ($key !== '') {
                $available[$key] = $item;
            }
        }

        return $available;
    }
}
