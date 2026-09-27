<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class AIResponse
{
    /**
     * @param array<string, mixed> $output
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $output = [],
        public readonly bool $requiresConfirmation = false,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
