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
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Events\KontorEvent;

final class CRMServiceTest extends DatabaseTestCase
{
    private DealRepository $deals;
    private LeadRepository $leads;
    private StageRepository $stages;
    private CRMService $service;
    private EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->deals = new DealRepository($this->pdo, $organizations);
        $this->leads = new LeadRepository($this->pdo, $organizations);
        $this->stages = new StageRepository($this->pdo);
        $pipelines = new PipelineRepository($this->pdo, $organizations);
        $this->events = new EventDispatcher();

        $this->service = new CRMService(
            new LeadConversionService($this->leads, $pipelines, $this->stages, $this->deals),
            $this->deals,
            $this->stages,
            $this->events,
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

    public function test_move_deal_to_stage_refuses_a_stage_from_another_pipeline(): void
    {
        $fixture = $this->createDefaultPipeline();
        $otherPipeline = \Kontor\CRM\Domain\Pipeline::create($this->organizationUid, 'Other');
        (new PipelineRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($otherPipeline);
        $otherStage = \Kontor\CRM\Domain\Stage::create(
            $otherPipeline->uid->toString(),
            'incoming',
            ['en' => 'Incoming'],
        );
        $this->stages->save($otherStage);
        $deal = Deal::create(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            $fixture['stages'][0]->uid->toString(),
            'Big deal'
        );
        $this->deals->save($deal);

        $this->expectException(\RuntimeException::class);

        $this->service->moveDealToStage($deal->uid->toString(), $otherStage->uid->toString());
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

    public function test_documented_lifecycle_events_have_stable_names_and_payloads(): void
    {
        $captured = [];
        foreach ([
            'crm.lead.converted',
            'crm.deal.stage_changed',
            'crm.deal.won',
            'crm.deal.lost',
        ] as $eventName) {
            $this->events->subscribe(
                $eventName,
                static function (KontorEvent $event) use (&$captured): void {
                    $captured[] = $event;
                },
            );
        }

        $fixture = $this->createDefaultPipeline();
        $lead = Lead::create($this->organizationUid, 'Eventful opportunity', contactUid: 'ct_01');
        $this->leads->save($lead);
        $dealUid = $this->service->convertLead($lead->uid->toString(), 'user-42');
        $this->service->moveDealToStage(
            $dealUid,
            $fixture['stages'][1]->uid->toString(),
            'user-42',
        );
        $this->service->closeDealWon($dealUid, 'user-42');

        $lostDeal = Deal::create(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            $fixture['stages'][0]->uid->toString(),
            'Lost opportunity',
        );
        $this->deals->save($lostDeal);
        $this->service->closeDealLost($lostDeal->uid->toString(), 'Budget withdrawn');

        $this->assertSame([
            'crm.lead.converted',
            'crm.deal.stage_changed',
            'crm.deal.stage_changed',
            'crm.deal.won',
            'crm.deal.lost',
        ], array_map(static fn (KontorEvent $event): string => $event->event, $captured));

        $this->assertSame($this->organizationUid, $captured[0]->organizationId);
        $this->assertSame('lead', $captured[0]->entityType);
        $this->assertSame($lead->uid->toString(), $captured[0]->entityId);
        $this->assertSame('user', $captured[0]->actorType);
        $this->assertSame('user-42', $captured[0]->actorId);
        $this->assertSame(['dealUid' => $dealUid], $captured[0]->data);

        $this->assertSame('deal', $captured[1]->entityType);
        $this->assertSame($dealUid, $captured[1]->entityId);
        $this->assertSame(['stageUid' => $fixture['stages'][0]->uid->toString()], $captured[1]->data);
        $this->assertSame(['stageUid' => $fixture['stages'][1]->uid->toString()], $captured[2]->data);
        $this->assertSame([], $captured[3]->data);

        $this->assertSame($lostDeal->uid->toString(), $captured[4]->entityId);
        $this->assertSame('system', $captured[4]->actorType);
        $this->assertNull($captured[4]->actorId);
        $this->assertSame(['reason' => 'Budget withdrawn'], $captured[4]->data);
        $this->assertSame('1.0', $captured[4]->version);
    }
}
