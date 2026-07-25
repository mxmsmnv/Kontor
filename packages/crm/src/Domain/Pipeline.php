<?php

declare(strict_types=1);

namespace Kontor\CRM\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#13.2
 */
final class Pipeline
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public string $entityType,
        public bool $isDefault,
        public string $status,
        public array $settings = [],
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function create(
        string $organizationId,
        string $name,
        string $entityType = 'deal',
        bool $isDefault = false,
        string $status = 'active',
        array $settings = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            entityType: $entityType,
            isDefault: $isDefault,
            status: $status,
            settings: $settings,
        );
    }
}
