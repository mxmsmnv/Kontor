<?php

declare(strict_types=1);

namespace Kontor\Ledger\Domain;

use InvalidArgumentException;
use Kontor\SDK\ValueObjects\Uid;

/**
 * The "chart of accounts" milestone. `type` is the classic five-way
 * double-entry classification; `AccountBalanceService` uses it to
 * decide an account's normal balance side (debit for asset/expense,
 * credit for liability/equity/revenue).
 */
final class Account
{
    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $code,
        public string $name,
        public readonly string $type,
        public readonly ?string $parentUid,
        public readonly string $currencyCode,
        public string $status,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
        public readonly ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    public static function create(
        string $organizationId,
        string $code,
        string $name,
        string $type,
        string $currencyCode,
        ?string $parentUid = null,
        ?int $createdBy = null,
    ): self {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Account type must be one of: '.implode(', ', self::TYPES).'.');
        }

        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            code: $code,
            name: $name,
            type: $type,
            parentUid: $parentUid,
            currencyCode: strtoupper($currencyCode),
            status: 'active',
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
            archivedAt: null,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isArchived();
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * Debit-normal accounts (asset/expense) increase with a debit;
     * credit-normal accounts (liability/equity/revenue) increase with a
     * credit — the standard double-entry convention
     * `AccountBalanceService` relies on.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
