<?php

declare(strict_types=1);

namespace Kontor\Expenses\Tests\Unit\Domain;

use Kontor\Expenses\Domain\Expense;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class ExpenseTest extends TestCase
{
    private function expense(): Expense
    {
        return Expense::create('org_01', 'cat_01', 'Taxi to airport', Money::ofMinor(4500, 'EUR'), new \DateTimeImmutable('2026-01-01'));
    }

    public function test_create_starts_as_a_draft_with_no_receipt(): void
    {
        $expense = $this->expense();

        $this->assertTrue($expense->isDraft());
        $this->assertFalse($expense->hasReceipt());
        $this->assertTrue($expense->isCancellable());
    }

    public function test_has_receipt_reflects_the_file_reference(): void
    {
        $expense = Expense::create(
            'org_01', 'cat_01', 'Hotel', Money::ofMinor(20000, 'EUR'), new \DateTimeImmutable('2026-01-01'),
            receiptFileUid: 'file_01',
        );

        $this->assertTrue($expense->hasReceipt());
    }

    public function test_is_cancellable_only_before_a_decision(): void
    {
        $expense = $this->expense();

        $expense->status = 'submitted';
        $this->assertTrue($expense->isCancellable());

        $expense->status = 'approved';
        $this->assertFalse($expense->isCancellable());

        $expense->status = 'rejected';
        $this->assertFalse($expense->isCancellable());
    }
}
