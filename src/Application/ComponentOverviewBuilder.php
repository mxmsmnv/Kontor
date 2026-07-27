<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

final class ComponentOverviewBuilder
{
    /**
     * @param array<int, array<string, mixed>> $registryRows
     * @param array<string, array<string, mixed>> $runtimeInfo
     * @return array{
     *   components: array<int, array<string, mixed>>,
     *   counts: array{total: int, enabled: int, attention: int}
     * }
     */
    public function build(
        array $registryRows,
        array $runtimeInfo,
        string $query = '',
        string $status = '',
    ): array {
        $query = mb_strtolower(trim($query));
        $components = [];
        $enabled = 0;
        $attention = 0;

        foreach ($registryRows as $row) {
            $name = (string) ($row['name'] ?? '');
            $moduleName = $this->moduleName($name);
            $runtime = $runtimeInfo[$moduleName] ?? [];
            $runtimeInstalled = (bool) ($runtime['installed'] ?? false);
            $registryVersion = (string) ($row['version'] ?? '');
            $runtimeVersion = (string) ($runtime['version'] ?? '');
            $versionInSync = $runtimeInstalled
                && $registryVersion !== ''
                && hash_equals($registryVersion, $runtimeVersion);
            $componentStatus = (string) ($row['status'] ?? 'unknown');
            $needsAttention = $componentStatus !== 'enabled' || !$versionInSync;

            if ($componentStatus === 'enabled') {
                $enabled++;
            }

            if ($needsAttention) {
                $attention++;
            }

            $component = [
                'name' => $name,
                'moduleName' => $moduleName,
                'title' => (string) ($runtime['title'] ?? $this->title($name)),
                'summary' => (string) ($runtime['summary'] ?? 'No component description is available.'),
                'icon' => (string) ($runtime['icon'] ?? 'cube'),
                'status' => $componentStatus,
                'runtimeInstalled' => $runtimeInstalled,
                'runtimeVersion' => (string) ($runtime['versionStr'] ?? $runtimeVersion ?: '—'),
                'registryVersion' => $registryVersion !== '' ? $this->version($registryVersion) : '—',
                'versionInSync' => $versionInSync,
                'needsAttention' => $needsAttention,
                'requires' => array_values(array_filter(
                    (array) ($runtime['requires'] ?? []),
                    static fn (string $dependency): bool => $dependency !== 'ProcessWire'
                )),
                'href' => (string) ($runtime['href'] ?? ''),
                'workspaceUrl' => (string) ($runtime['workspaceUrl'] ?? ''),
                'settingsUrl' => (string) ($runtime['settingsUrl'] ?? ''),
                'updatedAt' => (string) ($row['updated_at'] ?? ''),
            ];
            $haystack = mb_strtolower(implode(' ', [
                $component['name'],
                $component['moduleName'],
                $component['title'],
                $component['summary'],
            ]));

            if ($query !== '' && !str_contains($haystack, $query)) {
                continue;
            }

            if ($status === 'attention' && !$needsAttention) {
                continue;
            }

            if ($status !== '' && $status !== 'attention' && $componentStatus !== $status) {
                continue;
            }

            $components[] = $component;
        }

        return [
            'components' => $components,
            'counts' => [
                'total' => count($registryRows),
                'enabled' => $enabled,
                'attention' => $attention,
            ],
        ];
    }

    public function moduleName(string $componentName): string
    {
        if ($componentName === 'core') {
            return 'Kontor';
        }

        return 'Kontor' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $componentName)));
    }

    private function title(string $name): string
    {
        return 'Kontor ' . ucwords(str_replace(['-', '_'], ' ', $name));
    }

    private function version(string $version): string
    {
        if (preg_match('/^\d{3}$/', $version) !== 1) {
            return $version;
        }

        return implode('.', array_map('intval', str_split($version)));
    }
}
