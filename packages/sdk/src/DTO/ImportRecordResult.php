<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ImportRecordResult
{
    /**
     * @param 'created'|'updated'|'skipped'|'failed' $outcome
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $entityUid = null,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
