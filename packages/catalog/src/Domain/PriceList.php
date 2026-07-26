<?php

declare(strict_types=1);

namespace Kontor\Catalog\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#14.2
 */
final class PriceList
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public string $currencyCode,
        public string $status,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
    ) {
    }

    public static function create(
        string $organizationId,
        string $name,
        string $currencyCode,
        string $status = 'active',
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            currencyCode: strtoupper($currencyCode),
            status: $status,
            validFrom: $validFrom,
            validTo: $validTo,
        );
    }

    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->validFrom !== null && $date < $this->validFrom) {
            return false;
        }

        return !($this->validTo !== null && $date > $this->validTo);
    }

    public function duplicate(string $nameSuffix = ' (copy)'): self
    {
        return self::create(
            organizationId: $this->organizationId,
            name: $this->name . $nameSuffix,
            currencyCode: $this->currencyCode,
            status: 'inactive',
            validFrom: $this->validFrom,
            validTo: $this->validTo,
        );
    }
}
