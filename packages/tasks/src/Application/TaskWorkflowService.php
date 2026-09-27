<?php

declare(strict_types=1);

namespace Kontor\Tasks\Application;

use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;
use RuntimeException;

/**
 * The "tasks" milestone's own lifecycle, plus "recurrence": completing a
 * recurring task spawns its next occurrence rather than just closing it
 * out — the entire point of a recurring task is that completing one
 * instance produces the next.
 */
final class TaskWorkflowService
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function start(string $taskUid): Task
    {
        $task = $this->tasks->require($taskUid);

        if (!$task->isOpen()) {
            throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be started.");
        }

        $task->status = 'in_progress';
        $this->tasks->save($task);

        return $task;
    }

    /**
     * @return array{completed: Task, next: ?Task}
     */
    public function complete(string $taskUid): array
    {
        $task = $this->tasks->require($taskUid);

        if (!$task->isOpen()) {
            throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be completed.");
        }

        $task->status = 'done';
        $task->completedAt = new \DateTimeImmutable();
        $this->tasks->save($task);

        $next = null;
        $nextDueAt = $task->nextOccurrenceDueAt();

        if ($nextDueAt !== null) {
            $next = Task::create(
                organizationId: $task->organizationId,
                title: $task->title,
                description: $task->description,
                priority: $task->priority,
                assignedTo: $task->assignedTo,
                dueAt: $nextDueAt,
                recurrenceRule: $task->recurrenceRule,
                recurrenceUntil: $task->recurrenceUntil,
            );
            $this->tasks->save($next);
        }

        return ['completed' => $task, 'next' => $next];
    }

    public function cancel(string $taskUid): Task
    {
        $task = $this->tasks->require($taskUid);

        if (!$task->isOpen()) {
            throw new RuntimeException("Task \"{$taskUid}\" is not open and cannot be cancelled.");
        }

        $task->status = 'cancelled';
        $this->tasks->save($task);

        return $task;
    }
}
