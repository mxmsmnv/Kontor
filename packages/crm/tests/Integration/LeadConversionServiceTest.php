<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Application\LeadConversionService;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;

final class LeadConversionServiceTest extends DatabaseTestCase
{
    private function service(): LeadConversionService
    {
        $organizations = new OrganizationRepository($this->pdo);

        return new LeadConversionService(
            new LeadRepository($this->pdo, $organizations),
            new PipelineRepository($this->pdo, $organizations),
            new StageRepository($this->pdo),
            new DealRepository($this->pdo, $organizations),
        );
    }

    public function test_converts_a_qualified_lead_into_a_deal_in_the_first_open_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $organizations = new OrganizationRepository($this->pdo);
        $leads = new LeadRepository($this->pdo, $organizations);

        $lead = Lead::create($this->organizationUid, 'Big opportunity', contactUid: 'ct_01', estimatedValue: Money::ofMinor(500000, 'EUR'));
        $leads->save($lead);

        $deal = $this->service()->convert($lead->uid->toString());

        $this->assertSame('Big opportunity', $deal->title);
        $this->assertSame('ct_01', $deal->contactUid);
        $this->assertSame(500000, $deal->value->amountMinor());
        $this->assertSame($fixture['stages'][0]->uid->toString(), $deal->stageUid);

        $converted = $leads->find($lead->uid->toString());
        $this->assertTrue($converted->isConverted());
        $this->assertSame($deal->uid->toString(), $converted->convertedDealUid);
    }

    public function test_refuses_to_convert_an_unqualified_lead(): void
    {
        $this->createDefaultPipeline();
        $organizations = new OrganizationRepository($this->pdo);
        $leads = new LeadRepository($this->pdo, $organizations);

        $lead = Lead::create($this->organizationUid, 'No contact linked yet');
        $leads->save($lead);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be linked to a contact or company');

        $this->service()->convert($lead->uid->toString());
    }

    public function test_refuses_to_convert_an_already_converted_lead(): void
    {
        $this->createDefaultPipeline();
        $organizations = new OrganizationRepository($this->pdo);
        $leads = new LeadRepository($this->pdo, $organizations);

        $lead = Lead::create($this->organizationUid, 'Big opportunity', contactUid: 'ct_01');
        $leads->save($lead);

        $service = $this->service();
        $service->convert($lead->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already converted');

        $service->convert($lead->uid->toString());
    }

    public function test_refuses_when_no_default_pipeline_is_configured(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $leads = new LeadRepository($this->pdo, $organizations);
        $lead = Lead::create($this->organizationUid, 'Big opportunity', contactUid: 'ct_01');
        $leads->save($lead);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No default deal pipeline');

        $this->service()->convert($lead->uid->toString());
    }
}
