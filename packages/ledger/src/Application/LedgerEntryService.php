<?php

declare(strict_types=1);

namespace Kontor\Ledger\Application;

use Kontor\Ledger\Domain\LedgerEntry;
use Kontor\Ledger\Domain\LedgerLine;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use RuntimeException;

/**
 * The "double-entry foundations" milestone's core invariant: an entry's
 * lines are validated to actually balance — debits equal credits, per
 * currency — before anything is persisted (`LedgerBalanceValidator`).
 * This is what makes the ledger a real double-entry system rather than
 * just two tables that happen to be shaped like one.
 */
final class LedgerEntryService
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly LedgerEntryRepository $entries,
        private readonly LedgerLineRepository $lines,
    ) {
    }

    /**
     * @param LedgerLineInput[] $lineInputs
     *
     * @throws UnbalancedLedgerEntryException
     */
    public function record(
        string $organizationId,
        string $description,
        \DateTimeImmutable $entryDate,
        array $lineInputs,
        ?string $referenceType = null,
        ?string $referenceUid = null,
        ?int $createdBy = null,
    ): LedgerEntry {
        LedgerBalanceValidator::validate($lineInputs);

        foreach ($lineInputs as $input) {
            $account = $this->accounts->require($input->accountUid);

            if ($account->organizationId !== $organizationId) {
                throw new RuntimeException("Account \"{$input->accountUid}\" does not belong to this organization.");
            }
        }

        $entry = LedgerEntry::create($organizationId, $description, $entryDate, $referenceType, $referenceUid, $createdBy);
        $this->entries->insert($entry);

        foreach ($lineInputs as $input) {
            $this->lines->insert(LedgerLine::create($organizationId, $entry->uid->toString(), $input->accountUid, $input->debit, $input->credit));
        }

        return $entry;
    }
}
