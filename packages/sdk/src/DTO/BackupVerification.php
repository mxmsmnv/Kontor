<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class BackupVerification
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly bool $verified,
        public readonly array $errors = [],
        public readonly ?string $checksum = null,
    ) {
    }
}
