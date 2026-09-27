<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class RestoreResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $restoredCount = 0,
        public readonly array $errors = [],
    ) {
    }
}
