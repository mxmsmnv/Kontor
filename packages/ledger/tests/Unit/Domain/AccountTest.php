<?php

declare(strict_types=1);

namespace Kontor\Ledger\Tests\Unit\Domain;

use InvalidArgumentException;
use Kontor\Ledger\Domain\Account;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function test_asset_and_expense_accounts_are_debit_normal(): void
    {
        $asset = Account::create('org_1', '1000', 'Cash', 'asset', 'EUR');
        $expense = Account::create('org_1', '5000', 'Rent', 'expense', 'EUR');

        $this->assertTrue($asset->isDebitNormal());
        $this->assertTrue($expense->isDebitNormal());
    }

    public function test_liability_equity_and_revenue_accounts_are_credit_normal(): void
    {
        $liability = Account::create('org_1', '2000', 'Accounts Payable', 'liability', 'EUR');
        $equity = Account::create('org_1', '3000', 'Retained Earnings', 'equity', 'EUR');
        $revenue = Account::create('org_1', '4000', 'Sales Revenue', 'revenue', 'EUR');

        $this->assertFalse($liability->isDebitNormal());
        $this->assertFalse($equity->isDebitNormal());
        $this->assertFalse($revenue->isDebitNormal());
    }

    public function test_an_invalid_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Account::create('org_1', '9999', 'Nonsense', 'not-a-real-type', 'EUR');
    }

    public function test_create_defaults_to_active(): void
    {
        $account = Account::create('org_1', '1000', 'Cash', 'asset', 'EUR');

        $this->assertTrue($account->isActive());
    }
}
