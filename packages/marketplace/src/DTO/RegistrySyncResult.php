<?php

declare(strict_types=1);

namespace Kontor\Marketplace\DTO;

final class RegistrySyncResult
{
    /**
     * @param string[] $skipped human-readable reasons each skipped entry was rejected
     */
    public function __construct(
        public readonly int $listingsSynced,
        public readonly int $advisoriesSynced,
        public readonly array $skipped,
    ) {
    }
}
