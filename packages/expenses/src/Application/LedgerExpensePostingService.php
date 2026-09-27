<?php

declare(strict_types=1);

namespace Kontor\Expenses\Application;

use Kontor\Expenses\Domain\Expense;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use RuntimeException;

final class LedgerExpensePostingService implements ExpensePostingInterface
{
    public const REFERENCE_TYPE = 'expense_reimbursement';

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerEntryRepository $entries,
        private readonly LedgerEntryService $ledger,
        private readonly string $expenseAccountCode = '4000',
        private readonly string $bankAccountCode = '1200',
    ) {
    }

    public function assertCanPost(Expense $expense): void
    {
        [$expenseAccount, $bank] = $this->postingAccounts($expense);

        if (!$expenseAccount->isActive() || $expenseAccount->type !== 'expense') {
            throw new RuntimeException("Expense reimbursement posting requires active expense account {$expenseAccount->code}.");
        }
        if (!$bank->isActive() || $bank->type !== 'asset') {
            throw new RuntimeException("Expense reimbursement posting requires active asset account {$bank->code}.");
        }
        foreach ([$expenseAccount, $bank] as $account) {
            if ($account->currencyCode !== $expense->amount->currencyCode()) {
                throw new RuntimeException(sprintf(
                    'Expense reimbursement account %s uses %s, not %s.',
                    $account->code,
                    $account->currencyCode,
                    $expense->amount->currencyCode(),
                ));
            }
        }
    }

    public function postReimbursement(Expense $expense, ?int $createdBy = null): void
    {
        if ($this->entries->findByReference(self::REFERENCE_TYPE, $expense->uid->toString()) !== null) {
            return;
        }

        $this->assertCanPost($expense);
        [$expenseAccount, $bank] = $this->postingAccounts($expense);
        $this->ledger->record(
            $expense->organizationId,
            'Expense reimbursed: ' . $expense->description,
            $expense->reimbursedAt ?? new \DateTimeImmutable('today'),
            [
                LedgerLineInput::debit($expenseAccount->uid->toString(), $expense->amount),
                LedgerLineInput::credit($bank->uid->toString(), $expense->amount),
            ],
            self::REFERENCE_TYPE,
            $expense->uid->toString(),
            $createdBy,
        );
    }

    /**
     * @return array{0: \Kontor\Ledger\Domain\Account, 1: \Kontor\Ledger\Domain\Account}
     */
    private function postingAccounts(Expense $expense): array
    {
        $expenseAccount = $this->accounts->findByCode($expense->organizationId, $this->expenseAccountCode);
        $bank = $this->accounts->findByCode($expense->organizationId, $this->bankAccountCode);
        if ($expenseAccount === null || $bank === null) {
            throw new RuntimeException(sprintf(
                'Expense reimbursement posting requires ledger accounts %s (expense) and %s (bank).',
                $this->expenseAccountCode,
                $this->bankAccountCode,
            ));
        }

        return [$expenseAccount, $bank];
    }
}
