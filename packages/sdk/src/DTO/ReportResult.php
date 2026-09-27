<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ReportResult
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $totals = [],
        public readonly array $metadata = [],
    ) {
    }
}
