<?php

declare(strict_types=1);

namespace Kontor\Tasks\Infrastructure\Automation;

use Kontor\Automation\Contracts\ActionHandlerInterface;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Application\TaskReminderDispatcher;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use RuntimeException;

final class CreateTaskActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ?TaskRelationService $relations = null,
        private readonly ?TaskReminderDispatcher $reminders = null,
    ) {
    }

    public function key(): string
    {
        return 'tasks.create';
    }

    public function execute(array $eventData, array $params): array
    {
        $organizationUid = trim((string) ($eventData['_event']['organizationId'] ?? ''));
        if ($organizationUid === '') {
            throw new RuntimeException('The automation event organization is required to create a task.');
        }

        $title = trim($this->interpolate((string) ($params['title'] ?? ''), $eventData));
        if ($title === '') {
            throw new \InvalidArgumentException('The tasks.create action requires a title.');
        }
        $priority = (string) ($params['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            throw new \InvalidArgumentException("\"{$priority}\" is not a supported task priority.");
        }

        $dueAt = isset($params['dueInMinutes'])
            ? (new \DateTimeImmutable())->modify('+'.max(0, (int) $params['dueInMinutes']).' minutes')
            : null;
        $task = Task::create(
            organizationId: $organizationUid,
            title: $title,
            description: isset($params['description'])
                ? $this->interpolate((string) $params['description'], $eventData)
                : null,
            priority: $priority,
            assignedTo: isset($params['assignedTo']) ? (int) $params['assignedTo'] : null,
            dueAt: $dueAt,
        );
        $this->tasks->save($task);
        $taskUid = $task->uid->toString();

        $relationUid = null;
        if (($params['linkToTrigger'] ?? false) && $this->relations !== null) {
            $entityType = (string) ($eventData['_event']['entityType'] ?? '');
            $entityUid = (string) ($eventData['_event']['entityId'] ?? '');
            if ($entityType !== '' && $entityUid !== '') {
                $relationUid = $this->relations->linkToEntity(
                    $organizationUid,
                    $taskUid,
                    $entityType,
                    $entityUid,
                );
            }
        }

        $reminderResult = null;
        $reminder = $params['reminder'] ?? null;
        if (is_array($reminder) && $this->reminders !== null) {
            if ($dueAt === null) {
                throw new \InvalidArgumentException('A tasks.create reminder requires dueInMinutes.');
            }
            $beforeMinutes = max(0, (int) ($reminder['beforeMinutes'] ?? 0));
            $actorId = $eventData['_event']['actorId'] ?? null;
            $reminderResult = $this->reminders->scheduleEmail(
                organizationUid: $organizationUid,
                taskUid: $taskUid,
                taskTitle: $task->title,
                remindAt: $dueAt->modify("-{$beforeMinutes} minutes"),
                recipientUserId: (int) ($reminder['recipientUserId'] ?? $task->assignedTo ?? 0),
                recipientEmail: $this->interpolate((string) ($reminder['recipientEmail'] ?? ''), $eventData),
                fromAddress: $this->interpolate((string) ($reminder['fromAddress'] ?? ''), $eventData),
                createdBy: is_int($actorId) || (is_string($actorId) && ctype_digit($actorId))
                    ? (int) $actorId
                    : null,
            );
        }

        return array_filter([
            'taskUid' => $taskUid,
            'relationUid' => $relationUid,
            'reminderUid' => isset($reminderResult['reminder'])
                ? $reminderResult['reminder']->uid->toString()
                : null,
            'jobUid' => $reminderResult['jobUid'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Resolves {{ dot.path }} placeholders against the event data.
     *
     * @param array<string, mixed> $eventData
     */
    private function interpolate(string $template, array $eventData): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $match) use ($eventData): string {
                $value = $eventData;
                foreach (explode('.', $match[1]) as $segment) {
                    if (!is_array($value) || !array_key_exists($segment, $value)) {
                        return '';
                    }
                    $value = $value[$segment];
                }

                return is_scalar($value) ? (string) $value : '';
            },
            $template,
        );
    }
}
