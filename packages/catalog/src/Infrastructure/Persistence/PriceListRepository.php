<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

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
    ): array {
        [$sql, $parameters] = $this->listQuery($organizationUid, $query, $status);
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

    public function countMatching(string $organizationUid, string $query = '', ?string $status = null): int
    {
        [$sql, $parameters] = $this->listQuery($organizationUid, $query, $status, true);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function listQuery(
        string $organizationUid,
        string $query,
        ?string $status,
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

        return [
            sprintf(
                'SELECT %s FROM kontor_catalog_price_lists WHERE %s',
                $count ? 'COUNT(*)' : '*',
                implode(' AND ', $conditions),
            ),
            $parameters,
        ];
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
