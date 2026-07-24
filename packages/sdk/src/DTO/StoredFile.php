<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class StoredFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $storage,
        public readonly int $sizeBytes,
        public readonly string $checksum,
        public readonly ?string $mimeType = null,
    ) {
    }
}
