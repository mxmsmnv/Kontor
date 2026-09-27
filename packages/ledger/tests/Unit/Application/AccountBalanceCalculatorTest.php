<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Unit\Application;

use Kontor\Ledger\Application\AccountBalanceCalculator;
use Kontor\Ledger\Domain\LedgerLine;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class AccountBalanceCalculatorTest extends TestCase
{
    public function test_a_debit_normal_account_increases_with_debits(): void
    {
        $lines = [
            LedgerLine::create('org_1', 'entry_1', 'cash', Money::ofMinor(10000, 'EUR'), Money::zero('EUR')),
            LedgerLine::create('org_1', 'entry_2', 'cash', Money::zero('EUR'), Money::ofMinor(3000, 'EUR')),
        ];

        $balance = AccountBalanceCalculator::calculate(true, $lines, 'EUR');

        $this->assertSame(7000, $balance->amountMinor());
    }

    public function test_a_credit_normal_account_increases_with_credits(): void
    {
        $lines = [
            LedgerLine::create('org_1', 'entry_1', 'revenue', Money::zero('EUR'), Money::ofMinor(10000, 'EUR')),
            LedgerLine::create('org_1', 'entry_2', 'revenue', Money::ofMinor(1000, 'EUR'), Money::zero('EUR')),
        ];

        $balance = AccountBalanceCalculator::calculate(false, $lines, 'EUR');

        $this->assertSame(9000, $balance->amountMinor());
    }

    public function test_no_lines_is_a_zero_balance(): void
    {
        $balance = AccountBalanceCalculator::calculate(true, [], 'EUR');

        $this->assertTrue($balance->isZero());
    }
}
