<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class IntakeResponse
{
    /** @param array<string, mixed> $answers */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $profileUid,
        public readonly string $entityType,
        public readonly string $entityUid,
        public array $answers,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $answers */
    public static function create(
        string $organizationId,
        string $profileUid,
        string $entityType,
        string $entityUid,
        array $answers,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            Uid::generate(),
            $organizationId,
            $profileUid,
            $entityType,
            $entityUid,
            $answers,
            $now,
            $now,
        );
    }
}
