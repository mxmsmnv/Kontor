<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class AIRequest
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly string $capability,
        public readonly string $organizationId,
        public readonly array $input,
        public readonly bool $requiresConfirmation = true,
        public readonly ?string $actorId = null,
    ) {
    }
}
