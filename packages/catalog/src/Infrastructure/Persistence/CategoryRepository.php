<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

use Kontor\Catalog\Domain\Category;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class CategoryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(Category $category): void
    {
        $organizationId = $this->organizations->internalIdOf($category->organizationId);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_catalog_categories
                (uid, organization_id, parent_uid, name_json, sort_order, status, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :parent_uid, :name_json, :sort_order, :status, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                parent_uid = VALUES(parent_uid), name_json = VALUES(name_json),
                sort_order = VALUES(sort_order), status = VALUES(status), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $category->uid->toString(),
            'organization_id' => $organizationId,
            'parent_uid' => $category->parentUid,
            'name_json' => json_encode($category->name, JSON_THROW_ON_ERROR),
            'sort_order' => $category->sortOrder,
            'status' => $category->status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function find(string $uid): ?Category
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_catalog_categories WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, Category>
     */
    public function children(string $parentUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_catalog_categories WHERE parent_uid = :parent_uid ORDER BY sort_order ASC'
        );
        $statement->execute(['parent_uid' => $parentUid]);

        return array_map(fn (array $row): Category => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return array<int, Category> top-level categories for an organization
     */
    public function roots(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_catalog_categories WHERE organization_id = :organization_id AND parent_uid IS NULL ORDER BY sort_order ASC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map(fn (array $row): Category => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function archive(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_catalog_categories SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $uid]);
    }

    private function hydrate(array $row): Category
    {
        return new Category(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            parentUid: $row['parent_uid'],
            name: json_decode($row['name_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            sortOrder: (int) $row['sort_order'],
            status: $row['status'],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
