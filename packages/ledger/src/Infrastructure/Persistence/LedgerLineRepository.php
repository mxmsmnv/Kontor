<?php

declare(strict_types=1);

namespace Kontor\Ledger\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Ledger\Domain\LedgerLine;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

final class LedgerLineRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(LedgerLine $line): void
    {
        $organizationId = $this->organizations->internalIdOf($line->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_ledger_lines
                (uid, organization_id, entry_uid, account_uid, debit_minor, credit_minor, currency_code, created_at)
             VALUES
                (:uid, :organization_id, :entry_uid, :account_uid, :debit_minor, :credit_minor, :currency_code, :created_at)'
        );

        $statement->execute([
            'uid' => $line->uid->toString(),
            'organization_id' => $organizationId,
            'entry_uid' => $line->entryUid,
            'account_uid' => $line->accountUid,
            'debit_minor' => $line->debit->amountMinor(),
            'credit_minor' => $line->credit->amountMinor(),
            'currency_code' => $line->debit->currencyCode(),
            'created_at' => $line->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return LedgerLine[]
     */
    public function forEntry(string $entryUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_ledger_lines WHERE entry_uid = :entry_uid ORDER BY id ASC');
        $statement->execute(['entry_uid' => $entryUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return LedgerLine[]
     */
    public function forAccount(string $accountUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_ledger_lines WHERE account_uid = :account_uid ORDER BY created_at ASC');
        $statement->execute(['account_uid' => $accountUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): LedgerLine
    {
        return new LedgerLine(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            entryUid: $row['entry_uid'],
            accountUid: $row['account_uid'],
            debit: Money::ofMinor((int) $row['debit_minor'], $row['currency_code']),
            credit: Money::ofMinor((int) $row['credit_minor'], $row['currency_code']),
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
