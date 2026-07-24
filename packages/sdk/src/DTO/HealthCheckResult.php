<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class HealthCheckResult
{
    /**
     * @param 'ok'|'warning'|'critical' $status
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly array $details = [],
    ) {
    }
}
