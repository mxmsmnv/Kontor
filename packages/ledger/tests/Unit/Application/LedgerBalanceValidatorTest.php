<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Unit\Application;

use Kontor\Ledger\Application\LedgerBalanceValidator;
use Kontor\Ledger\Application\UnbalancedLedgerEntryException;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class LedgerBalanceValidatorTest extends TestCase
{
    public function test_balanced_lines_pass(): void
    {
        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash', Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit('revenue', Money::ofMinor(10000, 'EUR')),
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_unbalanced_lines_are_rejected(): void
    {
        $this->expectException(UnbalancedLedgerEntryException::class);

        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash', Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit('revenue', Money::ofMinor(9000, 'EUR')),
        ]);
    }

    public function test_fewer_than_two_lines_is_rejected(): void
    {
        $this->expectException(UnbalancedLedgerEntryException::class);

        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash', Money::ofMinor(10000, 'EUR')),
        ]);
    }

    public function test_a_line_with_both_a_debit_and_a_credit_is_rejected(): void
    {
        $this->expectException(UnbalancedLedgerEntryException::class);

        LedgerBalanceValidator::validate([
            new LedgerLineInput('cash', Money::ofMinor(100, 'EUR'), Money::ofMinor(50, 'EUR')),
            LedgerLineInput::credit('revenue', Money::ofMinor(50, 'EUR')),
        ]);
    }

    public function test_a_negative_amount_is_rejected(): void
    {
        $this->expectException(UnbalancedLedgerEntryException::class);

        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash', Money::ofMinor(-100, 'EUR')),
            LedgerLineInput::credit('revenue', Money::ofMinor(-100, 'EUR')),
        ]);
    }

    public function test_mismatched_currencies_on_one_line_are_rejected(): void
    {
        $this->expectException(UnbalancedLedgerEntryException::class);

        LedgerBalanceValidator::validate([
            new LedgerLineInput('cash', Money::ofMinor(100, 'EUR'), Money::ofMinor(100, 'USD')),
            LedgerLineInput::credit('revenue', Money::ofMinor(100, 'EUR')),
        ]);
    }

    public function test_multiple_currencies_each_balance_independently(): void
    {
        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash-eur', Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::credit('revenue-eur', Money::ofMinor(10000, 'EUR')),
            LedgerLineInput::debit('cash-usd', Money::ofMinor(5000, 'USD')),
            LedgerLineInput::credit('revenue-usd', Money::ofMinor(5000, 'USD')),
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_more_than_two_lines_can_still_balance(): void
    {
        LedgerBalanceValidator::validate([
            LedgerLineInput::debit('cash', Money::ofMinor(6000, 'EUR')),
            LedgerLineInput::debit('bank-fees', Money::ofMinor(4000, 'EUR')),
            LedgerLineInput::credit('revenue', Money::ofMinor(10000, 'EUR')),
        ]);

        $this->addToAssertionCount(1);
    }
}
