<?php

declare(strict_types=1);

namespace Kontor\Ledger\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Ledger\Domain\LedgerEntry;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * Not a `RepositoryInterface` implementation — entries are append-only
 * (see the migration's doc comment), so there's no `archive()`/
 * `restore()` concept, the same status
 * `Kontor\Automation\Infrastructure\Persistence\ExecutionLogRepository`
 * already has for its own append-only table.
 */
final class LedgerEntryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $uid): ?LedgerEntry
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_ledger_entries WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): LedgerEntry
    {
        return $this->find($uid) ?? throw new RuntimeException("Ledger entry \"{$uid}\" was not found.");
    }

    public function findByReference(string $referenceType, string $referenceUid): ?LedgerEntry
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_ledger_entries
             WHERE reference_type = :reference_type AND reference_uid = :reference_uid
             ORDER BY id ASC LIMIT 1'
        );
        $statement->execute([
            'reference_type' => $referenceType,
            'reference_uid' => $referenceUid,
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function insert(LedgerEntry $entry): void
    {
        $organizationId = $this->organizations->internalIdOf($entry->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_ledger_entries
                (uid, organization_id, description, entry_date, reference_type, reference_uid, created_at, created_by)
             VALUES
                (:uid, :organization_id, :description, :entry_date, :reference_type, :reference_uid, :created_at, :created_by)'
        );

        $statement->execute([
            'uid' => $entry->uid->toString(),
            'organization_id' => $organizationId,
            'description' => $entry->description,
            'entry_date' => $entry->entryDate->format('Y-m-d'),
            'reference_type' => $entry->referenceType,
            'reference_uid' => $entry->referenceUid,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entry->createdBy,
        ]);
    }

    /**
     * @return LedgerEntry[] newest first
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_ledger_entries WHERE organization_id = :organization_id ORDER BY entry_date DESC, created_at DESC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): LedgerEntry
    {
        return new LedgerEntry(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            description: $row['description'],
            entryDate: new \DateTimeImmutable($row['entry_date']),
            referenceType: $row['reference_type'],
            referenceUid: $row['reference_uid'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
