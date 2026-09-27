<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class DiscoveredComponent
{
    /**
     * @param 'new'|'update-available'|'up-to-date' $status
     */
    public function __construct(
        public readonly ComponentManifest $manifest,
        public readonly string $status,
        public readonly ?string $installedVersion = null,
    ) {
    }
}
