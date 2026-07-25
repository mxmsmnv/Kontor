<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Persistence;

/**
 * Generic CRUD for kontor_extensions (kontor.md#11.8) — arbitrary keyed
 * metadata any component can attach to any entity without a schema
 * migration of its own. First real consumer: KontorContacts' tags
 * (Substage 3.1), stored as extension_key='tags' with a JSON array value.
 */
final class ExtensionRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function get(
        int $organizationId,
        string $ownerComponent,
        string $entityType,
        string $entityUid,
        string $extensionKey,
    ): mixed {
        $statement = $this->pdo->prepare(
            'SELECT value_json FROM kontor_extensions
             WHERE organization_id = :organization_id AND owner_component = :owner_component
                AND entity_type = :entity_type AND entity_uid = :entity_uid AND extension_key = :extension_key'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'owner_component' => $ownerComponent,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'extension_key' => $extensionKey,
        ]);

        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    public function set(
        int $organizationId,
        string $ownerComponent,
        string $entityType,
        string $entityUid,
        string $extensionKey,
        mixed $value,
    ): void {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_extensions
                (organization_id, owner_component, entity_type, entity_uid, extension_key, value_json, created_at, updated_at)
             VALUES
                (:organization_id, :owner_component, :entity_type, :entity_uid, :extension_key, :value_json, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'organization_id' => $organizationId,
            'owner_component' => $ownerComponent,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'extension_key' => $extensionKey,
            'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function delete(
        int $organizationId,
        string $ownerComponent,
        string $entityType,
        string $entityUid,
        string $extensionKey,
    ): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM kontor_extensions
             WHERE organization_id = :organization_id AND owner_component = :owner_component
                AND entity_type = :entity_type AND entity_uid = :entity_uid AND extension_key = :extension_key'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'owner_component' => $ownerComponent,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'extension_key' => $extensionKey,
        ]);
    }

    /**
     * @return array<string, mixed> extension_key => value
     */
    public function allFor(
        int $organizationId,
        string $ownerComponent,
        string $entityType,
        string $entityUid,
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT extension_key, value_json FROM kontor_extensions
             WHERE organization_id = :organization_id AND owner_component = :owner_component
                AND entity_type = :entity_type AND entity_uid = :entity_uid'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'owner_component' => $ownerComponent,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
        ]);

        $result = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['extension_key']] = json_decode($row['value_json'], associative: true, flags: JSON_THROW_ON_ERROR);
        }

        return $result;
    }
}
