<?php

declare(strict_types=1);

namespace Kontor\CRM\Application;

use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;

/**
 * Substage 3.3 "Kanban" — the actual drag-and-drop board is a frontend/JS
 * concern, out of scope for this backend-only repository (no admin UI has
 * been built for any component yet). This is the data shape a board would
 * render: stages in column order, each with its deals.
 */
final class KanbanBoardService
{
    public function __construct(
        private readonly StageRepository $stages,
        private readonly DealRepository $deals,
    ) {
    }

    /**
     * @return array<int, array{stage: \Kontor\CRM\Domain\Stage, deals: array<int, \Kontor\CRM\Domain\Deal>}>
     */
    public function board(string $pipelineUid): array
    {
        $columns = [];

        foreach ($this->stages->forPipeline($pipelineUid) as $stage) {
            $columns[] = [
                'stage' => $stage,
                'deals' => $this->deals->forStage($stage->uid->toString()),
            ];
        }

        return $columns;
    }
}
