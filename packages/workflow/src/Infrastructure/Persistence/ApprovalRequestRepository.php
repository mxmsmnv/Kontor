<?php

declare(strict_types=1);

namespace Kontor\Workflow\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Workflow\Domain\ApprovalRequest;
use RuntimeException;

final class ApprovalRequestRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(ApprovalRequest $request): void
    {
        $organizationId = $this->organizations->internalIdOf($request->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_workflow_approval_requests
                (uid, organization_id, instance_uid, action_key, from_state, to_state, requested_by, status,
                 decided_by, decided_at, rejection_reason, created_at)
             VALUES
                (:uid, :organization_id, :instance_uid, :action_key, :from_state, :to_state, :requested_by, :status,
                 :decided_by, :decided_at, :rejection_reason, :created_at)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), decided_by = VALUES(decided_by), decided_at = VALUES(decided_at),
                rejection_reason = VALUES(rejection_reason)'
        );

        $statement->execute([
            'uid' => $request->uid->toString(),
            'organization_id' => $organizationId,
            'instance_uid' => $request->instanceUid,
            'action_key' => $request->actionKey,
            'from_state' => $request->fromState,
            'to_state' => $request->toState,
            'requested_by' => $request->requestedBy,
            'status' => $request->status,
            'decided_by' => $request->decidedBy,
            'decided_at' => $request->decidedAt?->format('Y-m-d H:i:s.u'),
            'rejection_reason' => $request->rejectionReason,
            'created_at' => $request->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?ApprovalRequest
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_approval_requests WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): ApprovalRequest
    {
        return $this->find($uid) ?? throw new RuntimeException("Approval request \"{$uid}\" was not found.");
    }

    /**
     * @return ApprovalRequest[]
     */
    public function pendingFor(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare("SELECT * FROM kontor_workflow_approval_requests WHERE organization_id = :organization_id AND status = 'pending'");
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ApprovalRequest
    {
        return new ApprovalRequest(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            instanceUid: $row['instance_uid'],
            actionKey: $row['action_key'],
            fromState: $row['from_state'],
            toState: $row['to_state'],
            requestedBy: $row['requested_by'] !== null ? (int) $row['requested_by'] : null,
            status: $row['status'],
            decidedBy: $row['decided_by'] !== null ? (int) $row['decided_by'] : null,
            decidedAt: $row['decided_at'] !== null ? new \DateTimeImmutable($row['decided_at']) : null,
            rejectionReason: $row['rejection_reason'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
