<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "advisories" milestone. `affectedVersions` is a single
 * `Kontor\Core\Support\VersionConstraint` expression (e.g. `"<0.1.1"`) —
 * see `AdvisoryService::affects()`.
 */
final class Advisory
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $package,
        public readonly string $affectedVersions,
        public readonly string $severity,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $url,
        public readonly \DateTimeImmutable $publishedAt,
    ) {
    }

    public static function create(
        string $package,
        string $affectedVersions,
        string $severity,
        string $title,
        ?string $description = null,
        ?string $url = null,
        ?\DateTimeImmutable $publishedAt = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            package: $package,
            affectedVersions: $affectedVersions,
            severity: $severity,
            title: $title,
            description: $description,
            url: $url,
            publishedAt: $publishedAt ?? new \DateTimeImmutable(),
        );
    }
}
