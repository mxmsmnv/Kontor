<?php

declare(strict_types=1);

namespace Kontor\SDK\DTO;

final class BackupContext
{
    /**
     * @param 'full'|'data'|'configuration'|'component'|'pre-update'|'snapshot' $kind
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $kind,
        public readonly ?string $reason = null,
        public readonly array $options = [],
    ) {
    }
}
