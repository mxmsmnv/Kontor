<?php

declare(strict_types=1);

namespace Kontor\Projects\Application;

use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Projects\Infrastructure\Persistence\MilestoneRepository;
use RuntimeException;

final class ProjectMilestoneService
{
    public function __construct(private readonly MilestoneRepository $milestones)
    {
    }

    public function complete(string $milestoneUid): ProjectMilestone
    {
        $milestone = $this->milestones->require($milestoneUid);

        if ($milestone->isCompleted()) {
            throw new RuntimeException("Project milestone \"{$milestoneUid}\" is already completed.");
        }

        $milestone->status = 'completed';
        $milestone->completedAt = new \DateTimeImmutable();
        $milestone->updatedAt = new \DateTimeImmutable();
        $this->milestones->save($milestone);

        return $milestone;
    }

    public function reopen(string $milestoneUid): ProjectMilestone
    {
        $milestone = $this->milestones->require($milestoneUid);

        if (!$milestone->isCompleted()) {
            throw new RuntimeException("Project milestone \"{$milestoneUid}\" is not completed.");
        }

        $milestone->status = 'pending';
        $milestone->completedAt = null;
        $milestone->updatedAt = new \DateTimeImmutable();
        $this->milestones->save($milestone);

        return $milestone;
    }
}
