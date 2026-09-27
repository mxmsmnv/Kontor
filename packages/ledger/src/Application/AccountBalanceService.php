<?php

declare(strict_types=1);

namespace Kontor\Ledger\Application;

use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\SDK\ValueObjects\Money;

final class AccountBalanceService
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerLineRepository $lines,
    ) {
    }

    public function balance(string $accountUid): Money
    {
        $account = $this->accounts->require($accountUid);

        return AccountBalanceCalculator::calculate(
            $account->isDebitNormal(),
            $this->lines->forAccount($accountUid),
            $account->currencyCode,
        );
    }
}
