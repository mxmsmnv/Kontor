<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ImportContext
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $batchId,
        public readonly bool $dryRun,
        public readonly string $actorType,
        public readonly ?string $actorId,
        public readonly array $fieldMapping = [],
    ) {
    }
}
