<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ReportQuery
{
    public function __construct(
        public readonly string $organizationId,
        public readonly array $filters = [],
        public readonly array $groupBy = [],
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
    ) {
    }
}
