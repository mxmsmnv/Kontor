<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class ImportBatchResult
{
    /**
     * @param ImportRowOutcome[] $rows every failed row plus every dry-run preview row; successful
     *   live-run rows beyond the counters are not retained individually to keep large-batch memory bounded
     */
    public function __construct(
        public readonly string $batchId,
        public readonly string $entityType,
        public readonly bool $dryRun,
        public readonly int $totalRows,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly int $failed,
        public readonly array $rows,
    ) {
    }
}
