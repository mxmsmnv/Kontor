<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Persistence;

use Kontor\Contacts\Domain\Address;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#12.3
 */
final class AddressRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(Address $address): void
    {
        $organizationId = $this->organizations->internalIdOf($address->organizationId);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_addresses
                (uid, organization_id, owner_type, owner_uid, address_type, recipient_name, company_name,
                 line1, line2, city, region, postal_code, country_code, is_primary, metadata_json,
                 created_at, updated_at)
             VALUES
                (:uid, :organization_id, :owner_type, :owner_uid, :address_type, :recipient_name, :company_name,
                 :line1, :line2, :city, :region, :postal_code, :country_code, :is_primary, :metadata_json,
                 :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                address_type = VALUES(address_type), recipient_name = VALUES(recipient_name),
                company_name = VALUES(company_name), line1 = VALUES(line1), line2 = VALUES(line2),
                city = VALUES(city), region = VALUES(region), postal_code = VALUES(postal_code),
                country_code = VALUES(country_code), is_primary = VALUES(is_primary),
                metadata_json = VALUES(metadata_json), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $address->uid->toString(),
            'organization_id' => $organizationId,
            'owner_type' => $address->ownerType,
            'owner_uid' => $address->ownerUid,
            'address_type' => $address->addressType,
            'recipient_name' => $address->recipientName,
            'company_name' => $address->companyName,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
            'is_primary' => $address->isPrimary ? 1 : 0,
            'metadata_json' => $address->metadata !== [] ? json_encode($address->metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function find(string $uid): ?Address
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_addresses WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, Address>
     */
    public function forOwner(string $ownerType, string $ownerUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_addresses WHERE owner_type = :owner_type AND owner_uid = :owner_uid ORDER BY is_primary DESC, created_at ASC'
        );
        $statement->execute(['owner_type' => $ownerType, 'owner_uid' => $ownerUid]);

        return array_map(fn (array $row): Address => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function delete(string $uid): void
    {
        $statement = $this->pdo->prepare('DELETE FROM kontor_addresses WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);
    }

    private function hydrate(array $row): Address
    {
        return new Address(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            ownerType: $row['owner_type'],
            ownerUid: $row['owner_uid'],
            addressType: $row['address_type'],
            recipientName: $row['recipient_name'],
            companyName: $row['company_name'],
            line1: $row['line1'],
            line2: $row['line2'],
            city: $row['city'],
            region: $row['region'],
            postalCode: $row['postal_code'],
            countryCode: $row['country_code'],
            isPrimary: (bool) $row['is_primary'],
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
