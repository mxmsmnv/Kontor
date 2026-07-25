<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Application\KanbanBoardService;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class KanbanBoardServiceTest extends DatabaseTestCase
{
    public function test_board_groups_deals_by_stage_in_column_order(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deals = new DealRepository($this->pdo, new OrganizationRepository($this->pdo));

        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Deal A'));
        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Deal B'));
        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][1]->uid->toString(), 'Deal C'));

        $board = (new KanbanBoardService(new StageRepository($this->pdo), $deals))->board($fixture['pipeline']->uid->toString());

        $this->assertCount(4, $board);
        $this->assertSame('qualified', $board[0]['stage']->nameKey);
        $this->assertCount(2, $board[0]['deals']);
        $this->assertCount(1, $board[1]['deals']);
        $this->assertCount(0, $board[2]['deals']);
    }
}
