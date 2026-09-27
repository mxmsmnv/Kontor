<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Application;

use Kontor\Core\Support\VersionConstraint;
use Kontor\Marketplace\Domain\Advisory;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;

/**
 * The "advisories" milestone's query side: which known advisories
 * actually affect a given package version, reusing
 * `Kontor\Core\Support\VersionConstraint::satisfies()` directly rather
 * than a second constraint-matching implementation.
 *
 * The version-matching/severity logic is split into pure static methods
 * (taking an already-fetched `Advisory[]`) so it's unit-testable without
 * a database — the same "pure vs DB-touching split" pattern
 * `kontor/entities`' `EntityViewService` already uses.
 */
final class AdvisoryService
{
    public function __construct(
        private readonly AdvisoryRepository $advisories,
    ) {
    }

    /**
     * @return Advisory[]
     */
    public function affecting(string $package, string $version): array
    {
        return self::filterAffecting($this->advisories->forPackage($package), $version);
    }

    public function hasCriticalAdvisory(string $package, string $version): bool
    {
        return self::containsCritical($this->affecting($package, $version));
    }

    /**
     * @param Advisory[] $advisories every known advisory for one package
     * @return Advisory[] the subset whose affected-version constraint matches
     */
    public static function filterAffecting(array $advisories, string $version): array
    {
        return array_values(array_filter(
            $advisories,
            static fn (Advisory $advisory) => VersionConstraint::satisfies($version, $advisory->affectedVersions),
        ));
    }

    /**
     * @param Advisory[] $advisories
     */
    public static function containsCritical(array $advisories): bool
    {
        foreach ($advisories as $advisory) {
            if ($advisory->severity === 'critical') {
                return true;
            }
        }

        return false;
    }
}
