<?php

declare(strict_types=1);

namespace Kontor\Entities\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Domain\EntityView;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class EntityViewRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(EntityView $view): void
    {
        $organizationId = $this->organizations->internalIdOf($view->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_entity_views
                (uid, organization_id, definition_uid, name, filters_json, sort_json, columns_json, created_at, updated_at, created_by)
             VALUES
                (:uid, :organization_id, :definition_uid, :name, :filters_json, :sort_json, :columns_json, :created_at, :updated_at, :created_by)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), filters_json = VALUES(filters_json), sort_json = VALUES(sort_json),
                columns_json = VALUES(columns_json), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $view->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $view->definitionUid,
            'name' => $view->name,
            'filters_json' => $view->filters !== [] ? json_encode($view->filters, JSON_THROW_ON_ERROR) : null,
            'sort_json' => $view->sort !== [] ? json_encode($view->sort, JSON_THROW_ON_ERROR) : null,
            'columns_json' => $view->columns !== [] ? json_encode($view->columns, JSON_THROW_ON_ERROR) : null,
            'created_at' => $view->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $view->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $view->createdBy,
        ]);
    }

    public function find(string $uid): ?EntityView
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_views WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): EntityView
    {
        return $this->find($uid) ?? throw new RuntimeException("Entity view \"{$uid}\" was not found.");
    }

    /**
     * @return EntityView[]
     */
    public function forDefinition(string $definitionUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_views WHERE definition_uid = :definition_uid ORDER BY name ASC');
        $statement->execute(['definition_uid' => $definitionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): EntityView
    {
        return new EntityView(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            name: $row['name'],
            filters: $row['filters_json'] !== null ? json_decode($row['filters_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            sort: $row['sort_json'] !== null ? json_decode($row['sort_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            columns: $row['columns_json'] !== null ? json_decode($row['columns_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
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
