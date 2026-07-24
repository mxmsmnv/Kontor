<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class BackupEstimate
{
    public function __construct(
        public readonly int $itemCount,
        public readonly int $estimatedSizeBytes,
        public readonly array $tables = [],
    ) {
    }
}
