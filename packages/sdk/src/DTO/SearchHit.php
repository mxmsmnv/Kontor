<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class SearchHit
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityUid,
        public readonly string $title,
        public readonly ?string $subtitle = null,
        public readonly ?string $url = null,
        public readonly float $score = 1.0,
    ) {
    }
}
