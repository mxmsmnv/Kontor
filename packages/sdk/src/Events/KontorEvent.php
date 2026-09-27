<?php

declare(strict_types=1);

namespace Kontor\SDK\Events;

use Kontor\SDK\ValueObjects\Uid;

/**
 * Canonical event envelope (kontor.md#21). Every event published on the
 * Kontor event bus, and every webhook delivery, uses this exact shape.
 */
final class KontorEvent
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public readonly string $event,
        public readonly string $version,
        public readonly Uid $eventId,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly string $organizationId,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $actorType,
        public readonly ?string $actorId,
        public readonly Uid $correlationId,
        public readonly ?Uid $causationId,
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(
        string $event,
        string $organizationId,
        string $entityType,
        string $entityId,
        string $actorType,
        ?string $actorId,
        array $data = [],
        ?Uid $correlationId = null,
        ?Uid $causationId = null,
        string $version = '1.0',
    ): self {
        return new self(
            event: $event,
            version: $version,
            eventId: Uid::generate(),
            occurredAt: new \DateTimeImmutable(),
            organizationId: $organizationId,
            entityType: $entityType,
            entityId: $entityId,
            actorType: $actorType,
            actorId: $actorId,
            correlationId: $correlationId ?? Uid::generate(),
            causationId: $causationId,
            data: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'version' => $this->version,
            'eventId' => $this->eventId->toString(),
            'occurredAt' => $this->occurredAt->format('Y-m-d\TH:i:s\Z'),
            'organizationId' => $this->organizationId,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'correlationId' => $this->correlationId->toString(),
            'causationId' => $this->causationId?->toString(),
            'data' => $this->data,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
