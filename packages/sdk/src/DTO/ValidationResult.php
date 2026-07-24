<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class ValidationResult
{
    /**
     * @param array<string, string[]> $errors field => messages
     */
    private function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
    ) {
    }

    public static function valid(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, string[]> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }
}
