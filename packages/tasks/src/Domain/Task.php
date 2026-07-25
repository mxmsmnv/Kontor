<?php

declare(strict_types=1);

namespace Kontor\Tasks\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * Status transitions and recurrence spawning live in TaskWorkflowService,
 * not here — creating the next occurrence needs a repository (to save the
 * new row), so it isn't a pure domain method. This class only knows how to
 * compute *when* the next occurrence would fall due.
 */
final class Task
{
    private const RECURRENCE_INTERVALS = [
        'daily' => '+1 day',
        'weekly' => '+1 week',
        'monthly' => '+1 month',
        'yearly' => '+1 year',
    ];

    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $title,
        public ?string $description,
        public string $status,
        public string $priority,
        public ?int $assignedTo,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $dueAt,
        public ?\DateTimeImmutable $completedAt,
        public ?string $recurrenceRule,
        public ?\DateTimeImmutable $recurrenceUntil,
    ) {
    }

    public static function create(
        string $organizationId,
        string $title,
        ?string $description = null,
        string $priority = 'normal',
        ?int $assignedTo = null,
        ?\DateTimeImmutable $startAt = null,
        ?\DateTimeImmutable $dueAt = null,
        ?string $recurrenceRule = null,
        ?\DateTimeImmutable $recurrenceUntil = null,
    ): self {
        if ($recurrenceRule !== null && !array_key_exists($recurrenceRule, self::RECURRENCE_INTERVALS)) {
            throw new \InvalidArgumentException("\"{$recurrenceRule}\" is not a supported recurrence rule.");
        }

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            title: $title,
            description: $description,
            status: 'open',
            priority: $priority,
            assignedTo: $assignedTo,
            startAt: $startAt,
            dueAt: $dueAt,
            completedAt: null,
            recurrenceRule: $recurrenceRule,
            recurrenceUntil: $recurrenceUntil,
        );
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->dueAt !== null && $this->dueAt < new \DateTimeImmutable();
    }

    public function isRecurring(): bool
    {
        return $this->recurrenceRule !== null;
    }

    /**
     * The due date the next occurrence would fall on, advancing from this
     * task's own due date (or now, if it had none) by one recurrence
     * interval — or null if this task isn't recurring, or the next
     * occurrence would fall after `recurrenceUntil`.
     */
    public function nextOccurrenceDueAt(): ?\DateTimeImmutable
    {
        if ($this->recurrenceRule === null) {
            return null;
        }

        $interval = self::RECURRENCE_INTERVALS[$this->recurrenceRule];
        $next = ($this->dueAt ?? new \DateTimeImmutable())->modify($interval);

        if ($this->recurrenceUntil !== null && $next > $this->recurrenceUntil) {
            return null;
        }

        return $next;
    }
}
