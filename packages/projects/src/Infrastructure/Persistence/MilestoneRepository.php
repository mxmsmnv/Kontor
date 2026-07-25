<?php

declare(strict_types=1);

namespace Kontor\Projects\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class MilestoneRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(ProjectMilestone $milestone): void
    {
        $organizationId = $this->organizations->internalIdOf($milestone->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_project_milestones
                (uid, organization_id, project_uid, name, due_date, status, completed_at, sort_order, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :project_uid, :name, :due_date, :status, :completed_at, :sort_order, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), due_date = VALUES(due_date), status = VALUES(status),
                completed_at = VALUES(completed_at), sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $milestone->uid->toString(),
            'organization_id' => $organizationId,
            'project_uid' => $milestone->projectUid,
            'name' => $milestone->name,
            'due_date' => $milestone->dueDate?->format('Y-m-d'),
            'status' => $milestone->status,
            'completed_at' => $milestone->completedAt?->format('Y-m-d H:i:s.u'),
            'sort_order' => $milestone->sortOrder,
            'created_at' => $milestone->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $milestone->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?ProjectMilestone
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_milestones WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): ProjectMilestone
    {
        return $this->find($uid) ?? throw new RuntimeException("Project milestone \"{$uid}\" was not found.");
    }

    /**
     * @return ProjectMilestone[]
     */
    public function forProject(string $projectUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_milestones WHERE project_uid = :project_uid ORDER BY sort_order ASC');
        $statement->execute(['project_uid' => $projectUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ProjectMilestone
    {
        return new ProjectMilestone(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            projectUid: $row['project_uid'],
            name: $row['name'],
            dueDate: $row['due_date'] !== null ? new \DateTimeImmutable($row['due_date']) : null,
            status: $row['status'],
            completedAt: $row['completed_at'] !== null ? new \DateTimeImmutable($row['completed_at']) : null,
            sortOrder: (int) $row['sort_order'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
