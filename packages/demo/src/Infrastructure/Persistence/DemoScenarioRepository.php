<?php

declare(strict_types=1);

namespace Kontor\Demo\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Demo\Domain\DemoScenario;
use Kontor\SDK\ValueObjects\Uid;

final class DemoScenarioRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $uid): ?DemoScenario
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_demo_scenarios WHERE uid = :uid AND archived_at IS NULL'
        );
        $statement->execute(['uid' => $uid]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): DemoScenario
    {
        return $this->find($uid)
            ?? throw new \RuntimeException("Demo scenario \"{$uid}\" was not found.");
    }

    /**
     * @return DemoScenario[]
     */
    public function forOrganization(string $organizationUid, int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.*
             FROM kontor_demo_scenarios s
             INNER JOIN kontor_organizations o ON o.id = s.organization_id
             WHERE o.uid = :organization_uid AND s.archived_at IS NULL
             ORDER BY s.updated_at DESC, s.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('organization_uid', $organizationUid);
        $statement->bindValue('limit', max(1, min($limit, 100)), \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(DemoScenario $scenario): void
    {
        $organizationId = $this->organizations->internalIdOf($scenario->organizationId);
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_demo_scenarios
                (uid, organization_id, name, current_state, status, workflow_instance_uid,
                 pending_approval_uid, entities_json, last_error, created_at, updated_at, created_by)
             VALUES
                (:uid, :organization_id, :name, :current_state, :status, :workflow_instance_uid,
                 :pending_approval_uid, :entities_json, :last_error, :created_at, :updated_at, :created_by)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), current_state = VALUES(current_state), status = VALUES(status),
                workflow_instance_uid = VALUES(workflow_instance_uid),
                pending_approval_uid = VALUES(pending_approval_uid),
                entities_json = VALUES(entities_json), last_error = VALUES(last_error),
                updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'uid' => $scenario->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $scenario->name,
            'current_state' => $scenario->currentState,
            'status' => $scenario->status,
            'workflow_instance_uid' => $scenario->workflowInstanceUid,
            'pending_approval_uid' => $scenario->pendingApprovalUid,
            'entities_json' => json_encode($scenario->entities, JSON_THROW_ON_ERROR),
            'last_error' => $scenario->lastError,
            'created_at' => $scenario->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $scenario->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $scenario->createdBy,
        ]);
    }

    public function archive(string $uid): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE kontor_demo_scenarios SET archived_at = :archived_at WHERE uid = :uid'
        );
        $statement->execute([
            'uid' => $uid,
            'archived_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function hydrate(array $row): DemoScenario
    {
        return new DemoScenario(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            currentState: $row['current_state'],
            status: $row['status'],
            workflowInstanceUid: $row['workflow_instance_uid'],
            pendingApprovalUid: $row['pending_approval_uid'],
            entities: json_decode($row['entities_json'], true, flags: JSON_THROW_ON_ERROR),
            lastError: $row['last_error'],
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
