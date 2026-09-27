<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Health;

use Kontor\Marketplace\Application\AdvisoryService;
use Kontor\Marketplace\Infrastructure\Persistence\ListingRepository;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Two real invariants, not just a row count: every active registry
 * should have synced recently (a registry that silently stopped
 * responding is worth surfacing), and no currently-known listing should
 * be affected by an unresolved critical advisory — the same "check for a
 * real anomaly" approach `kontor/entities`'/`kontor/api`'s own health
 * checks use.
 */
final class MarketplaceHealthCheck implements HealthCheckInterface
{
    private const STALE_AFTER_DAYS = 30;

    public function __construct(
        private readonly RegistryRepository $registries,
        private readonly ListingRepository $listings,
        private readonly AdvisoryService $advisories,
    ) {
    }

    public function key(): string
    {
        return 'marketplace';
    }

    public function run(): HealthCheckResult
    {
        $staleCutoff = (new \DateTimeImmutable())->modify('-'.self::STALE_AFTER_DAYS.' days');
        $staleRegistries = [];

        foreach ($this->registries->active() as $registry) {
            if ($registry->lastSyncedAt === null || $registry->lastSyncedAt < $staleCutoff) {
                $staleRegistries[] = $registry->name;
            }
        }

        $criticalAdvisoryPackages = [];

        foreach ($this->listings->all() as $listing) {
            if ($this->advisories->hasCriticalAdvisory($listing->package, $listing->version)) {
                $criticalAdvisoryPackages[] = $listing->package;
            }
        }

        if ($staleRegistries === [] && $criticalAdvisoryPackages === []) {
            return new HealthCheckResult(
                'ok',
                'Every active registry is synced and no listing has an open critical advisory.',
                ['staleRegistries' => [], 'criticalAdvisoryPackages' => []],
            );
        }

        $messages = [];

        if ($staleRegistries !== []) {
            $messages[] = count($staleRegistries).' registry(ies) not synced in over '.self::STALE_AFTER_DAYS.' days: '.implode(', ', $staleRegistries);
        }

        if ($criticalAdvisoryPackages !== []) {
            $messages[] = count($criticalAdvisoryPackages).' listing(s) affected by a critical advisory: '.implode(', ', $criticalAdvisoryPackages);
        }

        return new HealthCheckResult(
            'warning',
            implode(' ', $messages),
            ['staleRegistries' => $staleRegistries, 'criticalAdvisoryPackages' => $criticalAdvisoryPackages],
        );
    }
}
