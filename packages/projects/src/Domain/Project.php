<?php

declare(strict_types=1);

namespace Kontor\Projects\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Project
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $code,
        public string $name,
        public ?string $customerType,
        public ?string $customerUid,
        public string $status,
        public ?\DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public ?int $defaultHourlyRateMinor,
        public ?string $currencyCode,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $code,
        string $name,
        ?string $customerType = null,
        ?string $customerUid = null,
        ?int $defaultHourlyRateMinor = null,
        ?string $currencyCode = null,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            code: $code,
            name: $name,
            customerType: $customerType,
            customerUid: $customerUid,
            status: 'active',
            startDate: null,
            endDate: null,
            defaultHourlyRateMinor: $defaultHourlyRateMinor,
            currencyCode: $currencyCode !== null ? strtoupper($currencyCode) : null,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
