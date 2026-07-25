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

    /**
     * @return array<int, PriceList>
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_catalog_price_lists WHERE organization_id = :organization_id');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map(fn (array $row): PriceList => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
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
