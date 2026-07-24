<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class ImportRowOutcome
{
    /**
     * @param 'created'|'updated'|'skipped'|'failed'|'would_create'|'would_update' $outcome
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $outcome,
        public readonly ?string $errorMessage = null,
        public readonly ?string $entityUid = null,
    ) {
    }
}
