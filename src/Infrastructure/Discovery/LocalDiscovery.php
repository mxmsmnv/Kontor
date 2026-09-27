<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Discovery;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Infrastructure\Manifest\ManifestReader;

/**
 * Scans immediate subdirectories of a components root (e.g. site/modules)
 * for a kontor.json and parses each into a ComponentManifest
 * (Substage 1.3 "local discovery" milestone).
 */
final class LocalDiscovery
{
    public function __construct(private readonly ManifestReader $manifestReader = new ManifestReader())
    {
    }

    /**
     * @return array<int, ComponentManifest> manifests that failed to parse are skipped, not thrown
     */
    public function scan(string $rootDir): array
    {
        if (!is_dir($rootDir)) {
            return [];
        }

        $manifests = [];

        foreach (scandir($rootDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $manifestPath = $rootDir . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'kontor.json';

            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $manifests[] = $this->manifestReader->readFile($manifestPath);
            } catch (\Throwable) {
                continue;
            }
        }

        return $manifests;
    }
}
