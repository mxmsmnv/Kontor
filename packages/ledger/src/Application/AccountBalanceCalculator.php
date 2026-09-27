<?php

declare(strict_types=1);

namespace Kontor\Ledger\Application;

use Kontor\Ledger\Domain\LedgerLine;
use Kontor\SDK\ValueObjects\Money;

/**
 * The balance-computation logic itself, extracted out of
 * `AccountBalanceService` so it's unit-testable without a database —
 * the same "pure vs DB-touching split" pattern `LedgerBalanceValidator`
 * uses.
 */
final class AccountBalanceCalculator
{
    /**
     * @param LedgerLine[] $lines every line posted against one account
     */
    public static function calculate(bool $isDebitNormal, array $lines, string $currencyCode): Money
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += $line->debit->amountMinor();
            $totalCredit += $line->credit->amountMinor();
        }

        $net = $isDebitNormal ? $totalDebit - $totalCredit : $totalCredit - $totalDebit;

        return Money::ofMinor($net, $currencyCode);
    }
}
