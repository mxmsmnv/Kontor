<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

final class StageRepositoryTest extends DatabaseTestCase
{
    public function test_for_pipeline_returns_stages_ordered_by_sort_order(): void
    {
        $fixture = $this->createDefaultPipeline();

        $stages = (new \Kontor\CRM\Infrastructure\Persistence\StageRepository($this->pdo))
            ->forPipeline($fixture['pipeline']->uid->toString());

        $this->assertSame(['qualified', 'negotiation', 'won', 'lost'], array_map(fn ($s) => $s->nameKey, $stages));
    }

    public function test_first_open_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $stages = new \Kontor\CRM\Infrastructure\Persistence\StageRepository($this->pdo);

        $first = $stages->firstOpenStage($fixture['pipeline']->uid->toString());

        $this->assertSame('qualified', $first->nameKey);
    }

    public function test_first_stage_of_type_won_and_lost(): void
    {
        $fixture = $this->createDefaultPipeline();
        $stages = new \Kontor\CRM\Infrastructure\Persistence\StageRepository($this->pdo);

        $this->assertSame('won', $stages->firstStageOfType($fixture['pipeline']->uid->toString(), 'won')->nameKey);
        $this->assertSame('lost', $stages->firstStageOfType($fixture['pipeline']->uid->toString(), 'lost')->nameKey);
    }
}
