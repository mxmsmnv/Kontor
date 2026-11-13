<?php

declare(strict_types=1);

namespace Kontor\Reminders\Application;

use DateTimeImmutable;
use Kontor\Tasks\Application\TaskAccessScope;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskReminderRepository;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

/** Read model for scheduled notifications and contact reminder tasks. */
final class ReminderInbox
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly TaskReminderRepository $reminders,
        private readonly TaskRelationService $relations,
    ) {
    }

    /**
     * @return list<array{taskUid: string, reminderUid: ?string, title: string, kind: string,
     *     remindAt: DateTimeImmutable, channel: string, sentAt: ?DateTimeImmutable, contactUid: ?string, active: bool, canScheduleEmail: bool}>
     */
    public function list(string $organizationUid, TaskAccessScope $access, bool $canViewContacts = false, int $limit = 100): array
    {
        $rows = [];
        $afterUid = '';
        $limit = max(1, min(250, $limit));
        do {
            $tasks = $this->tasks->reminderCandidates($organizationUid, $access, $afterUid, self::BATCH_SIZE);
            if ($tasks === []) {
                break;
            }
            $rows = array_merge($rows, $this->collectBatch($organizationUid, $tasks, $canViewContacts));
            usort($rows, static function (array $left, array $right): int {
                $leftPending = $left['active'] && $left['sentAt'] === null;
                $rightPending = $right['active'] && $right['sentAt'] === null;

                return ($rightPending <=> $leftPending)
                    ?: ($leftPending ? $left['remindAt'] <=> $right['remindAt'] : $right['remindAt'] <=> $left['remindAt'])
                    ?: ($left['taskUid'] <=> $right['taskUid'])
                    ?: ($left['reminderUid'] <=> $right['reminderUid']);
            });
            $rows = array_slice($rows, 0, $limit);
            $afterUid = $tasks[array_key_last($tasks)]->uid->toString();
        } while (count($tasks) === self::BATCH_SIZE);

        return $rows;
    }

    /** @param list<Task> $tasks */
    private function collectBatch(string $organizationUid, array $tasks, bool $canViewContacts): array
    {
        $tasksByUid = [];
        foreach ($tasks as $task) {
            $tasksByUid[$task->uid->toString()] = $task;
        }
        $taskUids = array_keys($tasksByUid);
        $contactSources = [];
        foreach ($this->relations->relatedEntitiesForTasks($organizationUid, $taskUids, 'contact') as $relation) {
            $taskUid = $relation['sourceUid'];
            $task = $tasksByUid[$taskUid];
            $kind = match ($relation['relationType']) {
                'contact_birthday' => 'birthday',
                'contact_followup' => 'followup',
                default => match (true) {
                    str_starts_with($task->title, 'Birthday: ') => 'birthday',
                    str_starts_with($task->title, 'Follow up with ') => 'followup',
                    default => 'task',
                },
            };
            if ($kind !== 'task' && !isset($contactSources[$taskUid])) {
                $contactSources[$taskUid] = ['kind' => $kind, 'uid' => $relation['targetUid']];
            }
        }

        $rows = [];
        foreach ($this->reminders->forTasks($organizationUid, $taskUids) as $reminder) {
            $task = $tasksByUid[$reminder->taskUid];
            $rows[] = $this->row($task, $contactSources[$reminder->taskUid] ?? null, $canViewContacts, $reminder->remindAt,
                $reminder->channel, $reminder->uid->toString(), $reminder->sentAt);
        }
        foreach ($contactSources as $taskUid => $source) {
            $task = $tasksByUid[$taskUid];
            if ($task->isOpen() && $task->dueAt !== null) {
                $rows[] = $this->row($task, $source, $canViewContacts, $task->dueAt, 'app', null, null);
            }
        }

        return $rows;
    }

    private function row(
        Task $task,
        ?array $contact,
        bool $canViewContacts,
        DateTimeImmutable $remindAt,
        string $channel,
        ?string $reminderUid,
        ?DateTimeImmutable $sentAt,
    ): array {
        return [
            'taskUid' => $task->uid->toString(),
            'reminderUid' => $reminderUid,
            'title' => $contact !== null && !$canViewContacts ? 'Contact reminder' : $task->title,
            'kind' => $contact['kind'] ?? 'task',
            'remindAt' => $remindAt,
            'channel' => $channel,
            'sentAt' => $sentAt,
            'contactUid' => $canViewContacts ? ($contact['uid'] ?? null) : null,
            'active' => $task->isOpen(),
            'canScheduleEmail' => $task->isOpen() && $task->assignedTo !== null,
        ];
    }
}
