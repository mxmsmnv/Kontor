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
    ): array {
        $query = trim($query);
        $limit = max(1, min($limit, 250));
        $sql = 'SELECT * FROM kontor_audit_events WHERE organization_id = :organization_id';

        if ($query !== '') {
            $sql .= ' AND (
                component LIKE :query
                OR entity_type LIKE :query
                OR entity_uid LIKE :query
                OR action LIKE :query
                OR actor_uid LIKE :query
            )';
        }

        if ($component !== null) {
            $sql .= ' AND component = :component';
        }

        if ($entityType !== null) {
            $sql .= ' AND entity_type = :entity_type';
        }

        if ($action !== null) {
            $sql .= ' AND action = :action';
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);

        if ($query !== '') {
            $statement->bindValue(':query', '%' . $query . '%');
        }

        if ($component !== null) {
            $statement->bindValue(':component', $component);
        }

        if ($entityType !== null) {
            $statement->bindValue(':entity_type', $entityType);
        }

        if ($action !== null) {
            $statement->bindValue(':action', $action);
        }

        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): AuditEvent => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
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
