<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Domain;

final class Registry
{
    public function __construct(
        public readonly string $name,
        public string $url,
        public readonly string $type,
        public readonly bool $trusted,
        public string $status,
        public ?\DateTimeImmutable $lastSyncedAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function official(string $url): self
    {
        $now = new \DateTimeImmutable();

        return new self('official', $url, 'official', true, 'active', null, $now, $now);
    }

    public static function custom(string $name, string $url, bool $trusted = false): self
    {
        $now = new \DateTimeImmutable();

        return new self($name, $url, 'custom', $trusted, 'active', null, $now, $now);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function recordSync(): void
    {
        $this->lastSyncedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastSyncedAt;
    }

    public function disable(): void
    {
        $this->status = 'disabled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function enable(): void
    {
        $this->status = 'active';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
