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
    public function findRecent(int $organizationId, string $query = '', int $limit = 100): array
    {
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

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);

        if ($query !== '') {
            $statement->bindValue(':query', '%' . $query . '%');
        }

        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): AuditEvent => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
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
