<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Supplier
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $code,
        public string $legalName,
        public ?string $email,
        public ?string $phone,
        public string $currencyCode,
        public int $paymentTermsDays,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $code,
        string $legalName,
        string $currencyCode,
        ?string $email = null,
        ?string $phone = null,
        int $paymentTermsDays = 30,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            code: $code,
            legalName: $legalName,
            email: $email,
            phone: $phone,
            currencyCode: strtoupper($currencyCode),
            paymentTermsDays: $paymentTermsDays,
            status: 'active',
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
