<?php

declare(strict_types=1);

namespace Kontor\Ledger\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * The "double-entry foundations" milestone: one journal entry. Immutable
 * once recorded — see the migration's own doc comment for why.
 */
final class LedgerEntry
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $description,
        public readonly \DateTimeImmutable $entryDate,
        public readonly ?string $referenceType,
        public readonly ?string $referenceUid,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?int $createdBy,
    ) {
    }

    public static function create(
        string $organizationId,
        string $description,
        \DateTimeImmutable $entryDate,
        ?string $referenceType = null,
        ?string $referenceUid = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            description: $description,
            entryDate: $entryDate,
            referenceType: $referenceType,
            referenceUid: $referenceUid,
            createdAt: new \DateTimeImmutable(),
            createdBy: $createdBy,
        );
    }
}
