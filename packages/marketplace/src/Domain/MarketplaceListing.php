<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Domain;

/**
 * The "component metadata" milestone. `manifest` is the full raw
 * kontor.json entry (kontor.md#22.1) a registry returned for this
 * component — everything else here is just the subset of it this
 * substage surfaces as its own columns for querying without decoding
 * JSON every time.
 */
final class MarketplaceListing
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $registryName,
        public readonly string $package,
        public string $name,
        public string $version,
        public ?string $title,
        public ?string $description,
        public ?string $license,
        public ?string $repositoryUrl,
        public ?string $publisherName,
        public array $manifest,
        public \DateTimeImmutable $syncedAt,
    ) {
    }
}
