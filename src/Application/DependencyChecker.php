<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Domain\DependencyCheckResult;
use Kontor\Core\Support\VersionConstraint;

/**
 * Validates a component's `requires` and `conflicts` (kontor.md#22.2)
 * against the running PHP/ProcessWire versions and the set of currently
 * installed manifests, before ComponentManager allows an install or update.
 */
final class DependencyChecker
{
    /**
     * @param array<string, ComponentManifest> $installedManifests keyed by composer package name
     */
    public function check(
        ComponentManifest $manifest,
        array $installedManifests,
        string $phpVersion,
        string $processWireVersion,
    ): DependencyCheckResult {
        $missing = [];

        foreach ($manifest->requires as $package => $constraint) {
            $available = match ($package) {
                'php' => $phpVersion,
                'processwire' => $processWireVersion,
                default => ($installedManifests[$package] ?? null)?->version,
            };

            if ($available === null) {
                $missing[] = "requires \"{$package}\" {$constraint}, which is not installed";

                continue;
            }

            if (!VersionConstraint::satisfies($available, $constraint)) {
                $missing[] = "requires \"{$package}\" {$constraint}, found {$available}";
            }
        }

        $conflicts = [];

        foreach ($manifest->conflicts as $package => $constraint) {
            $installedVersion = ($installedManifests[$package] ?? null)?->version;

            if ($installedVersion !== null && VersionConstraint::satisfies($installedVersion, $constraint)) {
                $conflicts[] = "conflicts with installed \"{$package}\" {$installedVersion}";
            }
        }

        return $missing === [] && $conflicts === []
            ? DependencyCheckResult::ok()
            : DependencyCheckResult::failed($missing, $conflicts);
    }
}
