<?php

declare(strict_types=1);

namespace Kontor\Ledger\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * One debit or credit leg of a `LedgerEntry`. A single line can carry
 * both a debit and a credit amount (one of them is always zero in
 * practice, but the schema doesn't force it) — `LedgerEntryService`
 * always writes exactly one side per line; this class doesn't enforce
 * that itself since it's a plain data holder, the same "cross-cutting
 * infrastructure, not a bounded-context entity" reasoning
 * `Kontor\Core\Infrastructure\Persistence\RelationRepository` already
 * uses for why it works with arrays rather than guarding invariants
 * itself.
 */
final class LedgerLine
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $entryUid,
        public readonly string $accountUid,
        public readonly Money $debit,
        public readonly Money $credit,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $entryUid,
        string $accountUid,
        Money $debit,
        Money $credit,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            entryUid: $entryUid,
            accountUid: $accountUid,
            debit: $debit,
            credit: $credit,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
