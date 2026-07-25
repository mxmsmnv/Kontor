<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Application\CRMService;
use Kontor\CRM\Application\LeadConversionService;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CRMServiceTest extends DatabaseTestCase
{
    private DealRepository $deals;
    private LeadRepository $leads;
    private StageRepository $stages;
    private CRMService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->deals = new DealRepository($this->pdo, $organizations);
        $this->leads = new LeadRepository($this->pdo, $organizations);
        $this->stages = new StageRepository($this->pdo);
        $pipelines = new PipelineRepository($this->pdo, $organizations);

        $this->service = new CRMService(
            new LeadConversionService($this->leads, $pipelines, $this->stages, $this->deals),
            $this->deals,
            $this->stages,
        );
    }

    public function test_convert_lead_returns_the_new_deal_uid(): void
    {
        $this->createDefaultPipeline();
        $lead = Lead::create($this->organizationUid, 'Big opportunity', contactUid: 'ct_01');
        $this->leads->save($lead);

        $dealUid = $this->service->convertLead($lead->uid->toString());

        $this->assertNotNull($this->deals->find($dealUid));
    }

    public function test_move_deal_to_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deal = Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal');
        $this->deals->save($deal);

        $this->service->moveDealToStage($deal->uid->toString(), $fixture['stages'][1]->uid->toString());

        $this->assertSame($fixture['stages'][1]->uid->toString(), $this->deals->find($deal->uid->toString())->stageUid);
    }

    public function test_move_deal_to_stage_refuses_a_closed_deal(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deal = Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal');
        $this->deals->save($deal);
        $this->service->closeDealWon($deal->uid->toString());

        $this->expectException(\RuntimeException::class);

        $this->service->moveDealToStage($deal->uid->toString(), $fixture['stages'][1]->uid->toString());
    }

    public function test_close_deal_won_moves_to_the_won_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deal = Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal');
        $this->deals->save($deal);

        $this->service->closeDealWon($deal->uid->toString());

        $found = $this->deals->find($deal->uid->toString());
        $this->assertSame('won', $found->status);
        $this->assertNotNull($found->wonAt);
        $this->assertSame($fixture['stages'][2]->uid->toString(), $found->stageUid);
    }

    public function test_close_deal_lost_records_the_reason(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deal = Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal');
        $this->deals->save($deal);

        $this->service->closeDealLost($deal->uid->toString(), 'Went with a competitor');

        $found = $this->deals->find($deal->uid->toString());
        $this->assertSame('lost', $found->status);
        $this->assertSame('Went with a competitor', $found->lostReason);
        $this->assertSame($fixture['stages'][3]->uid->toString(), $found->stageUid);
    }

    public function test_cannot_close_an_already_closed_deal(): void
    {
        $fixture = $this->createDefaultPipeline();
        $deal = Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal');
        $this->deals->save($deal);
        $this->service->closeDealWon($deal->uid->toString());

        $this->expectException(\RuntimeException::class);

        $this->service->closeDealLost($deal->uid->toString());
    }
}
