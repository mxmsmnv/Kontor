<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class QueueOptions
{
    public function __construct(
        public readonly string $queue = 'default',
        public readonly int $priority = 0,
        public readonly int $maxAttempts = 3,
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
