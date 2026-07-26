<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Persistence;

use Kontor\Core\Domain\AuditEvent;

final class AuditEventRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return AuditEvent[]
     */
    public function findRecent(
        int $organizationId,
        string $query = '',
        int $limit = 100,
        ?string $component = null,
        ?string $entityType = null,
        ?string $action = null,
        int $offset = 0,
    ): array {
        $limit = max(1, min($limit, 250));
        $offset = max(0, $offset);
        [$where, $bindings] = $this->matchingWhere(
            $organizationId,
            $query,
            $component,
            $entityType,
            $action,
        );
        $sql = 'SELECT * FROM kontor_audit_events WHERE ' . $where
            . ' ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        $this->bindMatching($statement, $bindings);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): AuditEvent => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return \Generator<int, AuditEvent>
     */
    public function iterateMatching(
        int $organizationId,
        string $query = '',
        ?string $component = null,
        ?string $entityType = null,
        ?string $action = null,
    ): \Generator {
        [$where, $bindings] = $this->matchingWhere(
            $organizationId,
            $query,
            $component,
            $entityType,
            $action,
        );
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_audit_events WHERE ' . $where
            . ' ORDER BY occurred_at DESC, id DESC'
        );
        $this->bindMatching($statement, $bindings);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            yield $this->hydrate($row);
        }
    }

    public function countMatching(
        int $organizationId,
        string $query = '',
        ?string $component = null,
        ?string $entityType = null,
        ?string $action = null,
    ): int {
        [$where, $bindings] = $this->matchingWhere(
            $organizationId,
            $query,
            $component,
            $entityType,
            $action,
        );
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM kontor_audit_events WHERE ' . $where
        );
        $this->bindMatching($statement, $bindings);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function matchingWhere(
        int $organizationId,
        string $query,
        ?string $component,
        ?string $entityType,
        ?string $action,
    ): array {
        $query = trim($query);
        $where = 'organization_id = :organization_id';
        $bindings = [':organization_id' => $organizationId];

        if ($query !== '') {
            $where .= ' AND (
                component LIKE :query
                OR entity_type LIKE :query
                OR entity_uid LIKE :query
                OR action LIKE :query
                OR actor_uid LIKE :query
            )';
            $bindings[':query'] = '%' . $query . '%';
        }

        foreach (
            [
                'component' => $component,
                'entity_type' => $entityType,
                'action' => $action,
            ] as $column => $value
        ) {
            if ($value !== null) {
                $where .= " AND {$column} = :{$column}";
                $bindings[":{$column}"] = $value;
            }
        }

        return [$where, $bindings];
    }

    /**
     * @param array<string, int|string> $bindings
     */
    private function bindMatching(\PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $name => $value) {
            $statement->bindValue(
                $name,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
    }

    /**
     * @return array{components: string[], entityTypes: string[], actions: string[]}
     */
    public function filterOptions(int $organizationId): array
    {
        return [
            'components' => $this->distinctValues($organizationId, 'component'),
            'entityTypes' => $this->distinctValues($organizationId, 'entity_type'),
            'actions' => $this->distinctValues($organizationId, 'action'),
        ];
    }

    /**
     * @return string[]
     */
    private function distinctValues(int $organizationId, string $column): array
    {
        if (!in_array($column, ['component', 'entity_type', 'action'], true)) {
            throw new \InvalidArgumentException('Unsupported audit facet.');
        }

        $statement = $this->pdo->prepare(
            "SELECT DISTINCT {$column}
             FROM kontor_audit_events
             WHERE organization_id = :organization_id
             ORDER BY {$column}"
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function hydrate(array $row): AuditEvent
    {
        return new AuditEvent(
            uid: $row['uid'],
            component: $row['component'],
            entityType: $row['entity_type'],
            entityUid: $row['entity_uid'],
            action: $row['action'],
            actorType: $row['actor_type'],
            actorUid: $row['actor_uid'],
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
            previous: $this->decode($row['previous_json']),
            current: $this->decode($row['current_json']),
            metadata: $this->decode($row['metadata_json']) ?? [],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(?string $json): ?array
    {
        return $json === null
            ? null
            : json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
