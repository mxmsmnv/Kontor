<?php

declare(strict_types=1);

namespace Kontor\Ledger\Application;

use Kontor\Ledger\DTO\LedgerLineInput;

/**
 * The double-entry balance check itself, extracted out of
 * `LedgerEntryService` so it's unit-testable without a database — the
 * same "pure vs DB-touching split" pattern
 * `Kontor\Entities\Application\EntityViewService` and
 * `Kontor\Marketplace\Application\AdvisoryService` already use.
 */
final class LedgerBalanceValidator
{
    /**
     * @param LedgerLineInput[] $lineInputs
     *
     * @throws UnbalancedLedgerEntryException
     */
    public static function validate(array $lineInputs): void
    {
        if (count($lineInputs) < 2) {
            throw new UnbalancedLedgerEntryException('A ledger entry needs at least two lines.');
        }

        $netByCurrency = [];

        foreach ($lineInputs as $input) {
            if ($input->debit->isNegative() || $input->credit->isNegative()) {
                throw new UnbalancedLedgerEntryException("A line's debit and credit amounts cannot be negative.");
            }

            if (!$input->debit->isZero() && !$input->credit->isZero()) {
                throw new UnbalancedLedgerEntryException('A single line cannot carry both a debit and a credit.');
            }

            $currency = $input->debit->currencyCode();

            if ($input->credit->currencyCode() !== $currency) {
                throw new UnbalancedLedgerEntryException("A line's debit and credit must use the same currency.");
            }

            $netByCurrency[$currency] = ($netByCurrency[$currency] ?? 0) + $input->debit->amountMinor() - $input->credit->amountMinor();
        }

        foreach ($netByCurrency as $currency => $net) {
            if ($net !== 0) {
                throw new UnbalancedLedgerEntryException("Debits and credits do not balance for {$currency}: net difference of {$net} minor units.");
            }
        }
    }
}
