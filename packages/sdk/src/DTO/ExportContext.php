<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ExportContext
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $actorType,
        public readonly ?string $actorId,
        public readonly string $mode = 'machine',
        public readonly ?string $locale = null,
    ) {
    }
}
