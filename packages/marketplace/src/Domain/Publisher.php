<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Domain;

/**
 * The "publisher model" milestone. `verified` is only ever set to `true`
 * by `RegistrySyncService` (the first time this publisher is seen via a
 * trusted registry) — never flipped back to `false` by a later
 * untrusted-registry sync. See that service's own doc comment.
 */
final class Publisher
{
    public function __construct(
        public readonly string $name,
        public ?string $url,
        public bool $verified,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $name, ?string $url, bool $verified = false): self
    {
        $now = new \DateTimeImmutable();

        return new self($name, $url, $verified, $now, $now);
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
