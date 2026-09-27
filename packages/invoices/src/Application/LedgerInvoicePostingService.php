<?php

declare(strict_types=1);

namespace Kontor\Invoices\Application;

use Kontor\Invoices\Domain\Invoice;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\SDK\ValueObjects\Money;
use RuntimeException;

/**
 * Posts invoice lifecycle events into the append-only general ledger.
 *
 * The default account roles match the illustrative German chart:
 * 1400 trade receivables, 1700 sales tax, and 8000 revenue.
 */
final class LedgerInvoicePostingService implements InvoicePostingInterface
{
    public const ISSUE_REFERENCE = 'invoice_issue';
    public const CANCELLATION_REFERENCE = 'invoice_cancellation';

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerEntryRepository $entries,
        private readonly LedgerEntryService $ledger,
        private readonly string $receivablesAccountCode = '1400',
        private readonly string $taxAccountCode = '1700',
        private readonly string $revenueAccountCode = '8000',
    ) {
    }

    public function assertCanPost(Invoice $invoice): void
    {
        if (!in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid', 'paid', 'credited'], true)) {
            throw new RuntimeException('Only an issued invoice or credit note can be posted.');
        }
        if ($invoice->total->amountMinor() === 0) {
            throw new RuntimeException('A zero-value invoice cannot be posted.');
        }
        if ($invoice->subtotal->amountMinor() + $invoice->tax->amountMinor() !== $invoice->total->amountMinor()) {
            throw new RuntimeException('Invoice subtotal and tax do not match its total.');
        }

        [$receivables, $tax, $revenue] = $this->postingAccounts($invoice);
        if (!$receivables->isActive() || $receivables->type !== 'asset') {
            throw new RuntimeException("Invoice posting requires active asset account {$receivables->code}.");
        }
        if (!$revenue->isActive() || $revenue->type !== 'revenue') {
            throw new RuntimeException("Invoice posting requires active revenue account {$revenue->code}.");
        }
        if ($tax !== null && (!$tax->isActive() || $tax->type !== 'liability')) {
            throw new RuntimeException("Invoice posting requires active liability account {$tax->code}.");
        }
        foreach (array_filter([$receivables, $tax, $revenue]) as $account) {
            if ($account->currencyCode !== $invoice->currencyCode) {
                throw new RuntimeException(sprintf(
                    'Invoice posting account %s uses %s, not %s.',
                    $account->code,
                    $account->currencyCode,
                    $invoice->currencyCode,
                ));
            }
        }
    }

    public function postIssue(Invoice $invoice, ?int $createdBy = null): void
    {
        if ($this->entries->findByReference(self::ISSUE_REFERENCE, $invoice->uid->toString()) !== null) {
            return;
        }

        $this->assertCanPost($invoice);
        $this->record($invoice, self::ISSUE_REFERENCE, false, $createdBy);
    }

    public function postCancellation(Invoice $invoice, ?int $createdBy = null): void
    {
        if ($this->entries->findByReference(self::CANCELLATION_REFERENCE, $invoice->uid->toString()) !== null) {
            return;
        }

        if ($this->entries->findByReference(self::ISSUE_REFERENCE, $invoice->uid->toString()) === null) {
            $this->postIssue($invoice, $createdBy);
        } else {
            $this->assertCanPost($invoice);
        }
        $this->record($invoice, self::CANCELLATION_REFERENCE, true, $createdBy);
    }

    private function record(
        Invoice $invoice,
        string $referenceType,
        bool $reverse,
        ?int $createdBy,
    ): void {
        [$receivables, $tax, $revenue] = $this->postingAccounts($invoice);
        $direction = $reverse ? -1 : 1;
        $balances = [
            [$receivables->uid->toString(), $invoice->total->amountMinor() * $direction],
        ];
        if ($invoice->tax->amountMinor() !== 0 && $tax !== null) {
            $balances[] = [$tax->uid->toString(), -$invoice->tax->amountMinor() * $direction];
        }
        $balances[] = [$revenue->uid->toString(), -$invoice->subtotal->amountMinor() * $direction];
        $balances = array_values(array_filter(
            $balances,
            static fn (array $balance): bool => $balance[1] !== 0,
        ));
        $lines = array_map(
            static function (array $balance) use ($invoice): LedgerLineInput {
                [$accountUid, $debitBalance] = $balance;
                $amount = Money::ofMinor(abs($debitBalance), $invoice->currencyCode);

                return $debitBalance > 0
                    ? LedgerLineInput::debit($accountUid, $amount)
                    : LedgerLineInput::credit($accountUid, $amount);
            },
            $balances,
        );

        $label = $invoice->kind === 'credit_note' ? 'Credit note' : 'Invoice';
        $this->ledger->record(
            $invoice->organizationId,
            sprintf(
                '%s%s %s',
                $reverse ? 'Cancellation of ' : '',
                strtolower($label),
                $invoice->number ?? $invoice->uid->toString(),
            ),
            $invoice->issueDate ?? new \DateTimeImmutable('today'),
            $lines,
            $referenceType,
            $invoice->uid->toString(),
            $createdBy,
        );
    }

    /**
     * @return array{0: \Kontor\Ledger\Domain\Account, 1: \Kontor\Ledger\Domain\Account|null, 2: \Kontor\Ledger\Domain\Account}
     */
    private function postingAccounts(Invoice $invoice): array
    {
        $receivables = $this->accounts->findByCode($invoice->organizationId, $this->receivablesAccountCode);
        $revenue = $this->accounts->findByCode($invoice->organizationId, $this->revenueAccountCode);
        $tax = $invoice->tax->amountMinor() === 0
            ? null
            : $this->accounts->findByCode($invoice->organizationId, $this->taxAccountCode);

        if ($receivables === null || $revenue === null || ($invoice->tax->amountMinor() !== 0 && $tax === null)) {
            throw new RuntimeException(sprintf(
                'Invoice posting requires ledger accounts %s (receivables), %s (tax), and %s (revenue).',
                $this->receivablesAccountCode,
                $this->taxAccountCode,
                $this->revenueAccountCode,
            ));
        }

        return [$receivables, $tax, $revenue];
    }
}
