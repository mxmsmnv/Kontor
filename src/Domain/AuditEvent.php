<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class AuditEvent
{
    /**
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed>|null $current
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $component,
        public readonly string $entityType,
        public readonly string $entityUid,
        public readonly string $action,
        public readonly string $actorType,
        public readonly ?string $actorUid,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?array $previous,
        public readonly ?array $current,
        public readonly array $metadata,
    ) {
    }
}
