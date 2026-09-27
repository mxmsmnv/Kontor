<?php

declare(strict_types=1);

namespace Kontor\Tasks\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\TaskReminder;
use RuntimeException;

final class TaskReminderRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(TaskReminder $reminder): void
    {
        $organizationId = $this->organizations->internalIdOf($reminder->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_task_reminders
                (uid, organization_id, task_uid, remind_at, channel, sent_at, created_at)
             VALUES
                (:uid, :organization_id, :task_uid, :remind_at, :channel, :sent_at, :created_at)
             ON DUPLICATE KEY UPDATE sent_at = VALUES(sent_at)'
        );

        $statement->execute([
            'uid' => $reminder->uid->toString(),
            'organization_id' => $organizationId,
            'task_uid' => $reminder->taskUid,
            'remind_at' => $reminder->remindAt->format('Y-m-d H:i:s.u'),
            'channel' => $reminder->channel,
            'sent_at' => $reminder->sentAt?->format('Y-m-d H:i:s.u'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?TaskReminder
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_task_reminders WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): TaskReminder
    {
        return $this->find($uid) ?? throw new RuntimeException("Task reminder \"{$uid}\" was not found.");
    }

    /**
     * @return TaskReminder[]
     */
    public function forTask(string $taskUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_task_reminders WHERE task_uid = :task_uid ORDER BY remind_at ASC');
        $statement->execute(['task_uid' => $taskUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Unsent reminders whose remind_at has arrived — the query a future
     * kontor/queue-backed dispatcher would poll. No dispatcher is built in
     * this package; see the README.
     *
     * @return TaskReminder[]
     */
    public function due(string $organizationUid, ?\DateTimeImmutable $asOf = null): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $asOf ??= new \DateTimeImmutable();

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_task_reminders
             WHERE organization_id = :organization_id AND remind_at <= :as_of AND sent_at IS NULL
             ORDER BY remind_at ASC'
        );
        $statement->execute(['organization_id' => $organizationId, 'as_of' => $asOf->format('Y-m-d H:i:s.u')]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): TaskReminder
    {
        return new TaskReminder(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            taskUid: $row['task_uid'],
            remindAt: new \DateTimeImmutable($row['remind_at']),
            channel: $row['channel'],
            sentAt: $row['sent_at'] !== null ? new \DateTimeImmutable($row['sent_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
