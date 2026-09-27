<?php

declare(strict_types=1);

namespace Kontor\Expenses\Application;

use Kontor\Expenses\Domain\Expense;

interface ExpensePostingInterface
{
    public function assertCanPost(Expense $expense): void;

    public function postReimbursement(Expense $expense, ?int $createdBy = null): void;
}
