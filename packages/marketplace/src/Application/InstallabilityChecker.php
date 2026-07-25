<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Application;

use Kontor\Core\Application\DependencyChecker;
use Kontor\Core\Domain\ComponentManifest;
use Kontor\Marketplace\Domain\MarketplaceListing;
use Kontor\Marketplace\DTO\InstallabilityResult;

/**
 * Answers "can/should I install this listing?" by combining
 * `kontor/core`'s own `DependencyChecker` (requires/conflicts against
 * what's currently installed — kontor.md#22.2) with `AdvisoryService`
 * (does an open critical advisory affect this exact version) — reusing
 * both rather than a third, marketplace-specific compatibility
 * implementation. Marketplace only ever *recommends*; actually
 * installing a component remains `Kontor\Core\Application\ComponentManager`'s
 * own job (Substage 1.3), not retrofitted here.
 */
final class InstallabilityChecker
{
    public function __construct(
        private readonly AdvisoryService $advisories,
        private readonly DependencyChecker $dependencyChecker = new DependencyChecker(),
    ) {
    }

    /**
     * @param array<string, ComponentManifest> $installedManifests keyed by composer package name
     */
    public function check(
        MarketplaceListing $listing,
        array $installedManifests,
        string $phpVersion,
        string $processWireVersion,
    ): InstallabilityResult {
        $manifest = ComponentManifest::fromArray($listing->manifest);

        $dependencyResult = $this->dependencyChecker->check($manifest, $installedManifests, $phpVersion, $processWireVersion);
        $advisories = $this->advisories->affecting($listing->package, $listing->version);

        return new InstallabilityResult($dependencyResult, $advisories);
    }
}
