<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class RollbackResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly int $archivedCount,
        public readonly array $errors = [],
    ) {
    }
}
