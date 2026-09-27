<?php

declare(strict_types=1);

namespace Kontor\Portal\DTO;

final class PortalDownloadResult
{
    private function __construct(
        public readonly int $status,
        public readonly ?string $mimeType,
        public readonly mixed $stream,
    ) {
    }

    public static function found(mixed $stream, string $mimeType): self
    {
        return new self(200, $mimeType, $stream);
    }

    public static function forbidden(): self
    {
        return new self(403, null, null);
    }

    public static function notFound(): self
    {
        return new self(404, null, null);
    }
}
