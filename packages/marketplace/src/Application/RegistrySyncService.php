<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Application;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Marketplace\Contracts\RegistryClientInterface;
use Kontor\Marketplace\Domain\Advisory;
use Kontor\Marketplace\Domain\MarketplaceListing;
use Kontor\Marketplace\Domain\Publisher;
use Kontor\Marketplace\Domain\Registry;
use Kontor\Marketplace\DTO\RegistrySyncResult;
use Kontor\Marketplace\Infrastructure\Persistence\AdvisoryRepository;
use Kontor\Marketplace\Infrastructure\Persistence\ListingRepository;
use Kontor\Marketplace\Infrastructure\Persistence\PublisherRepository;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;

/**
 * The "official registry"/"custom registry"/"component metadata"/
 * "publisher model" milestones, all at once: fetches a registry's index
 * (`{"components": [...], "advisories": [...]}`, each entry shaped like
 * a real kontor.json — kontor.md#22.1) and ingests both in one pass — a
 * deliberate simplification over a separate advisory feed, since this
 * substage doesn't require one.
 *
 * Each component entry is parsed with `Kontor\Core\Domain\ComponentManifest::fromArray()`
 * directly — the exact same validation `kontor/core`'s own
 * `LocalDiscovery` applies to a locally-installed component's kontor.json
 * — so a malformed entry from a registry is rejected the same way a
 * malformed local manifest would be, not with a second validation
 * implementation. A malformed entry is skipped (recorded in
 * `RegistrySyncResult::$skipped`), not thrown — one bad entry doesn't
 * abort the rest of the sync, the same "one failure doesn't stop the
 * rest" behavior used throughout this monorepo.
 *
 * Publisher trust: a publisher only ever becomes `verified` the first
 * time it's seen via a *trusted* registry sync (`Registry::$trusted` —
 * true for the official registry, opt-in for a custom one) and is never
 * un-verified by a later untrusted sync.
 */
final class RegistrySyncService
{
    public function __construct(
        private readonly RegistryClientInterface $client,
        private readonly RegistryRepository $registries,
        private readonly ListingRepository $listings,
        private readonly PublisherRepository $publishers,
        private readonly AdvisoryRepository $advisories,
    ) {
    }

    public function sync(string $registryName): RegistrySyncResult
    {
        $registry = $this->registries->require($registryName);
        $payload = $this->client->fetch($registry->url);

        $data = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

        $listingsSynced = 0;
        $advisoriesSynced = 0;
        $skipped = [];

        foreach ((array) ($data['components'] ?? []) as $entry) {
            try {
                $this->syncListing($registry, (array) $entry);
                $listingsSynced++;
            } catch (\Throwable $e) {
                $skipped[] = "component: {$e->getMessage()}";
            }
        }

        foreach ((array) ($data['advisories'] ?? []) as $entry) {
            try {
                if ($this->syncAdvisory((array) $entry)) {
                    $advisoriesSynced++;
                }
            } catch (\Throwable $e) {
                $skipped[] = "advisory: {$e->getMessage()}";
            }
        }

        $registry->recordSync();
        $this->registries->save($registry);

        return new RegistrySyncResult($listingsSynced, $advisoriesSynced, $skipped);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function syncListing(Registry $registry, array $entry): void
    {
        $manifest = ComponentManifest::fromArray($entry);
        $publisherName = isset($entry['author']['name']) ? (string) $entry['author']['name'] : null;

        if ($publisherName !== null) {
            $this->upsertPublisher($publisherName, isset($entry['author']['url']) ? (string) $entry['author']['url'] : null, $registry->trusted);
        }

        $this->listings->save(new MarketplaceListing(
            registryName: $registry->name,
            package: $manifest->package,
            name: $manifest->name,
            version: $manifest->version,
            title: isset($entry['title']) ? (string) $entry['title'] : null,
            description: isset($entry['description']) ? (string) $entry['description'] : null,
            license: isset($entry['license']) ? (string) $entry['license'] : null,
            repositoryUrl: isset($entry['repository']) ? (string) $entry['repository'] : null,
            publisherName: $publisherName,
            manifest: $manifest->raw,
            syncedAt: new \DateTimeImmutable(),
        ));
    }

    private function upsertPublisher(string $name, ?string $url, bool $trustedSource): void
    {
        $publisher = $this->publishers->find($name);

        if ($publisher === null) {
            $this->publishers->save(Publisher::create($name, $url, verified: $trustedSource));

            return;
        }

        $publisher->url = $url ?? $publisher->url;

        if ($trustedSource && !$publisher->verified) {
            $publisher->verify();
        }

        $this->publishers->save($publisher);
    }

    /**
     * @param array<string, mixed> $entry
     * @return bool true if a new advisory was inserted, false if it was already known
     */
    private function syncAdvisory(array $entry): bool
    {
        foreach (['package', 'affectedVersions', 'severity', 'title'] as $required) {
            if (!isset($entry[$required])) {
                throw new \RuntimeException("advisory entry is missing required field \"{$required}\"");
            }
        }

        $publishedAt = isset($entry['publishedAt'])
            ? new \DateTimeImmutable((string) $entry['publishedAt'])
            : new \DateTimeImmutable();

        if ($this->advisories->findExisting((string) $entry['package'], (string) $entry['title'], $publishedAt) !== null) {
            return false;
        }

        $this->advisories->insert(Advisory::create(
            package: (string) $entry['package'],
            affectedVersions: (string) $entry['affectedVersions'],
            severity: (string) $entry['severity'],
            title: (string) $entry['title'],
            description: isset($entry['description']) ? (string) $entry['description'] : null,
            url: isset($entry['url']) ? (string) $entry['url'] : null,
            publishedAt: $publishedAt,
        ));

        return true;
    }
}
