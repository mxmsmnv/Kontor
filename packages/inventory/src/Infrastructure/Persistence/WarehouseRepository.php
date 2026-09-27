<?php

declare(strict_types=1);

namespace Kontor\Inventory\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#16.1. archive()/restore() operate on `status`
 * ('active'/'inactive') rather than an archived_at column — the table has
 * none (see the migration's doc comment).
 */
final class WarehouseRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Warehouse
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_inventory_warehouses WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Warehouse
    {
        return $this->find($id) ?? throw new RuntimeException("Warehouse \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Warehouse) {
            throw new InvalidArgumentException('WarehouseRepository::save() expects a Warehouse.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_inventory_warehouses
                (uid, organization_id, code, name, address_uid, manager_user_id, status, metadata_json)
             VALUES
                (:uid, :organization_id, :code, :name, :address_uid, :manager_user_id, :status, :metadata_json)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), address_uid = VALUES(address_uid), manager_user_id = VALUES(manager_user_id),
                status = VALUES(status), metadata_json = VALUES(metadata_json)'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'code' => $entity->code,
            'name' => $entity->name,
            'address_uid' => $entity->addressUid,
            'manager_user_id' => $entity->managerUserId,
            'status' => $entity->status,
            'metadata_json' => $entity->metadata !== [] ? json_encode($entity->metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare("UPDATE kontor_inventory_warehouses SET status = 'inactive' WHERE uid = :uid");
        $statement->execute(['uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare("UPDATE kontor_inventory_warehouses SET status = 'active' WHERE uid = :uid");
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Warehouse[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_inventory_warehouses WHERE organization_id = :organization_id ORDER BY code ASC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Warehouse
    {
        return new Warehouse(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            code: $row['code'],
            name: $row['name'],
            addressUid: $row['address_uid'],
            managerUserId: $row['manager_user_id'] !== null ? (int) $row['manager_user_id'] : null,
            status: $row['status'],
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
