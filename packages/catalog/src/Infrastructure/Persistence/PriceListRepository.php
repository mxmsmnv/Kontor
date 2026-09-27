<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use InvalidArgumentException;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#14.2
 */
final class PriceListRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(PriceList $priceList): void
    {
        $organizationId = $this->organizations->internalIdOf($priceList->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_catalog_price_lists
                (uid, organization_id, name, currency_code, status, valid_from, valid_to)
             VALUES
                (:uid, :organization_id, :name, :currency_code, :status, :valid_from, :valid_to)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), currency_code = VALUES(currency_code), status = VALUES(status),
                valid_from = VALUES(valid_from), valid_to = VALUES(valid_to)'
        );

        $statement->execute([
            'uid' => $priceList->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $priceList->name,
            'currency_code' => $priceList->currencyCode,
            'status' => $priceList->status,
            'valid_from' => $priceList->validFrom?->format('Y-m-d'),
            'valid_to' => $priceList->validTo?->format('Y-m-d'),
        ]);
    }

    public function find(string $uid): ?PriceList
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_catalog_price_lists WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): PriceList
    {
        return $this->find($uid) ?? throw new \RuntimeException("Price list {$uid} was not found.");
    }

    /**
     * @return array<int, PriceList>
     */
    public function forOrganization(string $organizationUid): array
    {
        return $this->findAll($organizationUid);
    }

    /**
     * @return array<int, PriceList>
     */
    public function findAll(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        int $limit = 100,
        int $offset = 0,
        ?string $validity = null,
        ?string $currencyCode = null,
    ): array {
        [$sql, $parameters] = $this->listQuery($organizationUid, $query, $status, $validity, $currencyCode);
        $statement = $this->pdo->prepare(
            $sql . ' ORDER BY name ASC, id ASC LIMIT :limit OFFSET :offset'
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): PriceList => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        ?string $validity = null,
        ?string $currencyCode = null,
    ): int
    {
        [$sql, $parameters] = $this->listQuery(
            $organizationUid,
            $query,
            $status,
            $validity,
            $currencyCode,
            true,
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * Activate up to 100 price lists belonging to the requested organization.
     *
     * @param string[] $ids
     * @return string[] Uids whose state changed
     */
    public function activateMany(string $organizationUid, array $ids): array
    {
        return $this->setStatusMany($organizationUid, $ids, 'active');
    }

    /**
     * Deactivate up to 100 price lists belonging to the requested organization.
     *
     * @param string[] $ids
     * @return string[] Uids whose state changed
     */
    public function deactivateMany(string $organizationUid, array $ids): array
    {
        return $this->setStatusMany($organizationUid, $ids, 'inactive');
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function listQuery(
        string $organizationUid,
        string $query,
        ?string $status,
        ?string $validity,
        ?string $currencyCode,
        bool $count = false,
    ): array {
        $parameters = [
            'organization_id' => $this->organizations->internalIdOf($organizationUid),
        ];
        $conditions = ['organization_id = :organization_id'];

        if ($query !== '') {
            $conditions[] = 'name LIKE :query';
            $parameters['query'] = '%' . $query . '%';
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }

        if ($validity === 'current') {
            $conditions[] = '(valid_from IS NULL OR valid_from <= CURRENT_DATE)';
            $conditions[] = '(valid_to IS NULL OR valid_to >= CURRENT_DATE)';
        } elseif ($validity === 'upcoming') {
            $conditions[] = 'valid_from > CURRENT_DATE';
        } elseif ($validity === 'expired') {
            $conditions[] = 'valid_to < CURRENT_DATE';
        }

        if ($currencyCode !== null) {
            $conditions[] = 'currency_code = :currency_code';
            $parameters['currency_code'] = $currencyCode;
        }

        return [
            sprintf(
                'SELECT %s FROM kontor_catalog_price_lists WHERE %s',
                $count ? 'COUNT(*)' : '*',
                implode(' AND ', $conditions),
            ),
            $parameters,
        ];
    }

    /**
     * @param string[] $ids
     * @return string[]
     */
    private function setStatusMany(string $organizationUid, array $ids, string $status): array
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id)
                && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1
        )));

        if ($ids === []) {
            return [];
        }

        if (count($ids) > 100) {
            throw new InvalidArgumentException('At most 100 price lists can be changed at once.');
        }

        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $select = $this->pdo->prepare(
                "SELECT uid
                 FROM kontor_catalog_price_lists
                 WHERE organization_id = ?
                   AND uid IN ({$placeholders})
                   AND status <> ?"
                . DatabaseConcurrency::forUpdate($this->pdo)
            );
            $select->execute([$organizationId, ...$ids, $status]);
            $changedIds = array_map('strval', $select->fetchAll(\PDO::FETCH_COLUMN));

            if ($changedIds !== []) {
                $changedPlaceholders = implode(', ', array_fill(0, count($changedIds), '?'));
                $update = $this->pdo->prepare(
                    "UPDATE kontor_catalog_price_lists
                     SET status = ?
                     WHERE organization_id = ?
                       AND uid IN ({$changedPlaceholders})"
                );
                $update->execute([$status, $organizationId, ...$changedIds]);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $changedIds;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function hydrate(array $row): PriceList
    {
        return new PriceList(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            currencyCode: $row['currency_code'],
            status: $row['status'],
            validFrom: $row['valid_from'] !== null ? new \DateTimeImmutable($row['valid_from']) : null,
            validTo: $row['valid_to'] !== null ? new \DateTimeImmutable($row['valid_to']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
