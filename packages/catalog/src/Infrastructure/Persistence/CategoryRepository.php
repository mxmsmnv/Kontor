<?php

declare(strict_types=1);

namespace Kontor\Catalog\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use InvalidArgumentException;
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

    public function require(string $uid): Category
    {
        return $this->find($uid) ?? throw new \RuntimeException("Catalog category \"{$uid}\" was not found.");
    }

    /**
     * @return Category[]
     */
    public function findAll(
        string $organizationUid,
        string $query = '',
        bool $archived = false,
        int $limit = 100,
        int $offset = 0,
        ?string $status = null,
    ): array {
        [$sql, $params] = $this->listQuery($organizationUid, $query, $archived, $status);
        $sql .= ' ORDER BY sort_order ASC, id ASC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', max(1, min($limit, 250)), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Category => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    public function countMatching(
        string $organizationUid,
        string $query = '',
        bool $archived = false,
        ?string $status = null,
    ): int {
        [$sql, $params] = $this->listQuery($organizationUid, $query, $archived, $status, true);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
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

    public function restore(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_catalog_categories SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);
    }

    /**
     * Archive up to 100 categories belonging to the requested organization.
     *
     * @param string[] $ids
     * @return string[] Uids whose state changed
     */
    public function archiveMany(string $organizationUid, array $ids): array
    {
        return $this->setArchivedMany($organizationUid, $ids, true);
    }

    /**
     * Restore up to 100 categories belonging to the requested organization.
     *
     * @param string[] $ids
     * @return string[] Uids whose state changed
     */
    public function restoreMany(string $organizationUid, array $ids): array
    {
        return $this->setArchivedMany($organizationUid, $ids, false);
    }

    /**
     * @param string[] $ids
     * @return string[] Uids whose state changed
     */
    public function activateMany(string $organizationUid, array $ids): array
    {
        return $this->setStatusMany($organizationUid, $ids, 'active');
    }

    /**
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
        bool $archived,
        ?string $status,
        bool $count = false,
    ): array {
        $params = ['organization_id' => $this->organizations->internalIdOf($organizationUid)];
        $sql = 'SELECT ' . ($count ? 'COUNT(*)' : '*') . ' FROM kontor_catalog_categories
            WHERE organization_id = :organization_id
              AND archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        $query = trim($query);

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($query !== '') {
            $sql .= ' AND name_json LIKE :query';
            $params['query'] = '%' . $query . '%';
        }

        return [$sql, $params];
    }

    /**
     * @param string[] $ids
     * @return string[]
     */
    private function setArchivedMany(string $organizationUid, array $ids, bool $archived): array
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
            throw new InvalidArgumentException('At most 100 catalog categories can be changed at once.');
        }

        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $select = $this->pdo->prepare(
                "SELECT uid
                 FROM kontor_catalog_categories
                 WHERE organization_id = ?
                   AND uid IN ({$placeholders})
                   AND archived_at IS " . ($archived ? 'NULL' : 'NOT NULL')
                . DatabaseConcurrency::forUpdate($this->pdo)
            );
            $select->execute([$organizationId, ...$ids]);
            $changedIds = array_map('strval', $select->fetchAll(\PDO::FETCH_COLUMN));

            if ($changedIds !== []) {
                $changedPlaceholders = implode(', ', array_fill(0, count($changedIds), '?'));
                $update = $this->pdo->prepare(
                    'UPDATE kontor_catalog_categories
                     SET archived_at = ' . ($archived ? '?' : 'NULL') . "
                     WHERE organization_id = ?
                       AND uid IN ({$changedPlaceholders})"
                );
                $parameters = $archived
                    ? [(new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), $organizationId, ...$changedIds]
                    : [$organizationId, ...$changedIds];
                $update->execute($parameters);
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
            throw new InvalidArgumentException('At most 100 catalog categories can be changed at once.');
        }

        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($this->pdo);

        try {
            $select = $this->pdo->prepare(
                "SELECT uid
                 FROM kontor_catalog_categories
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
                    "UPDATE kontor_catalog_categories
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
