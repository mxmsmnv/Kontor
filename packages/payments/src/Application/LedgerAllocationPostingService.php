<?php

declare(strict_types=1);

namespace Kontor\Payments\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Payments\Domain\Payment;
use Kontor\Payments\Domain\PaymentAllocation;
use RuntimeException;

/**
 * Posts invoice-payment allocations into the append-only general ledger.
 *
 * Codes 1200 (bank) and 1400 (trade receivables) are the roles exposed by
 * the illustrative German chart. They are constructor arguments so another
 * country package or installation can map the same bridge differently.
 */
final class LedgerAllocationPostingService implements AllocationPostingInterface
{
    public const POSTING_REFERENCE = 'payment_allocation';
    public const REVERSAL_REFERENCE = 'payment_allocation_reversal';

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerEntryRepository $entries,
        private readonly LedgerEntryService $ledger,
        private readonly string $bankAccountCode = '1200',
        private readonly string $receivablesAccountCode = '1400',
    ) {
    }

    public function assertCanPost(Payment $payment): void
    {
        [$bank, $receivables] = $this->postingAccounts($payment);

        foreach ([$bank, $receivables] as $account) {
            if (!$account->isActive()) {
                throw new RuntimeException("Automatic payment posting requires active account {$account->code}.");
            }
            if ($account->type !== 'asset') {
                throw new RuntimeException("Automatic payment posting requires asset account {$account->code}.");
            }
            if ($account->currencyCode !== $payment->amount->currencyCode()) {
                throw new RuntimeException(sprintf(
                    'Automatic payment posting account %s uses %s, not %s.',
                    $account->code,
                    $account->currencyCode,
                    $payment->amount->currencyCode(),
                ));
            }
        }
    }

    public function postAllocation(
        Payment $payment,
        Invoice $invoice,
        PaymentAllocation $allocation,
        ?int $createdBy = null,
    ): void {
        if ($this->entries->findByReference(self::POSTING_REFERENCE, $allocation->uid->toString()) !== null) {
            return;
        }

        $this->assertCanPost($payment);
        [$bank, $receivables] = $this->postingAccounts($payment);
        $this->ledger->record(
            $payment->organizationId,
            sprintf(
                'Payment %s allocated to invoice %s',
                $payment->number ?? $payment->uid->toString(),
                $invoice->number ?? $invoice->uid->toString(),
            ),
            $payment->paymentDate ?? $allocation->allocatedAt,
            [
                LedgerLineInput::debit($bank->uid->toString(), $allocation->amount),
                LedgerLineInput::credit($receivables->uid->toString(), $allocation->amount),
            ],
            self::POSTING_REFERENCE,
            $allocation->uid->toString(),
            $createdBy,
        );
    }

    public function reverseAllocation(
        Payment $payment,
        Invoice $invoice,
        PaymentAllocation $allocation,
        ?int $createdBy = null,
    ): void {
        if ($this->entries->findByReference(self::REVERSAL_REFERENCE, $allocation->uid->toString()) !== null) {
            return;
        }

        $this->assertCanPost($payment);
        [$bank, $receivables] = $this->postingAccounts($payment);
        $this->ledger->record(
            $payment->organizationId,
            sprintf(
                'Reversal of payment %s allocation to invoice %s',
                $payment->number ?? $payment->uid->toString(),
                $invoice->number ?? $invoice->uid->toString(),
            ),
            new \DateTimeImmutable('today'),
            [
                LedgerLineInput::debit($receivables->uid->toString(), $allocation->amount),
                LedgerLineInput::credit($bank->uid->toString(), $allocation->amount),
            ],
            self::REVERSAL_REFERENCE,
            $allocation->uid->toString(),
            $createdBy,
        );
    }

    /**
     * @return array{0: \Kontor\Ledger\Domain\Account, 1: \Kontor\Ledger\Domain\Account}
     */
    private function postingAccounts(Payment $payment): array
    {
        $bank = $this->accounts->findByCode($payment->organizationId, $this->bankAccountCode);
        $receivables = $this->accounts->findByCode($payment->organizationId, $this->receivablesAccountCode);

        if ($bank === null || $receivables === null) {
            throw new RuntimeException(sprintf(
                'Automatic payment posting requires active ledger accounts %s (bank) and %s (trade receivables).',
                $this->bankAccountCode,
                $this->receivablesAccountCode,
            ));
        }

        return [$bank, $receivables];
    }
}
