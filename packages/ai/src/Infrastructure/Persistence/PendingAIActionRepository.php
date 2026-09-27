<?php

declare(strict_types=1);

namespace Kontor\AI\Infrastructure\Persistence;

use Kontor\AI\Domain\PendingAIAction;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class PendingAIActionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?PendingAIAction
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_ai_pending_actions WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): PendingAIAction
    {
        return $this->find($id) ?? throw new RuntimeException("Pending AI action \"{$id}\" was not found.");
    }

    public function save(PendingAIAction $action): void
    {
        $organizationId = $this->organizations->internalIdOf($action->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_ai_pending_actions
                (uid, organization_id, capability, input_json, output_json, status, requested_by, decided_by,
                 created_at, decided_at)
             VALUES
                (:uid, :organization_id, :capability, :input_json, :output_json, :status, :requested_by, :decided_by,
                 :created_at, :decided_at)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), decided_by = VALUES(decided_by), decided_at = VALUES(decided_at)'
        );

        $statement->execute([
            'uid' => $action->uid->toString(),
            'organization_id' => $organizationId,
            'capability' => $action->capability,
            'input_json' => json_encode($action->input, JSON_THROW_ON_ERROR),
            'output_json' => json_encode($action->output, JSON_THROW_ON_ERROR),
            'status' => $action->status,
            'requested_by' => $action->requestedBy,
            'decided_by' => $action->decidedBy,
            'created_at' => $action->createdAt->format('Y-m-d H:i:s.u'),
            'decided_at' => $action->decidedAt?->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return PendingAIAction[] still-pending actions requested before $cutoff
     */
    public function pendingOlderThan(\DateTimeImmutable $cutoff): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_ai_pending_actions WHERE status = 'pending' AND created_at < :cutoff"
        );
        $statement->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s.u')]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return PendingAIAction[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_ai_pending_actions WHERE organization_id = :organization_id ORDER BY created_at DESC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): PendingAIAction
    {
        return new PendingAIAction(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            capability: $row['capability'],
            input: json_decode($row['input_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            output: json_decode($row['output_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            status: $row['status'],
            requestedBy: $row['requested_by'] !== null ? (int) $row['requested_by'] : null,
            decidedBy: $row['decided_by'] !== null ? (int) $row['decided_by'] : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            decidedAt: $row['decided_at'] !== null ? new \DateTimeImmutable($row['decided_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
