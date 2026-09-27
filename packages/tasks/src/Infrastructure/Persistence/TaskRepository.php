<?php

declare(strict_types=1);

namespace Kontor\Tasks\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\Task;
use InvalidArgumentException;
use RuntimeException;

final class TaskRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Task
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_tasks WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findActive(string $organizationUid, string $id): ?Task
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_tasks
             WHERE uid = :uid AND organization_id = :organization_id AND archived_at IS NULL'
        );
        $statement->execute([
            'uid' => $id,
            'organization_id' => $this->organizations->internalIdOf($organizationUid),
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Task
    {
        return $this->find($id) ?? throw new RuntimeException("Task \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Task) {
            throw new InvalidArgumentException('TaskRepository::save() expects a Task.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_tasks
                (uid, organization_id, title, description, status, priority, assigned_to, start_at, due_at,
                 completed_at, recurrence_rule, recurrence_until, created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :title, :description, :status, :priority, :assigned_to, :start_at, :due_at,
                 :completed_at, :recurrence_rule, :recurrence_until, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title), description = VALUES(description), status = VALUES(status),
                priority = VALUES(priority), assigned_to = VALUES(assigned_to), start_at = VALUES(start_at),
                due_at = VALUES(due_at), completed_at = VALUES(completed_at), recurrence_rule = VALUES(recurrence_rule),
                recurrence_until = VALUES(recurrence_until), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'title' => $entity->title,
            'description' => $entity->description,
            'status' => $entity->status,
            'priority' => $entity->priority,
            'assigned_to' => $entity->assignedTo,
            'start_at' => $entity->startAt?->format('Y-m-d H:i:s.u'),
            'due_at' => $entity->dueAt?->format('Y-m-d H:i:s.u'),
            'completed_at' => $entity->completedAt?->format('Y-m-d H:i:s.u'),
            'recurrence_rule' => $entity->recurrenceRule,
            'recurrence_until' => $entity->recurrenceUntil?->format('Y-m-d'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_tasks SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_tasks SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, Task>
     */
    public function findMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        ?string $priority = null,
        bool $archived = false,
        int $limit = 50,
        int $offset = 0,
    ): array {
        [$where, $params] = $this->matchingConditions(
            $organizationUid,
            $query,
            $status,
            $priority,
            $archived,
        );
        $statement = $this->pdo->prepare(
            'SELECT t.* FROM kontor_tasks t
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY t.due_at IS NULL, t.due_at ASC, t.updated_at DESC, t.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Task => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, int|string>}
     */
    private function matchingConditions(
        string $organizationUid,
        string $query,
        ?string $status,
        ?string $priority,
        bool $archived,
    ): array {
        $where = [
            't.organization_id = :organization_id',
            't.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL'),
        ];
        $params = ['organization_id' => $this->organizations->internalIdOf($organizationUid)];

        if ($status !== null) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if ($priority !== null) {
            $where[] = 't.priority = :priority';
            $params['priority'] = $priority;
        }
        $query = trim($query);
        if ($query !== '') {
            $where[] = '(t.title LIKE :query OR t.description LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        return [$where, $params];
    }

    /**
     * The "calendar" milestone's actual query surface — tasks with a due
     * date inside a range, for a caller to render on a calendar. No
     * calendar UI is built in this package; this is the data it would use.
     *
     * @return Task[]
     */
    public function dueBetween(string $organizationUid, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_tasks
             WHERE organization_id = :organization_id AND due_at BETWEEN :from AND :to AND archived_at IS NULL
             ORDER BY due_at ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'from' => $from->format('Y-m-d H:i:s.u'),
            'to' => $to->format('Y-m-d H:i:s.u'),
        ]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Task[]
     */
    public function openForAssignee(string $organizationUid, int $userId, int $limit = 8): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_tasks
             WHERE organization_id = :organization_id
               AND assigned_to = :assigned_to
               AND status IN ('open', 'in_progress')
               AND archived_at IS NULL
             ORDER BY due_at IS NULL, due_at ASC, updated_at DESC, id DESC
             LIMIT :limit"
        );
        $statement->bindValue(
            ':organization_id',
            $this->organizations->internalIdOf($organizationUid),
            \PDO::PARAM_INT,
        );
        $statement->bindValue(':assigned_to', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(50, $limit)), \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Task
    {
        return new Task(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            title: $row['title'],
            description: $row['description'],
            status: $row['status'],
            priority: $row['priority'],
            assignedTo: $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            startAt: $row['start_at'] !== null ? new \DateTimeImmutable($row['start_at']) : null,
            dueAt: $row['due_at'] !== null ? new \DateTimeImmutable($row['due_at']) : null,
            completedAt: $row['completed_at'] !== null ? new \DateTimeImmutable($row['completed_at']) : null,
            recurrenceRule: $row['recurrence_rule'],
            recurrenceUntil: $row['recurrence_until'] !== null ? new \DateTimeImmutable($row['recurrence_until']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
