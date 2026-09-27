<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class RestoreContext
{
    public function __construct(
        public readonly string $organizationId,
        public readonly bool $dryRun = false,
        public readonly array $options = [],
    ) {
    }
}
