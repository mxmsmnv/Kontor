<?php

declare(strict_types=1);

namespace Kontor\Ledger\DTO;

use Kontor\SDK\ValueObjects\Money;

/**
 * One line of a ledger entry being recorded — a debit OR a credit
 * against one account, never both (enforced by
 * `LedgerEntryService::record()`, not by this plain data holder).
 */
final class LedgerLineInput
{
    public function __construct(
        public readonly string $accountUid,
        public readonly Money $debit,
        public readonly Money $credit,
    ) {
    }

    public static function debit(string $accountUid, Money $amount): self
    {
        return new self($accountUid, $amount, Money::zero($amount->currencyCode()));
    }

    public static function credit(string $accountUid, Money $amount): self
    {
        return new self($accountUid, Money::zero($amount->currencyCode()), $amount);
    }
}
