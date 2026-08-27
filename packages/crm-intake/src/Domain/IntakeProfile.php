<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class IntakeProfile
{
    /** @param array<int, array<string, mixed>> $fields */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public array $fields,
        public bool $isDefault,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /** @param array<int, array<string, mixed>> $fields */
    public static function create(
        string $organizationId,
        string $name,
        array $fields,
        bool $isDefault = true,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            Uid::generate(),
            $organizationId,
            $name,
            $fields,
            $isDefault,
            'active',
            $now,
            $now,
            $createdBy,
        );
    }
}
