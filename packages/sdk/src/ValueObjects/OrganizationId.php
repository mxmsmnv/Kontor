<?php

declare(strict_types=1);

namespace Kontor\SDK\ValueObjects;

/**
 * Scopes every business record to an organization (kontor.md#4.2).
 * Single-company installs use one default organization.
 */
final class OrganizationId implements \Stringable
{
    private function __construct(private readonly Uid $uid)
    {
    }

    public static function fromUid(Uid $uid): self
    {
        return new self($uid);
    }

    public static function fromString(string $uid): self
    {
        return new self(Uid::fromString($uid));
    }

    public function uid(): Uid
    {
        return $this->uid;
    }

    public function equals(self $other): bool
    {
        return $this->uid->equals($other->uid);
    }

    public function __toString(): string
    {
        return $this->uid->toString();
    }
}
